<?php

declare(strict_types=1);

/** @noinspection PhpUnused */
class BazosBridge extends BridgeAbstract
{
    const NAME = 'Bazos.cz';
    const URI = 'https://www.bazos.cz';
    const DESCRIPTION = 'Returns search results from Bazos.cz classifieds';
    const MAINTAINER = 'Omelug';
    const PARAMETERS = [[
        'hledat' => [
            'name' => 'Search term',
            'type' => 'text',
            'required' => true,
            'exampleValue' => 'WPA3 router',
        ],
        'hlokalita' => [
            'name' => 'Location',
            'type' => 'text',
            'required' => false,
            'exampleValue' => 'Praha',
        ],
        'humkreis' => [
            'name' => 'Radius (km)',
            'type' => 'number',
            'defaultValue' => 25,
            'minimum' => 0,
            'maximum' => 1000,
        ],
        'cenaod' => [
            'name' => 'Min price (Kč)',
            'type' => 'number',
            'required' => false,
        ],
        'cenado' => [
            'name' => 'Max price (Kč)',
            'type' => 'number',
            'required' => false,
        ],
        'order' => [
            'name' => 'Sort by',
            'type' => 'list',
            'values' => [
                'Newest first' => '0',
                'Price ascending' => '1',
                'Most viewed' => '3',
            ],
            'defaultValue' => '0',
        ],
    ]];
    const CACHE_TIMEOUT = 900;

    public function getIcon(): string
    {
        return ''; // fing png image
    }

    public function getURI(): string
    {
        $term = $this->getInput('hledat');
        if (!$term) {
            return parent::getURI();
        }
        return self::URI . '/search.php?' . http_build_query($this->buildParams());
    }

    public function collectData(): void
    {
        $url = self::URI . '/search.php?' . http_build_query($this->buildParams());
        $html = getSimpleHTMLDOM($url);

        foreach ($html->find('div.inzeraty.inzeratyflex') as $item) {
            $titleEl = $item->find('h2.nadpis a', 0);
            if (!$titleEl) {
                continue;
            }

            $title = $titleEl->plaintext;
            $uri = $titleEl->href;

            preg_match('#/inzerat/(\d+)/#', $uri, $m);
            $uid = $m[1] ?? $uri;

            $imgEl = $item->find('img.obrazek', 0);
            $image = $imgEl ? $imgEl->src : null;

            $priceEl = $item->find('div.inzeratycena span[translate=no]', 0);
            $price = $priceEl ? trim($priceEl->plaintext) : '';

            $lokEl = $item->find('div.inzeratylok', 0);
            $location = $lokEl ? trim(str_replace('<br>', ' ', $lokEl->innertext)) : '';

            $descEl = $item->find('div.popis', 0);
            $description = $descEl ? trim($descEl->plaintext) : '';

            $dateEl = $item->find('span.velikost10', 0);
            $timestamp = null;
            if ($dateEl) {
                if (preg_match('#\[(\d+\.\d+\. \d{4})]#', $dateEl->plaintext, $dm)) {
                    $dt = DateTime::createFromFormat('j.n. Y', $dm[1]);
                    if ($dt) {
                        $timestamp = $dt->getTimestamp();
                    }
                }
            }

            $content = '<p><strong>' . e($title) . '</strong></p>';
            if ($image) {
                $content .= '<p><img src="' . e($image) . '" alt="' . e($title) . '" /></p>';
            }
            if ($price) {
                $content .= '<p>' . e($price) . '</p>';
            }
            if ($location) {
                $content .= '<p>' . e($location) . '</p>';
            }
            if ($description) {
                $content .= '<p>' . e($description) . '</p>';
            }

            $this->items[] = [
                'title' => $title,
                'uri' => $uri,
                'content' => $content,
                'uid' => $uid,
                'timestamp' => $timestamp,
                'enclosures' => $image ? [$image] : [],
            ];
        }
    }

    private function buildParams(): array
    {
        return [
            'hledat' => $this->getInput('hledat'),
            'rubriky' => 'www',
            'hlokalita' => (string) ($this->getInput('hlokalita') ?? ''),
            'humkreis' => (string) ($this->getInput('humkreis') ?? 25),
            'cenaod' => (string) ($this->getInput('cenaod') ?? ''),
            'cenado' => (string) ($this->getInput('cenado') ?? ''),
            'Submit' => 'Hledat',
            'order' => (string) ($this->getInput('order') ?? '0'),
            'kitx' => 'ano',
        ];
    }
}
