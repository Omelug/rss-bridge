<?php

declare(strict_types=1);

class AukroBridge extends BridgeAbstract
{
    const NAME = 'Aukro.cz';
    const URI = 'https://aukro.cz';
    const DESCRIPTION = 'Returns search results from Aukro.cz marketplace';
    const MAINTAINER = 'Omelug';
    const PARAMETERS = [[
        'text' => [
            'name' => 'Search term',
            'type' => 'text',
            'required' => true,
            'exampleValue' => 'WPA3 router',
        ],
        'searchAll' => [
            'name' => 'Search in description',
            'type' => 'checkbox',
            'defaultValue' => 'checked',
        ],
        'subbrand' => [
            'name' => 'Section',
            'type' => 'list',
            'values' => [
                'All' => '',
                'Bazaar (used goods)' => 'BAZAAR',
            ],
            'defaultValue' => 'BAZAAR',
        ],
        'listingType' => [
            'name' => 'Listing type',
            'type' => 'list',
            'values' => [
                'All' => 'all',
                'Auctions only' => 'auction',
                'Buy now only' => 'buyNow',
            ],
            'defaultValue' => 'all',
        ],
        'sort' => [
            'name' => 'Sort by',
            'type' => 'list',
            'values' => [
                'Relevance' => 'relevance',
                'Newest first' => 'startingTime',
                'Ending soon' => 'endingTime',
            ],
            'defaultValue' => 'startingTime',
        ],
        'limit' => [
            'name' => 'Number of results',
            'type' => 'number',
            'defaultValue' => 20,
            'minimum' => 1,
            'maximum' => 50,
        ],
    ]];
    const CACHE_TIMEOUT = 900;

    public function getIcon(): string
    {
        return ''; //find png image
    }

    public function getURI(): string
    {
        $text = $this->getInput('text');
        if (!$text) {
            return parent::getURI();
        }
        $uri = self::URI . '/vysledky-vyhledavani?text=' . urlencode($text);
        $uri .= '&searchAll=' . ($this->getInput('searchAll') ? 'true' : 'false');
        $subbrand = $this->getInput('subbrand');
        if ($subbrand) {
            $uri .= '&subbrand=' . urlencode($subbrand);
        }
        return $uri;
    }

    public function collectData(): void
    {
        $text = $this->getInput('text');
        if (!$text) {
            throwClientException('Search term is required');
        }

        $filter = [
            'text' => $text,
            'searchAll' => (bool) $this->getInput('searchAll'),
        ];

        $subbrand = $this->getInput('subbrand');
        if ($subbrand) {
            $filter['subbrand'] = $subbrand;
        }

        $listingType = $this->getInput('listingType');
        if ($listingType === 'auction') {
            $filter['auction'] = true;
        } elseif ($listingType === 'buyNow') {
            $filter['buyNowActive'] = true;
        }

        $sort = $this->getInput('sort') ?: 'startingTime';
        $limit = max(1, min(50, (int) ($this->getInput('limit') ?: 20)));

        $url = self::URI . '/backend-web/api/offers/searchItemsCommon?page=0&size=' . $limit . '&sort=' . $sort;

        $json = getContents($url, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Referer: ' . $this->getURI(),
        ], [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => Json::encode($filter),
        ]);

        $data = Json::decode($json);

        foreach ($data['content'] ?? [] as $item) {
            $itemId = $item['itemId'];
            $seoUrl = $item['seoUrl'] ?? 'nabidka';
            $uri = self::URI . '/' . $seoUrl . '-' . $itemId;

            $price = isset($item['price']) ? number_format($item['price']['amount'], 0, ',', ' ') . ' ' . $item['price']['currency'] : '';

            $image = $item['titleImageUrl'] ?? null;
            $seller = $item['seller']['showName'] ?? $item['sellerLogin'] ?? '';
            $location = $item['location'] ?? '';
            $endingTime = $item['endingTime'] ?? null;

            $condition = '';
            foreach ($item['attributes'] ?? [] as $attr) {
                if ($attr['attributeId'] === 48) {
                    $condition = $attr['attributeValue'];
                    break;
                }
            }

            $content = '<p><strong>' . e($item['itemName']) . '</strong></p>';
            if ($image) {
                $content .= '<p><img src="' . e($image) . '" alt="' . e($item['itemName']) . '" /></p>';
            }
            $content .= '<p>Price: ' . e($price) . '</p>';
            if ($condition) {
                $content .= '<p>Condition: ' . e($condition) . '</p>';
            }
            if ($seller) {
                $content .= '<p>Seller: ' . e($seller);
                if ($location) {
                    $content .= ' (' . e($location) . ')';
                }
                $content .= '</p>';
            }
            $flags = [];
            if ($item['auction'] ?? false) {
                $flags[] = 'Auction';
            }
            if ($item['freeShipping'] ?? false) {
                $flags[] = 'Free shipping';
            }
            if ($item['buyersProtectionAvailable'] ?? false) {
                $flags[] = 'Buyer protection';
            }
            if ($flags) {
                $content .= '<p>' . implode(' · ', array_map('e', $flags)) . '</p>';
            }
            if ($endingTime) {
                $content .= '<p>Ends: ' . e($endingTime) . '</p>';
            }

            $this->items[] = [
                'title' => $item['itemName'],
                'uri' => $uri,
                'content' => $content,
                'uid' => (string) $itemId,
                'timestamp' => $item['startingTime'] ?? null,
                'enclosures' => $image ? [$image] : [],
            ];
        }
    }
}