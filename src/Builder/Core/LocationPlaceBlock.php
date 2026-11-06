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
        $locale = strtolower((string) ($context['locale'] ?? 'de'));
        $en = str_starts_with($locale, 'en');
        $name = trim((string) ($props['name'] ?? ''));
        $address = trim((string) ($props['address'] ?? ''));
        $phone = trim((string) ($props['phone'] ?? ''));
        $email = trim((string) ($props['email'] ?? ''));
        $hours = trim((string) ($props['hours'] ?? ''));
        $lat = trim((string) ($props['lat'] ?? ''));
        $lon = trim((string) ($props['lon'] ?? ''));
        $showMap = $lat !== '' && $lon !== '' && is_numeric($lat) && is_numeric($lon);
        $hoursLabel = $en ? 'Opening hours' : 'Öffnungszeiten';
        $mapTitle = $en ? 'Map' : 'Karte';
        $loadLabel = $en ? 'Load map' : 'Karte laden';
        $loadHint = $en
            ? 'The map is provided by OpenStreetMap and loads only after you click.'
            : 'Die Karte wird von OpenStreetMap bereitgestellt und erst nach Ihrem Klick geladen.';
        $largerLabel = $en ? 'Larger map' : 'Größere Karte';

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
            $html .= '<div class="nx-location__hours"><strong>' . $this->e($hoursLabel) . '</strong><br>'
                . nl2br($this->e($hours)) . '</div>';
        }
        $html .= '</div>';

        if ($showMap) {
            $latF = (float) $lat;
            $lonF = (float) $lon;
            $delta = 0.01;
            $src = 'https://www.openstreetmap.org/export/embed.html?bbox='
                . rawurlencode(($lonF - $delta) . ',' . ($latF - $delta) . ',' . ($lonF + $delta) . ',' . ($latF + $delta))
                . '&layer=mapnik&marker=' . rawurlencode($latF . ',' . $lonF);
            $html .= '<div class="nx-location__map" data-src="' . $this->e($src) . '" data-title="'
                . $this->e($mapTitle) . '">';
            $html .= '<div class="nx-location__map-facade">';
            $html .= '<p class="nx-location__map-hint">' . $this->e($loadHint) . '</p>';
            $html .= '<button type="button" class="nx-location__map-load" data-nx-map-load>'
                . $this->e($loadLabel) . '</button>';
            $html .= '</div>';
            $html .= '<p class="nx-location__map-link"><a href="https://www.openstreetmap.org/?mlat='
                . rawurlencode((string) $latF) . '&amp;mlon=' . rawurlencode((string) $lonF)
                . '#map=16/' . rawurlencode((string) $latF) . '/' . rawurlencode((string) $lonF)
                . '" target="_blank" rel="noopener">' . $this->e($largerLabel) . '</a></p>';
            $html .= '</div>';
        }

        $html .= '</div></section>';

        return $html;
    }
}
