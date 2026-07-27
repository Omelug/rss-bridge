<?php

declare(strict_types=1);

class BazarCzBridge extends BridgeAbstract
{
    const NAME = 'Bazar.cz';
    const URI = 'https://www.bazar.cz';
    const DESCRIPTION = 'Returns search results from Bazar.cz classifieds';
    const MAINTAINER = 'Omelug';
    const PARAMETERS = [[
        'keyword' => [
            'name' => 'Hledaný výraz',
            'type' => 'text',
            'required' => true,
            'exampleValue' => 'router',
        ],
        'category' => [
            'name' => 'Kategorie',
            'type' => 'list',
            'values' => [
                'Vše' => '',
                'Auta' => 'osobni-auta',
                'Bílá technika' => 'bila-technika',
                'Elektro' => 'elektro',
                'Foto' => 'foto',
                'Hry, hobby' => 'hry-hobby',
                'Hudba, film' => 'hudba-film-kultura',
                'Kancelář' => 'kancelar',
                'Knihy' => 'antikvariat',
                'Mobil, navigace' => 'mobil',
                'Motorky' => 'silnicni-motorky',
                'Nábytek' => 'nabytek',
                'Oblečení, boty' => 'obleceni-boty-doplnky',
                'Počítače' => 'pc',
                'Pro děti' => 'detske',
                'Sběratelství' => 'sberatelstvi',
                'Služby' => 'pujcky-oddluzeni-sluzby',
                'Sport' => 'sport',
                'Stavba, bydlení' => 'stavba-dum-zahrada',
                'Stroje' => 'stroje',
                'Zbraně a výstroj' => 'zbrane-vystroj',
                'Zvířata a chov' => 'chovatelstvi-zvirata',
                'Ostatní' => 'ostatni',
            ],
            'defaultValue' => '',
        ],
        'minPrice' => [
            'name' => 'Min. cena (Kč)',
            'type' => 'number',
            'required' => false,
        ],
        'maxPrice' => [
            'name' => 'Max. cena (Kč)',
            'type' => 'number',
            'required' => false,
        ],
        'onlyNew' => [
            'name' => 'Pouze nové zboží',
            'type' => 'checkbox',
            'defaultValue' => false,
        ],
        'sellerType' => [
            'name' => 'Typ prodejce',
            'type' => 'list',
            'values' => [
                'Vše' => '',
                'Soukromý' => 'private',
                'Komerční' => 'commercial',
            ],
            'defaultValue' => '',
        ],
    ]];
    const CACHE_TIMEOUT = 900;

    public function getIcon(): string
    {
        return self::URI . '/logoimage.png?width=32';
    }

    public function getURI(): string
    {
        return $this->getInput('keyword') ? $this->buildSearchUrl() : parent::getURI();
    }

    public function collectData(): void
    {
        if (!$this->getInput('keyword')) {
            throwClientException('Hledaný výraz je povinný');
        }

        $minPrice = $this->getInput('minPrice') !== '' && $this->getInput('minPrice') !== null ? (int) $this->getInput('minPrice') : null;
        $maxPrice = $this->getInput('maxPrice') !== '' && $this->getInput('maxPrice') !== null ? (int) $this->getInput('maxPrice') : null;
        $onlyNew = (bool) $this->getInput('onlyNew');
        $sellerType = $this->getInput('sellerType');

        $html = getSimpleHTMLDOM($this->buildSearchUrl());

        foreach ($html->find('div.sale-item') as $item) {
            $linkEl = $item->find('a', 0);
            if (!$linkEl) {
                continue;
            }

            $uri = $linkEl->href;
            $title = $linkEl->title ?: $linkEl->{'data-title'};
            if (!$title) {
                continue;
            }

            $imgEl = $item->find('img.img', 0);
            $image = $imgEl ? $imgEl->src : null;

            $priceEl = $item->find('span.price', 0);
            $priceText = $priceEl ? trim($priceEl->plaintext) : '';
            $priceNum = $this->parsePrice($priceText);

            if ($minPrice !== null && $priceNum !== null && $priceNum < $minPrice) {
                continue;
            }
            if ($maxPrice !== null && $priceNum !== null && $priceNum > $maxPrice) {
                continue;
            }

            $tags = [];
            $isNew = false;
            $isCommercial = false;
            foreach ($item->find('ul.tags li') as $tag) {
                if (str_contains($tag->class, 'hidden')) {
                    continue;
                }
                $tagText = trim($tag->plaintext);
                $tags[] = $tagText;
                if ($tagText === 'Nové') {
                    $isNew = true;
                }
                if ($tagText === 'Komerční') {
                    $isCommercial = true;
                }
            }

            if ($onlyNew && !$isNew) {
                continue;
            }
            if ($sellerType === 'private' && $isCommercial) {
                continue;
            }
            if ($sellerType === 'commercial' && !$isCommercial) {
                continue;
            }

            $locationEl = $item->find('div.location', 0);
            $location = $locationEl ? trim($locationEl->plaintext) : '';

            $descEl = $item->find('div.text', 0);
            $description = $descEl ? trim($descEl->plaintext) : '';

            $itemId = $item->attr['data-id'] ?? '';

            $content = '<p><strong>' . e($title) . '</strong></p>';
            if ($image) {
                $content .= '<p><img src="' . e($image) . '" alt="' . e($title) . '" /></p>';
            }
            if ($priceText) {
                $content .= '<p>Cena: <strong>' . e($priceText) . '</strong></p>';
            }
            if ($location) {
                $content .= '<p>Lokalita: ' . e($location) . '</p>';
            }
            if ($tags) {
                $content .= '<p>' . implode(' · ', array_map('e', $tags)) . '</p>';
            }
            if ($description) {
                $content .= '<p>' . e($description) . '</p>';
            }

            $this->items[] = [
                'title' => ($priceText ? "[$priceText] " : '') . $title,
                'uri' => $uri,
                'content' => $content,
                'uid' => $itemId,
                'timestamp' => $this->extractTimestamp($image),
                'enclosures' => $image ? [$image] : [],
            ];
        }
    }

    private function buildSearchUrl(): string
    {
        $keyword = urlencode($this->getInput('keyword'));
        $category = $this->getInput('category');
        if ($category) {
            return self::URI . "/$category/hledat/$keyword/?p2=0";
        }
        return self::URI . "/hledat/$keyword/?p2=0";
    }

    private function parsePrice(string $text): ?int
    {
        if (!$text || stripos($text, 'dohodou') !== false || stripos($text, 'zdarma') !== false) {
            return null;
        }
        $cleaned = preg_replace('/[^0-9]/', '', $text);
        return $cleaned !== '' ? (int) $cleaned : null;
    }

    private function extractTimestamp(?string $imageUrl): ?int
    {
        // URL pattern: /inzer/2026/0725/15/filename_5.jpg → 2026-07-25 15:xx
        if (!$imageUrl) {
            return null;
        }
        if (preg_match('#/inzer/(\d{4})/(\d{2})(\d{2})/(\d{2})/#', $imageUrl, $m)) {
            $dt = DateTime::createFromFormat('Y m d H', "$m[1] $m[2] $m[3] $m[4]");
            return $dt ? $dt->getTimestamp() : null;
        }
        return null;
    }
}
