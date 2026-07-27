<?php

declare(strict_types=1);

class SBazarBridge extends BridgeAbstract
{
    const NAME = 'Sbazar.cz';
    const URI = 'https://www.sbazar.cz';
    const DESCRIPTION = 'Returns search results from Sbazar.cz (Seznam) classifieds';
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
                'Auto, moto' => '1-auto-moto',
                'Dům, byt, zahrada' => '8-dum-byt-zahrada',
                'Elektro, počítače' => '30-elektro-pocitace',
                'Hudba, knihy, hry' => '295-hudba-knihy-hry-zabava',
                'Nemovitosti' => '77-nemovitosti',
                'Služby' => '82-sluzby',
                'Sport' => '27-sport',
                'Dětský bazar' => '29-detsky-bazar',
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
    ]];
    const CACHE_TIMEOUT = 900;

    public function getIcon(): string
    {
        return self::URI . '/static/public/img/favicon.ico';
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

        $minPrice = $this->getInput('minPrice') !== '' && $this->getInput('minPrice') !== null
            ? (int) $this->getInput('minPrice') : null;
        $maxPrice = $this->getInput('maxPrice') !== '' && $this->getInput('maxPrice') !== null
            ? (int) $this->getInput('maxPrice') : null;

        $html = getSimpleHTMLDOM($this->buildSearchUrl());

        foreach ($html->find('li[data-offer-id]') as $item) {
            $linkEl = $item->find('a', 0);
            if (!$linkEl) {
                continue;
            }

            $href = $linkEl->href;
            $uri = str_starts_with($href, 'http') ? $href : self::URI . $href;

            $imgEl = $item->find('img', 0);
            $title = $imgEl ? $imgEl->alt : null;
            if (!$title) {
                continue;
            }

            $image = $this->parseSrcset($imgEl ? $imgEl->srcset : null);

            $priceEl = $item->find('b', 0);
            $priceText = $priceEl ? trim($priceEl->plaintext) : '';
            $priceNum = $this->parsePrice($priceText);

            if ($minPrice !== null && $priceNum !== null && $priceNum < $minPrice) {
                continue;
            }
            if ($maxPrice !== null && $priceNum !== null && $priceNum > $maxPrice) {
                continue;
            }

            $location = '';
            foreach ($item->find('span') as $span) {
                $text = trim($span->plaintext);
                if (str_starts_with($text, 'v ') || str_starts_with($text, 'v\u{A0}')) {
                    $location = substr($text, 2);
                    break;
                }
            }

            $itemId = $item->attr['data-offer-id'] ?? '';

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

            $this->items[] = [
                'title' => ($priceText ? "[$priceText] " : '') . $title,
                'uri' => $uri,
                'content' => $content,
                'uid' => $itemId,
                'enclosures' => $image ? [$image] : [],
            ];
        }
    }

    private function buildSearchUrl(): string
    {
        $keyword = urlencode($this->getInput('keyword'));
        $category = $this->getInput('category');
        if ($category) {
            return self::URI . "/hledej/$keyword/$category";
        }
        return self::URI . "/hledej/$keyword";
    }

    private function parseSrcset(?string $srcset): ?string
    {
        if (!$srcset) {
            return null;
        }
        $first = trim(explode(',', $srcset)[0]);
        $url = trim(explode(' ', $first)[0]);
        if (!$url) {
            return null;
        }
        return str_starts_with($url, '//') ? 'https:' . $url : $url;
    }

    private function parsePrice(string $text): ?int
    {
        if (!$text || stripos($text, 'dohodou') !== false || stripos($text, 'zdarma') !== false) {
            return null;
        }
        $cleaned = preg_replace('/[^0-9]/', '', $text);
        return $cleaned !== '' ? (int) $cleaned : null;
    }
}
