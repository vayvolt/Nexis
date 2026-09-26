<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class LocationPlaceBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'nexis/location/place';
    }

    public function label(): string
    {
        return 'Standort';
    }

    public function defaultProps(): array
    {
        return [
            'name' => 'Standort',
            'address' => '',
            'phone' => '',
            'email' => '',
            'hours' => '',
            'lat' => '',
            'lon' => '',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'name' => (object) ['type' => 'string'],
                'address' => (object) ['type' => 'string'],
                'phone' => (object) ['type' => 'string'],
                'email' => (object) ['type' => 'string'],
                'hours' => (object) ['type' => 'string'],
                'lat' => (object) ['type' => 'string'],
                'lon' => (object) ['type' => 'string'],
            ],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $name = trim((string) ($props['name'] ?? ''));
        $address = trim((string) ($props['address'] ?? ''));
        $phone = trim((string) ($props['phone'] ?? ''));
        $email = trim((string) ($props['email'] ?? ''));
        $hours = trim((string) ($props['hours'] ?? ''));
        $lat = trim((string) ($props['lat'] ?? ''));
        $lon = trim((string) ($props['lon'] ?? ''));
        $showMap = $lat !== '' && $lon !== '' && is_numeric($lat) && is_numeric($lon);

        $html = '<section class="nx-location">';
        if ($name !== '') {
            $html .= '<h2 class="nx-location__name">' . $this->e($name) . '</h2>';
        }
        $html .= '<div class="nx-location__grid">';
        $html .= '<div class="nx-location__meta">';
        if ($address !== '') {
            $html .= '<p class="nx-location__address">' . nl2br($this->e($address)) . '</p>';
        }
        if ($phone !== '') {
            $tel = preg_replace('/\s+/', '', $phone) ?? $phone;
            $html .= '<p><a href="tel:' . $this->e($tel) . '">' . $this->e($phone) . '</a></p>';
        }
        if ($email !== '') {
            $html .= '<p><a href="mailto:' . $this->e($email) . '">' . $this->e($email) . '</a></p>';
        }
        if ($hours !== '') {
            $html .= '<div class="nx-location__hours"><strong>Öffnungszeiten</strong><br>'
                . nl2br($this->e($hours)) . '</div>';
        }
        $html .= '</div>';

        if ($showMap) {
            $latF = (float) $lat;
            $lonF = (float) $lon;
            $delta = 0.01;
            $bbox = ($lonF - $delta) . '%2C' . ($latF - $delta) . '%2C' . ($lonF + $delta) . '%2C' . ($latF + $delta);
            $marker = $latF . '%2C' . $lonF;
            $src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox
                . '&amp;layer=mapnik&amp;marker=' . $marker;
            $html .= '<div class="nx-location__map">';
            $html .= '<iframe title="Karte" loading="lazy" referrerpolicy="no-referrer-when-downgrade" '
                . 'src="' . $src . '"></iframe>';
            $html .= '<p class="nx-location__map-link"><a href="https://www.openstreetmap.org/?mlat='
                . rawurlencode((string) $latF) . '&amp;mlon=' . rawurlencode((string) $lonF)
                . '#map=16/' . rawurlencode((string) $latF) . '/' . rawurlencode((string) $lonF)
                . '" target="_blank" rel="noopener">Größere Karte</a></p>';
            $html .= '</div>';
        }

        $html .= '</div></section>';

        return $html;
    }
}
