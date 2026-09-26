<?php

declare(strict_types=1);

namespace Nexis\Install;

use Nexis\Builder\BlockDocument;
use Nexis\Support\Uuid;

/**
 * Generic starter pages for a fresh install (no customer-specific copy).
 */
final class DefaultSiteContent
{
    public function __construct(
        private readonly string $siteName,
        private readonly string $adminEmail,
    ) {
    }

    /**
     * @return list<array{slug: string, titles: array<string, string>, descriptions: array<string, string>}>
     */
    public static function demoServices(): array
    {
        return [
            [
                'slug' => 'beratung',
                'titles' => ['de' => 'Beratung', 'en' => 'Consultation'],
                'descriptions' => [
                    'de' => 'Persönliche Beratung zu Ihrem Anliegen.',
                    'en' => 'Personal advice for your request.',
                ],
            ],
            [
                'slug' => 'service',
                'titles' => ['de' => 'Service', 'en' => 'Service'],
                'descriptions' => [
                    'de' => 'Wartung und Instandhaltung nach Absprache.',
                    'en' => 'Maintenance by arrangement.',
                ],
            ],
            [
                'slug' => 'reparatur',
                'titles' => ['de' => 'Reparatur', 'en' => 'Repair'],
                'descriptions' => [
                    'de' => 'Reparaturen und Instandsetzung.',
                    'en' => 'Repairs and restoration.',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function homeDocument(
        string $basePath,
        bool $withWorkshop,
        bool $withForms,
        string $aboutPath = '/de/kontakt',
        string $locale = 'de',
    ): array {
        if ($locale === 'en') {
            return $this->homeDocumentEn($basePath, $withWorkshop, $withForms, $aboutPath);
        }

        $bp = rtrim($basePath, '/');
        $aboutHref = $bp . (str_starts_with($aboutPath, '/') ? $aboutPath : '/' . $aboutPath);
        $name = $this->siteName !== '' ? $this->siteName : 'Ihre Webseite';

        $children = [
            $this->heading('Willkommen bei ' . $name, 1),
            $this->text(
                'Das ist Ihre Startseite mit Platzhalter-Inhalten. '
                . 'Sie zeigen, was mit Nexis möglich ist — ersetzen Sie Texte, Blöcke und Links unter Admin → Seiten.',
            ),
            $this->heading('Was Sie mit Nexis machen können', 2),
            $this->columns(
                $this->text(
                    "Seiten & Blöcke\n"
                    . 'Bauen Sie Seiten aus Abschnitten, Überschriften, Text, Bildern und Buttons. '
                    . 'Revisionen und Vorschau helfen beim Veröffentlichen.',
                ),
                $this->text(
                    "Themes & Design\n"
                    . 'Wählen Sie ein Theme und passen Sie Farben sowie Typografie über Tokens an — '
                    . 'ohne den Kern zu forken.',
                ),
                $this->text(
                    "Plugins & Erweiterungen\n"
                    . 'Formulare, Consent, Blog, Katalog, Redirects und mehr: direkt im Admin suchen, '
                    . 'installieren und aktualisieren.',
                ),
            ),
            $this->heading('So starten Sie', 2),
            $this->text(
                "1. Impressum und Datenschutz mit Ihren Angaben füllen\n"
                . "2. Diese Startseite an Ihre Marke anpassen\n"
                . "3. Menüs unter Admin → Menüs pflegen\n"
                . '4. Bei Bedarf Plugins aktivieren und Inhalte ergänzen',
            ),
            $this->heading('Typische Bausteine', 2),
            $this->columns(
                $this->text(
                    "Mehrsprachigkeit\n"
                    . 'Lokalisierte Seiten und Menüs für Deutsch, Englisch und weitere Sprachen.',
                ),
                $this->text(
                    "Medien & Navigation\n"
                    . 'Bilder hochladen, Menüs verknüpfen, Footer-Links für Impressum und Kontakt setzen.',
                ),
                $this->text(
                    "Sicherheit & Konten\n"
                    . 'Benutzer, Rollen und optionale 2FA — für Admin und Mitgliederbereich.',
                ),
            ),
            $this->faq(
                'Häufige Fragen',
                [
                    'question' => 'Sind das echte Inhalte?',
                    'answer' => 'Nein — Platzhalter zur Orientierung. Löschen oder überschreiben Sie sie mit Ihren Texten und Bildern.',
                ],
                [
                    'question' => 'Wo bearbeite ich die Startseite?',
                    'answer' => 'Unter Admin → Seiten die Seite „Startseite“ öffnen und im Block-Builder anpassen.',
                ],
                [
                    'question' => 'Brauche ich Programmierkenntnisse?',
                    'answer' => 'Für den Alltag nicht. Themes und Plugins decken die meisten Erweiterungen ab; Entwickler können eigene Plugins einreichen.',
                ],
            ),
            $this->locationPlace(
                $name,
                "[Straße und Hausnummer]\n[PLZ Ort]",
                '',
                $this->adminEmail,
                "Mo–Fr 09:00–17:00\n(Platzhalter – bitte anpassen)",
            ),
        ];

        if ($withWorkshop) {
            $children[] = $this->heading('Nächste Schritte', 2);
            $children[] = $this->text(
                'Mit dem Workshop-Plugin können Sie Leistungen und Aufträge abbilden. '
                . 'Die Demo-Einträge lassen sich jederzeit ersetzen.',
            );
            $children[] = $this->button('Leistungen ansehen', $bp . '/de/leistungen');
            $children[] = $this->button('Auftrag anmelden', $bp . '/de/auftrag', 'secondary');
        }

        $children[] = $this->heading('Bereit für Ihre Inhalte', 2);
        $children[] = $this->text(
            'Ersetzen Sie diesen Abschnitt durch Ihre Botschaft — und verknüpfen Sie die Buttons mit Ihren Seiten.',
        );
        $children[] = $this->button(
            $withForms ? 'Über uns & Kontakt' : 'Impressum',
            $aboutHref,
            $withWorkshop ? 'secondary' : 'primary',
        );
        if ($withForms) {
            $children[] = $this->button('Impressum', $bp . '/de/impressum', 'secondary');
        }

        return $this->document(...$children);
    }

    /** @return array<string, mixed> */
    private function homeDocumentEn(
        string $basePath,
        bool $withWorkshop,
        bool $withForms,
        string $aboutPath,
    ): array {
        $bp = rtrim($basePath, '/');
        $aboutHref = $bp . (str_starts_with($aboutPath, '/') ? $aboutPath : '/' . $aboutPath);
        $name = $this->siteName !== '' ? $this->siteName : 'Your site';

        $children = [
            $this->heading('Welcome to ' . $name, 1),
            $this->text(
                'This is your homepage with placeholder content. '
                . 'It shows what Nexis can do — replace texts, blocks and links under Admin → Pages.',
            ),
            $this->heading('What you can do with Nexis', 2),
            $this->columns(
                $this->text(
                    "Pages & blocks\n"
                    . 'Build pages from sections, headings, text, images and buttons. '
                    . 'Revisions and preview help with publishing.',
                ),
                $this->text(
                    "Themes & design\n"
                    . 'Pick a theme and adjust colours and typography via tokens — without forking the core.',
                ),
                $this->text(
                    "Plugins & extensions\n"
                    . 'Forms, consent, blog, catalog, redirects and more: search, install and update in the admin.',
                ),
            ),
            $this->heading('How to get started', 2),
            $this->text(
                "1. Fill in legal notice and privacy with your details\n"
                . "2. Adapt this homepage to your brand\n"
                . "3. Maintain menus under Admin → Menus\n"
                . '4. Enable plugins and add content as needed',
            ),
            $this->heading('Typical building blocks', 2),
            $this->columns(
                $this->text(
                    "Multilingual\n"
                    . 'Localised pages and menus for German, English and more.',
                ),
                $this->text(
                    "Media & navigation\n"
                    . 'Upload images, link menus, set footer links for legal and contact pages.',
                ),
                $this->text(
                    "Security & accounts\n"
                    . 'Users, roles and optional 2FA — for admin and member areas.',
                ),
            ),
            $this->faq(
                'Frequently asked questions',
                [
                    'question' => 'Is this real content?',
                    'answer' => 'No — placeholders for orientation. Delete or overwrite them with your own text and images.',
                ],
                [
                    'question' => 'Where do I edit the homepage?',
                    'answer' => 'Under Admin → Pages open “Home” and edit it in the block builder.',
                ],
                [
                    'question' => 'Do I need coding skills?',
                    'answer' => 'Not for day-to-day use. Themes and plugins cover most extensions; developers can submit their own plugins.',
                ],
            ),
            $this->locationPlace(
                $name,
                "[Street and number]\n[Postcode City]",
                '',
                $this->adminEmail,
                "Mon–Fri 09:00–17:00\n(Placeholder — please update)",
            ),
        ];

        if ($withWorkshop) {
            $children[] = $this->heading('Next steps', 2);
            $children[] = $this->text(
                'With the workshop plugin you can offer services and bookings. '
                . 'Demo entries can be replaced any time.',
            );
            $children[] = $this->button('View services', $bp . '/en/services');
            $children[] = $this->button('Book a job', $bp . '/en/request', 'secondary');
        }

        $children[] = $this->heading('Ready for your content', 2);
        $children[] = $this->text(
            'Replace this section with your message — and link the buttons to your pages.',
        );
        $children[] = $this->button(
            $withForms ? 'About & contact' : 'Legal notice',
            $aboutHref,
            $withWorkshop ? 'secondary' : 'primary',
        );
        if ($withForms) {
            $children[] = $this->button('Legal notice', $bp . '/en/imprint', 'secondary');
        }

        return $this->document(...$children);
    }

    /** @return array<string, mixed> */
    public function aboutDocument(bool $withForms, string $locale = 'de'): array
    {
        if ($locale === 'en') {
            $name = $this->siteName !== '' ? $this->siteName : 'Your site';
            $children = [
                $this->heading('About & contact', 1),
                $this->text(
                    $name . ' – replace this text with your company description and contact details.',
                ),
            ];
            if ($this->adminEmail !== '') {
                $children[] = $this->text('Email: ' . $this->adminEmail);
            }
            if ($withForms) {
                $children[] = $this->heading('Write to us', 2);
                $children[] = $this->contactForm('en');
            }

            return $this->document(...$children);
        }

        $name = $this->siteName !== '' ? $this->siteName : 'Ihre Webseite';
        $children = [
            $this->heading('Über uns & Kontakt', 1),
            $this->text(
                $name . ' – ersetzen Sie diesen Text durch Ihre Unternehmensbeschreibung und Kontaktdaten.',
            ),
        ];
        if ($this->adminEmail !== '') {
            $children[] = $this->text('E-Mail: ' . $this->adminEmail);
        }
        if ($withForms) {
            $children[] = $this->heading('Schreiben Sie uns', 2);
            $children[] = $this->contactForm('de');
        }

        return $this->document(...$children);
    }

    /** @return array<string, mixed> */
    public function servicesPageDocument(bool $withWorkshopBlock, string $locale = 'de'): array
    {
        if ($locale === 'en') {
            $children = [
                $this->heading('Services', 1),
                $this->text('Introduce your services here. Demo entries can be replaced in the admin.'),
            ];
            if ($withWorkshopBlock) {
                $children[] = $this->workshopServices('Services', 'No services available.');
            } else {
                $lines = [];
                foreach (self::demoServices() as $service) {
                    $lines[] = '• ' . $service['titles']['en'] . ' – ' . $service['descriptions']['en'];
                }
                $children[] = $this->text(implode("\n", $lines));
            }

            return $this->document(...$children);
        }

        $children = [
            $this->heading('Leistungen', 1),
            $this->text('Hier können Sie Ihre Leistungen vorstellen. Die Demo-Einträge lassen sich im Admin ersetzen.'),
        ];
        if ($withWorkshopBlock) {
            $children[] = $this->workshopServices();
        } else {
            $lines = [];
            foreach (self::demoServices() as $service) {
                $lines[] = '• ' . $service['titles']['de'] . ' – ' . $service['descriptions']['de'];
            }
            $children[] = $this->text(implode("\n", $lines));
        }

        return $this->document(...$children);
    }

    /** @return array<string, mixed> */
    public function orderDocument(string $locale = 'de'): array
    {
        if ($locale === 'en') {
            return $this->document(
                $this->heading('Book a job', 1),
                $this->text('Describe your request — we will follow up with the next step.'),
                $this->workshopRequest(
                    'Book a job',
                    'Sign in to your customer account and describe your request.',
                    'Submit order',
                ),
            );
        }

        return $this->document(
            $this->heading('Auftrag anmelden', 1),
            $this->text('Schildern Sie Ihr Anliegen – wir melden uns mit dem nächsten Schritt.'),
            $this->workshopRequest(),
        );
    }

    /** @return array<string, mixed> */
    public function imprintDocument(string $locale = 'de'): array
    {
        if ($locale === 'en') {
            $name = $this->siteName !== '' ? $this->siteName : '[Company name]';
            $email = $this->adminEmail !== '' ? $this->adminEmail : '[Email]';

            return $this->document(
                $this->heading('Legal notice', 1),
                $this->text(
                    "Provider information\n\n"
                    . $name . "\n"
                    . "[Street and number]\n"
                    . "[Postcode City]\n\n"
                    . "Contact\n"
                    . 'Email: ' . $email . "\n\n"
                    . 'Please replace these placeholders with your full provider details.',
                ),
            );
        }

        $name = $this->siteName !== '' ? $this->siteName : '[Firmenname]';
        $email = $this->adminEmail !== '' ? $this->adminEmail : '[E-Mail]';

        return $this->document(
            $this->heading('Impressum', 1),
            $this->text(
                "Angaben gemäß § 5 TMG\n\n"
                . $name . "\n"
                . "[Straße und Hausnummer]\n"
                . "[PLZ Ort]\n\n"
                . "Kontakt\n"
                . 'E-Mail: ' . $email . "\n\n"
                . 'Bitte ersetzen Sie diese Platzhalter durch Ihre vollständigen Anbieterangaben.',
            ),
        );
    }

    /** @return array<string, mixed> */
    public function privacyDocument(string $locale = 'de'): array
    {
        if ($locale === 'en') {
            $name = $this->siteName !== '' ? $this->siteName : '[Controller]';
            $email = $this->adminEmail !== '' ? $this->adminEmail : '[Email]';

            return $this->document(
                $this->heading('Privacy policy', 1),
                $this->text(
                    'As of: ' . date('d.m.Y') . "\n\n"
                    . "1. Controller\n"
                    . $name . "\n"
                    . 'Email: ' . $email . "\n\n"
                    . "2. Strictly necessary cookies\n"
                    . "We use strictly necessary cookies to run the site (e.g. session, CSRF protection). "
                    . "They are required for operation and are set without consent.\n\n"
                    . "3. Contact form\n"
                    . "If you contact us via forms, we process your details to handle the request.\n\n"
                    . "4. Your rights\n"
                    . "You have rights of access, rectification, erasure, restriction, objection and data portability "
                    . "under the GDPR.\n\n"
                    . 'Please adapt this template to your actual processing.',
                ),
            );
        }

        $name = $this->siteName !== '' ? $this->siteName : '[Verantwortlicher]';
        $email = $this->adminEmail !== '' ? $this->adminEmail : '[E-Mail]';

        return $this->document(
            $this->heading('Datenschutzerklärung', 1),
            $this->text(
                'Stand: ' . date('d.m.Y') . "\n\n"
                . "1. Verantwortlicher\n"
                . $name . "\n"
                . 'E-Mail: ' . $email . "\n\n"
                . "2. Technisch notwendige Cookies\n"
                . "Für den Betrieb der Webseite setzen wir technisch notwendige Cookies ein (z. B. Session, CSRF-Schutz). "
                . "Diese sind für die Funktion erforderlich und werden ohne Einwilligung gesetzt.\n\n"
                . "3. Kontaktformular\n"
                . "Wenn Sie uns über Formulare schreiben, verarbeiten wir Ihre Angaben zur Bearbeitung der Anfrage.\n\n"
                . "4. Ihre Rechte\n"
                . "Sie haben Rechte auf Auskunft, Berichtigung, Löschung, Einschränkung, Widerspruch und Datenübertragbarkeit "
                . "gemäß DSGVO.\n\n"
                . 'Bitte passen Sie diese Vorlage an Ihre tatsächliche Datenverarbeitung an.',
            ),
        );
    }

    /**
     * @param array<string, mixed> ...$children
     * @return array<string, mixed>
     */
    private function document(array ...$children): array
    {
        return [
            'schemaVersion' => BlockDocument::SCHEMA_VERSION,
            'root' => [
                'id' => Uuid::v7(),
                'type' => 'core/section',
                'props' => ['width' => 'wide', 'padding' => 'lg'],
                'children' => array_values($children),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function heading(string $text, int $level = 1): array
    {
        return [
            'id' => Uuid::v7(),
            'type' => 'core/heading',
            'props' => ['text' => $text, 'level' => $level],
        ];
    }

    /** @return array<string, mixed> */
    private function text(string $text): array
    {
        return [
            'id' => Uuid::v7(),
            'type' => 'core/text',
            'props' => ['text' => $text],
        ];
    }

    /** @return array<string, mixed> */
    private function button(string $label, string $href, string $style = 'primary'): array
    {
        return [
            'id' => Uuid::v7(),
            'type' => 'core/button',
            'props' => ['label' => $label, 'href' => $href, 'style' => $style],
        ];
    }

    /**
     * @param array<string, mixed> ...$children
     * @return array<string, mixed>
     */
    private function columns(array ...$children): array
    {
        $count = count($children);

        return [
            'id' => Uuid::v7(),
            'type' => 'core/columns',
            'props' => ['columns' => max(2, min(4, $count > 0 ? $count : 2))],
            'children' => array_values($children),
        ];
    }

    /**
     * @param array{question: string, answer: string} ...$items
     * @return array<string, mixed>
     */
    private function faq(string $heading, array ...$items): array
    {
        $children = [];
        foreach ($items as $item) {
            $children[] = [
                'id' => Uuid::v7(),
                'type' => 'nexis/faq/item',
                'props' => [
                    'question' => $item['question'],
                    'answer' => $item['answer'],
                ],
            ];
        }

        return [
            'id' => Uuid::v7(),
            'type' => 'nexis/faq/accordion',
            'props' => ['heading' => $heading],
            'children' => $children,
        ];
    }

    /** @return array<string, mixed> */
    private function locationPlace(
        string $name,
        string $address,
        string $phone,
        string $email,
        string $hours,
    ): array {
        return [
            'id' => Uuid::v7(),
            'type' => 'nexis/location/place',
            'props' => [
                'name' => $name,
                'address' => $address,
                'phone' => $phone,
                'email' => $email,
                'hours' => $hours,
                'lat' => '',
                'lon' => '',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function contactForm(string $locale = 'de'): array
    {
        if ($locale === 'en') {
            return [
                'id' => Uuid::v7(),
                'type' => 'nexis/forms/contact',
                'props' => [
                    'heading' => 'Send a message',
                    'submitLabel' => 'Send',
                    'successMessage' => 'Thanks! We will get back to you as soon as possible.',
                ],
            ];
        }

        return [
            'id' => Uuid::v7(),
            'type' => 'nexis/forms/contact',
            'props' => [
                'heading' => 'Nachricht senden',
                'submitLabel' => 'Absenden',
                'successMessage' => 'Danke! Wir melden uns so bald wie möglich.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function workshopServices(
        string $heading = 'Unsere Leistungen',
        string $emptyText = 'Aktuell keine Leistungen eingetragen.',
    ): array {
        return [
            'id' => Uuid::v7(),
            'type' => 'nexis/workshop/services',
            'props' => [
                'heading' => $heading,
                'limit' => 12,
                'showPrice' => true,
                'emptyText' => $emptyText,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function workshopRequest(
        string $heading = 'Auftrag anmelden',
        string $intro = 'Melden Sie sich in Ihrem Kundenkonto an und beschreiben Sie Ihr Anliegen.',
        string $submitLabel = 'Auftrag absenden',
    ): array {
        return [
            'id' => Uuid::v7(),
            'type' => 'nexis/workshop/request',
            'props' => [
                'heading' => $heading,
                'intro' => $intro,
                'submitLabel' => $submitLabel,
                'serviceId' => '',
            ],
        ];
    }
}
