<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Http\Idempotency;
use Nexis\Http\RequestInput;
use Nexis\Http\RequestSecurity;
use Nexis\Http\ResponseFactory;
use Nexis\I18n\PublicUi;
use Nexis\Site\SiteRepository;
use Nexis\Support\Uuid;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class SubmitController
{
    public function __construct(
        private PDO $pdo,
        private SiteRepository $sites,
        private ResponseFactory $responses,
        private FormMailNotifier $mail,
        private Idempotency $idempotency,
        private RequestSecurity $security,
        private PublicUi $ui,
        private FormDefinitionStore $forms,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->sites->installed();
        $localeHint = RequestInput::string($request, 'locale', $site?->defaultLocale ?? 'de');
        if ($site === null) {
            return $this->responses->html($this->ui->get($localeHint, 'public.forms.no_site'), 503);
        }

        if (!$this->security->isSameSiteFormPost($request)) {
            return $this->responses->html($this->ui->get($localeHint, 'public.forms.bad_origin'), 403);
        }

        $locale = RequestInput::string($request, 'locale', $site->defaultLocale);
        $formRef = trim(RequestInput::string($request, 'form_id'));
        if ($formRef === '') {
            $formRef = trim(RequestInput::string($request, 'form_slug', FormDefinition::CONTACT_SLUG));
        }
        $definition = $this->forms->find($site->id, $formRef !== '' ? $formRef : FormDefinition::CONTACT_SLUG)
            ?? FormDefinition::defaultContact($site->id->value);

        $values = [];
        foreach ($definition->fields as $field) {
            if ($field->type === 'checkbox') {
                $raw = RequestInput::string($request, $field->key);
                $values[$field->key] = ($raw === '1' || $raw === 'on' || $raw === 'true') ? '1' : '';
            } else {
                $values[$field->key] = trim(RequestInput::string($request, $field->key));
            }
        }

        $visible = ConditionEvaluator::filterVisibleFields($definition->fields, $values);
        $stored = [];
        foreach ($visible as $field) {
            $stored[$field->key] = (string) ($values[$field->key] ?? '');
        }

        $errors = ConditionEvaluator::validate($definition->fields, $values);
        if ($errors !== []) {
            return $this->responses->html($this->ui->get($locale, 'public.forms.invalid'), 422);
        }

        $name = (string) ($stored['name'] ?? '');
        $email = (string) ($stored['email'] ?? '');
        $message = (string) ($stored['message'] ?? '');
        if ($message === '' && $stored !== []) {
            $parts = [];
            foreach ($stored as $k => $v) {
                if ($k === 'name' || $k === 'email') {
                    continue;
                }
                $parts[] = $k . ': ' . $v;
            }
            $message = implode("\n", $parts);
        }

        $idemKey = $this->idempotency->keyFrom($request);
        $requestHash = Idempotency::hash(
            $definition->id !== '' ? $definition->id : $definition->slug,
            json_encode($stored, JSON_THROW_ON_ERROR),
            $locale,
        );
        $check = $this->idempotency->check($site->id, $idemKey, $requestHash);
        if ($check['kind'] === 'conflict') {
            return $this->responses->html($this->ui->get($locale, 'public.forms.idempotency_conflict'), 409);
        }
        if ($check['kind'] === 'replay') {
            return $this->replay($check['response'], $check['status_code'], $locale);
        }

        $id = Uuid::v7();
        $payloadJson = json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $formId = $definition->persisted && $definition->id !== '' ? $definition->id : null;

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO plugin_nexis_forms_submissions
                    (id, site_id, form_id, locale, name, email, message, payload_json, created_at)
                 VALUES
                    (:id, :site_id, :form_id, :locale, :name, :email, :message, :payload_json, :created_at)',
            );
            $stmt->execute([
                'id' => $id,
                'site_id' => $site->id->value,
                'form_id' => $formId,
                'locale' => $locale,
                'name' => $name !== '' ? $name : '—',
                'email' => $email !== '' ? $email : 'noreply@invalid.local',
                'message' => $message !== '' ? $message : '(leer)',
                'payload_json' => $payloadJson,
                'created_at' => gmdate('Y-m-d H:i:s.v'),
            ]);
        } catch (Throwable) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO plugin_nexis_forms_submissions
                    (id, site_id, locale, name, email, message, created_at)
                 VALUES (:id, :site_id, :locale, :name, :email, :message, :created_at)',
            );
            $stmt->execute([
                'id' => $id,
                'site_id' => $site->id->value,
                'locale' => $locale,
                'name' => $name !== '' ? $name : '—',
                'email' => $email !== '' ? $email : 'noreply@invalid.local',
                'message' => $message !== '' ? $message : '(leer)',
                'created_at' => gmdate('Y-m-d H:i:s.v'),
            ]);
        }

        $this->mail->notifySubmission($site->id, $id, $name !== '' ? $name : '—', $email, $message, $locale);

        $referer = $this->security->sameSiteReferer($request);
        if ($referer !== null) {
            $location = $referer . (str_contains($referer, '?') ? '&' : '?') . 'form=ok';
            $payload = ['type' => 'redirect', 'location' => $location];
            $this->idempotency->remember($site->id, $idemKey, $requestHash, 302, $payload);

            return $this->responses->redirect($location);
        }

        $body = $this->ui->get($locale, 'public.forms.thanks');
        $payload = ['type' => 'html', 'body' => $body];
        $this->idempotency->remember($site->id, $idemKey, $requestHash, 200, $payload);

        return $this->responses->html($body, 200);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function replay(array $response, int $statusCode, string $locale): ResponseInterface
    {
        $type = (string) ($response['type'] ?? '');
        if ($type === 'redirect' && isset($response['location']) && is_string($response['location'])) {
            return $this->responses->redirect($response['location']);
        }
        $body = isset($response['body']) && is_string($response['body'])
            ? $response['body']
            : $this->ui->get($locale, 'public.forms.thanks');

        return $this->responses->html($body, $statusCode > 0 ? $statusCode : 200);
    }
}
