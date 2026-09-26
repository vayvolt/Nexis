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
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $basePath = (string) $request->getAttribute('base_path', '');
        $site = $this->sites->installed();
        $localeHint = RequestInput::string($request, 'locale', $site?->defaultLocale ?? 'de');
        if ($site === null) {
            return $this->responses->html($this->ui->get($localeHint, 'public.forms.no_site'), 503);
        }

        if (!$this->security->isSameSiteFormPost($request)) {
            return $this->responses->html($this->ui->get($localeHint, 'public.forms.bad_origin'), 403);
        }

        $name = trim(RequestInput::string($request, 'name'));
        $email = trim(RequestInput::string($request, 'email'));
        $message = trim(RequestInput::string($request, 'message'));
        $locale = RequestInput::string($request, 'locale', $site->defaultLocale);
        if ($name === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->responses->html($this->ui->get($locale, 'public.forms.invalid'), 422);
        }

        $idemKey = $this->idempotency->keyFrom($request);
        $requestHash = Idempotency::hash($name, strtolower($email), $message, $locale);
        $check = $this->idempotency->check($site->id, $idemKey, $requestHash);
        if ($check['kind'] === 'conflict') {
            return $this->responses->html($this->ui->get($locale, 'public.forms.idempotency_conflict'), 409);
        }
        if ($check['kind'] === 'replay') {
            return $this->replay($check['response'], $check['status_code'], $locale);
        }

        $id = Uuid::v7();
        $stmt = $this->pdo->prepare(
            'INSERT INTO plugin_nexis_forms_submissions
                (id, site_id, locale, name, email, message, created_at)
             VALUES (:id, :site_id, :locale, :name, :email, :message, :created_at)',
        );
        $stmt->execute([
            'id' => $id,
            'site_id' => $site->id->value,
            'locale' => $locale,
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'created_at' => gmdate('Y-m-d H:i:s.v'),
        ]);

        $this->mail->notifySubmission($site->id, $id, $name, $email, $message, $locale);

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
