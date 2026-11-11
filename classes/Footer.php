<?php

/**
 * @file plugins/themes/paideia/classes/Footer.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class Footer
 *
 * @brief Pie de página en columnas a partir del "Pie de página" de OJS.
 *
 * El contenido se sigue editando donde siempre (Ajustes → Sitio web →
 * Apariencia → Pie de página). El theme lo reparte en columnas:
 *  - Cada línea horizontal (<hr>) abre una columna nueva.
 *  - Si no hay líneas pero hay dos o más encabezados (h2–h4), cada
 *    encabezado abre una columna.
 *  - Si no, el contenido va en una sola columna.
 * Antes de esas columnas va, opcionalmente, una columna con la identidad de
 * la revista (nombre, ISSN, editorial y enlaces principales).
 */

namespace APP\plugins\themes\paideia\classes;

class Footer
{
    public static function build($templateMgr, $request, bool $withIdentity, int $grid = 3): array
    {
        $context = $request->getContext();
        $identity = $withIdentity && $context ? self::identity($request, $context) : null;
        $columns = self::columns((string) $templateMgr->getTemplateVars('pageFooter'));
        // Con 2 columnas, la identidad y los enlaces van juntos; con 3 o más,
        // los enlaces ocupan su propia columna.
        $separateLinks = $identity && $grid >= 3;
        return [
            'identity' => $identity,
            'separateLinks' => $separateLinks,
            'columns' => $columns,
            'grid' => $grid,
        ];
    }

    /**
     * @return string[] HTML de cada columna
     */
    public static function columns(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }
        $parts = preg_split('/<hr\b[^>]*>/i', $html);
        if (count($parts) < 2 && preg_match_all('/<h[2-4]\b/i', $html) >= 2) {
            $parts = preg_split('/(?=<h[2-4]\b)/i', $html);
        }
        $parts = array_values(array_filter(array_map('trim', $parts), function ($part) {
            // Descarta restos vacíos (<p>&nbsp;</p>, párrafos sin texto).
            return trim(strip_tags(str_replace('&nbsp;', ' ', $part), '<img><iframe><svg>')) !== '';
        }));
        return $parts;
    }

    protected static function identity($request, $context): array
    {
        $links = [
            ['label' => __('about.aboutContext'), 'url' => $request->url(null, 'about')],
            ['label' => __('about.submissions'), 'url' => $request->url(null, 'about', 'submissions')],
            ['label' => __('navigation.archives'), 'url' => $request->url(null, 'issue', 'archive')],
            ['label' => __('about.contact'), 'url' => $request->url(null, 'about', 'contact')],
        ];
        return [
            'name' => $context->getLocalizedName(),
            'abbr' => $context->getLocalizedData('abbreviation'),
            'onlineIssn' => $context->getData('onlineIssn'),
            'printIssn' => $context->getData('printIssn'),
            'publisher' => $context->getData('publisherInstitution'),
            'publisherUrl' => $context->getData('publisherUrl'),
            'links' => $links,
        ];
    }
}
