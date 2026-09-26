<?php

declare(strict_types=1);

namespace Nexis\Mail;

use Nexis\I18n\PublicUi;
use Nexis\Site\SiteId;
use Nexis\Site\SiteRepository;

/**
 * Core mail handler for workshop orders until nexis/workshop registers its own.
 * Plugins may overwrite type `workshop_order` via registerMailJobHandler.
 */
final class WorkshopOrderMailHandler implements MailJobHandler
{
    public function __construct(
        private MailPort $mail,
        private PublicUi $ui,
        private SiteRepository $sites,
    ) {
    }

    public function handle(array $payload): void
    {
        $to = (string) ($payload['to'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $name = (string) ($payload['name'] ?? '');
        $email = (string) ($payload['email'] ?? '');
        $service = (string) ($payload['service'] ?? '');
        $message = (string) ($payload['message'] ?? '');
        $locale = (string) ($payload['locale'] ?? 'de');
        $orderId = (string) ($payload['order_id'] ?? '');
        $reference = (string) ($payload['reference'] ?? '');
        $company = (string) ($payload['company'] ?? '');
        $phone = (string) ($payload['phone'] ?? '');
        $paymentMethod = (string) ($payload['payment_method'] ?? 'invoice');
        $billingStreet = (string) ($payload['billing_street'] ?? '');
        $billingPostal = (string) ($payload['billing_postal_code'] ?? '');
        $billingCity = (string) ($payload['billing_city'] ?? '');
        $billingCountry = (string) ($payload['billing_country'] ?? '');
        $billingLines = array_values(array_filter([
            $billingStreet,
            trim($billingPostal . ' ' . $billingCity),
            $billingCountry,
        ], static fn (string $line): bool => trim($line) !== ''));
        $billingAddressText = $billingLines !== [] ? implode("\n", $billingLines) : '—';
        $billingAddressHtml = $billingLines !== [] ? implode(', ', $billingLines) : '—';
        $serviceLabel = $service !== '' ? $service : '—';
        $siteName = $this->siteNameFromPayload($payload);
        $paymentLabel = $this->ui->get($locale, 'mail.workshop_order.payment.' . $paymentMethod, [], $paymentMethod);
        $replace = [
            'site' => $siteName !== '' ? $siteName : '—',
            'locale' => $locale,
            'id' => $orderId,
            'reference' => $reference,
            'name' => $name,
            'email' => $email,
            'company' => $company !== '' ? $company : '—',
            'phone' => $phone !== '' ? $phone : '—',
            'payment' => $paymentLabel,
            'billing_address' => $billingAddressText,
            'service' => $serviceLabel,
            'message' => $message,
        ];
        $subject = $this->ui->get($locale, 'mail.workshop_order.subject', [
            'reference' => $reference,
            'site' => $siteName !== '' ? $siteName : 'Nexis',
        ]);
        $textBody = $this->ui->get($locale, 'mail.workshop_order.body', $replace);
        $heading = $this->ui->get($locale, 'mail.workshop_order.heading');
        $preheader = $this->ui->get($locale, 'mail.workshop_order.preheader', [
            'reference' => $reference,
            'name' => $name,
            'site' => $siteName !== '' ? $siteName : 'Nexis',
        ]);
        $inner = TransactionalMailHtml::badge(
            $this->ui->get($locale, 'mail.workshop_order.label.reference'),
            $reference,
        );
        $rows = [];
        if ($siteName !== '') {
            $rows[$this->ui->get($locale, 'mail.workshop_order.label.site')] = $siteName;
        }
        $rows[$this->ui->get($locale, 'mail.workshop_order.label.customer')] = $name !== '' ? $name : '—';
        $rows[$this->ui->get($locale, 'mail.workshop_order.label.email')] = $email !== '' ? $email : '—';
        if ($company !== '') {
            $rows[$this->ui->get($locale, 'mail.workshop_order.label.company')] = $company;
        }
        if ($phone !== '') {
            $rows[$this->ui->get($locale, 'mail.workshop_order.label.phone')] = $phone;
        }
        $rows[$this->ui->get($locale, 'mail.workshop_order.label.payment')] = $paymentLabel;
        $rows[$this->ui->get($locale, 'mail.workshop_order.label.billing_address')] = $billingAddressHtml;
        $rows[$this->ui->get($locale, 'mail.workshop_order.label.service')] = $serviceLabel;
        $rows[$this->ui->get($locale, 'mail.workshop_order.label.locale')] = $locale;
        $rows[$this->ui->get($locale, 'mail.workshop_order.label.id')] = $orderId !== '' ? $orderId : '—';
        $inner .= TransactionalMailHtml::rows($rows);
        $inner .= TransactionalMailHtml::message(
            $this->ui->get($locale, 'mail.workshop_order.label.message'),
            $message,
        );
        $inner .= TransactionalMailHtml::footer($this->ui->get($locale, 'mail.workshop_order.footer', [
            'site' => $siteName !== '' ? $siteName : 'Nexis',
        ]));
        $htmlBody = TransactionalMailHtml::wrap(
            $heading,
            $preheader,
            $inner,
            $siteName !== '' ? $siteName : 'Nexis',
        );

        $this->mail->send(new MailMessage(
            [$to],
            $subject,
            $textBody,
            $htmlBody,
            $email !== '' ? $email : null,
            is_string($payload['site_id'] ?? null) ? (string) $payload['site_id'] : null,
            $orderId !== '' ? 'workshop_order:' . $orderId : 'workshop_order',
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function siteNameFromPayload(array $payload): string
    {
        $siteId = (string) ($payload['site_id'] ?? '');
        if ($siteId !== '') {
            $site = $this->sites->findById(new SiteId($siteId));
            if ($site !== null && trim($site->name) !== '') {
                return trim($site->name);
            }
        }
        $installed = $this->sites->installed();
        if ($installed !== null && trim($installed->name) !== '') {
            return trim($installed->name);
        }

        return '';
    }
}
