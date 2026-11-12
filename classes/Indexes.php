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
 *  una ficha tipográfica; si el editor sube el logo desde las opciones del
 *  tema, se muestra el logo dentro de una ficha de tamaño fijo.
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

    public static function logoOption(string $key): string
    {
        return 'pdtIdxLogo_' . $key;
    }

    /** Formatos aceptados para los logos (sin SVG: puede llevar scripts). */
    public const LOGO_TYPES = ['image/png' => '.png', 'image/jpeg' => '.jpg', 'image/webp' => '.webp', 'image/gif' => '.gif'];

    /** Tamaño máximo del logo, en MB. */
    public const LOGO_MAX_MB = 1;

    /**
     * Carpeta pública de la revista, relativa a la raíz de OJS.
     */
    public static function publicPath(int $contextId): string
    {
        return (new \APP\file\PublicFileManager())->getContextFilesPath($contextId);
    }

    /**
     * Índices activos, en el orden del catálogo.
     *
     * @return array<int, array{key: string, name: string, sub: string, url: string, img: string}>
     */
    public static function active($plugin, string $baseUrl): array
    {
        $items = [];
        $context = \APP\core\Application::get()->getRequest()->getContext();
        $contextId = $context ? (int) $context->getId() : 0;
        foreach (self::CATALOG as $key => [$name, $sub]) {
            if (!$plugin->getOption(self::enabledOption($key))) {
                continue;
            }
            $url = trim((string) $plugin->getOption(self::urlOption($key)));
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
            $img = '';
            $alt = '';
            $logo = $plugin->getOption(self::logoOption($key));
            if (is_array($logo) && !empty($logo['uploadName']) && $contextId) {
                $img = $baseUrl . '/' . self::publicPath($contextId) . '/' . rawurlencode((string) $logo['uploadName']);
                $alt = trim((string) ($logo['altText'] ?? ''));
            }
            $items[] = ['key' => $key, 'name' => $name, 'sub' => $sub, 'url' => $url, 'img' => $img, 'alt' => $alt !== '' ? $alt : trim($name . ' ' . $sub)];
        }
        return $items;
    }
}
