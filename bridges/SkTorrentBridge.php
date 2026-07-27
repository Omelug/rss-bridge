<?php

declare(strict_types=1);

class SkTorrentBridge extends BridgeAbstract
{
    const NAME = 'SkTorrent';
    const URI = 'https://sktorrent.eu/torrent';
    const DESCRIPTION = 'Torrenty z sktorrent.eu podle kategorie a hledaného výrazu';
    const MAINTAINER = 'Omelug';
    const PARAMETERS = [[
        'search' => [
            'name' => 'Hledaný výraz',
            'type' => 'text',
            'required' => false,
            'defaultValue' => '',
        ],
        'category' => [
            'name' => 'Kategorie',
            'type' => 'list',
            'values' => [
                'Vše' => '0',
                'Filmy CZ/SK dabing' => '1',
                'Filmy Kreslené' => '5',
                'Filmy Kamera' => '14',
                'Filmy s titulkama' => '15',
                'Filmy DVD' => '20',
                'Filmy bez titulků' => '31',
                '3D Filmy' => '3',
                'HD Filmy' => '19',
                'Blu-ray Filmy' => '28',
                '3D Blu-ray Filmy' => '29',
                'UHD Filmy' => '43',
                'Seriál' => '16',
                'Dokument' => '17',
                'TV Pořad' => '42',
                'Sport' => '44',
                'Hudba' => '2',
                'Hudba DJ\'s Mix' => '22',
                'Mluvené slovo' => '24',
                'Hudební videa' => '26',
                'Soundtrack' => '45',
                'Hry na Windows' => '18',
                'Hry na Konzole' => '30',
                'Hry na Linux' => '37',
                'Hry na Mac' => '59',
                'Programy' => '21',
                'Mobil, PDA' => '27',
                'Knihy a Časopisy' => '23',
                'Ostatní' => '25',
                'xXx' => '9',
            ],
            'defaultValue' => '0',
        ],
        'jazyk' => [
            'name' => 'Jazyk',
            'type' => 'list',
            'values' => [
                'Vše' => '',
                'Československy' => 'czsk',
                'Slovensky' => 'sk',
                'Česky' => 'cz',
                'Anglicky' => 'eng',
            ],
            'defaultValue' => '',
        ],
    ]];
    const CACHE_TIMEOUT = 900;

    public function getURI(): string
    {
        return self::URI . '/torrents_v2.php?' . http_build_query([
            'search' => $this->getInput('search') ?? '',
            'category' => $this->getInput('category') ?? '0',
            'jazyk' => $this->getInput('jazyk') ?? '',
            'active' => '0',
        ]);
    }

    public function collectData(): void
    {
        $html = getSimpleHTMLDOM($this->getURI());

        foreach ($html->find('TD[align=center][valign=top]') as $td) {
            $linkEl = $td->find('A[href*="details.php"]', 0);
            if (!$linkEl) {
                continue;
            }

            $title = trim($linkEl->plaintext);
            if (!$title) {
                continue;
            }

            $href = $linkEl->href;
            $uri = self::URI . '/' . ltrim($href, '/');

            $imgEl = $linkEl->find('img', 0);
            $image = $imgEl ? ($imgEl->{'data-src'} ?: $imgEl->src) : null;

            // "Velkost 35.0 MB | Pridany 27/07/2026"
            $text = $td->plaintext;
            $size = null;
            $timestamp = null;
            if (preg_match('/Velkost\s+([\d.,]+\s*\w+)\s*\|\s*Pridany\s+(\d{2}\/\d{2}\/\d{4})/u', $text, $m)) {
                $size = trim($m[1]);
                $dt = DateTime::createFromFormat('d/m/Y', $m[2]);
                $timestamp = $dt ? $dt->getTimestamp() : null;
            }

            $seeds = null;
            $leechers = null;
            if (preg_match('/Odosielaju\s*:\s*(\d+)/u', $text, $m)) {
                $seeds = (int) $m[1];
            }
            if (preg_match('/Stahuju\s*:\s*(\d+)/u', $text, $m)) {
                $leechers = (int) $m[1];
            }

            $content = '';
            if ($image) {
                $content .= '<p><img src="' . e($image) . '" alt="' . e($title) . '" /></p>';
            }
            if ($size) {
                $content .= '<p>Velikost: ' . e($size) . '</p>';
            }
            if ($seeds !== null) {
                $content .= '<p>Seeduje: ' . $seeds . ' | Stahuje: ' . $leechers . '</p>';
            }

            $titleFull = ($seeds !== null ? "[S:$seeds] " : '') . $title;

            $this->items[] = [
                'title' => $titleFull,
                'uri' => $uri,
                'content' => $content,
                'timestamp' => $timestamp,
                'uid' => $uri,
                'enclosures' => $image ? [$image] : [],
            ];
        }
    }
}
