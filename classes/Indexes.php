<?php

/**
 * @file plugins/themes/paideia/classes/Indexes.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class Indexes
 *
 * @brief Catálogo de índices y bases de datos reconocidos para el bloque
 *  "Indexada en". Cada índice se activa desde las opciones del theme, con un
 *  enlace opcional a la ficha de la revista.
 *
 *  Los logotipos son marcas de sus dueños y no se distribuyen con el theme
 *  (no pueden licenciarse como GPL). Por defecto cada índice se muestra como
 *  una ficha tipográfica; si existe images/indexes/{clave}.svg (o .png) en el
 *  theme, se usa esa imagen.
 */

namespace APP\plugins\themes\paideia\classes;

class Indexes
{
    /**
     * clave => [nombre, subtítulo]
     */
    public const CATALOG = [
        'scopus' => ['Scopus', ''],
        'wos' => ['Web of Science', ''],
        'doaj' => ['DOAJ', ''],
        'scielo' => ['SciELO', ''],
        'redalyc' => ['Redalyc', ''],
        'latindexCatalogo' => ['Latindex', 'Catálogo 2.0'],
        'latindexDirectorio' => ['Latindex', 'Directorio'],
        'pubmed' => ['PubMed', ''],
        'lilacs' => ['LILACS', ''],
        'dialnet' => ['Dialnet', ''],
        'redib' => ['REDIB', ''],
        'erihplus' => ['ERIH PLUS', ''],
        'ebsco' => ['EBSCO', ''],
        'miar' => ['MIAR', ''],
        'biblat' => ['Biblat', ''],
    ];

    public static function enabledOption(string $key): string
    {
        return 'pdtIdx_' . $key;
    }

    public static function urlOption(string $key): string
    {
        return 'pdtIdxUrl_' . $key;
    }

    /**
     * Índices activos, en el orden del catálogo.
     *
     * @return array<int, array{key: string, name: string, sub: string, url: string, img: string}>
     */
    public static function active($plugin, string $baseUrl): array
    {
        $items = [];
        foreach (self::CATALOG as $key => [$name, $sub]) {
            if (!$plugin->getOption(self::enabledOption($key))) {
                continue;
            }
            $url = trim((string) $plugin->getOption(self::urlOption($key)));
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
            $img = '';
            foreach (['svg', 'png', 'webp'] as $ext) {
                if (is_file($plugin->getPluginPath() . "/images/indexes/{$key}.{$ext}")) {
                    $img = $baseUrl . '/' . $plugin->getPluginPath() . "/images/indexes/{$key}.{$ext}";
                    break;
                }
            }
            $items[] = ['key' => $key, 'name' => $name, 'sub' => $sub, 'url' => $url, 'img' => $img];
        }
        return $items;
    }
}
