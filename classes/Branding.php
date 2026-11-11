<?php

/**
 * @file plugins/themes/paideia/classes/Branding.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class Branding
 *
 * @brief Crédito de Paideia en el pie de página.
 *
 * Este theme es software libre (GPL v3). Como condición adicional permitida
 * por la sección 7(b) de la GPL v3, quien distribuya o use una versión
 * modificada debe conservar visible el crédito del pie de página (ver
 * LICENSE). El código acompaña esa condición de forma razonable:
 *
 *  1. El crédito se inyecta desde acá, no desde una plantilla, y el mismo
 *     paso agrega al <body> la clase `pdt` de la que depende toda la hoja de
 *     estilo del theme. Quitar la inyección deja el sitio con el aspecto del
 *     theme Default.
 *  2. js/paideia.js comprueba que el crédito exista, sea visible y enlace a
 *     Paideia. Si alguien lo oculta (por ejemplo con CSS personalizado), el
 *     theme se desactiva en el navegador y vuelve al aspecto del Default.
 *
 * No es un DRM ni pretende serlo: es la forma de que el crédito sea
 * "no posible de quitar fácilmente" sin perjudicar a lectores ni editores.
 */

namespace APP\plugins\themes\paideia\classes;

class Branding
{
    public const CREDIT_URL = 'https://paideiastudio.net/';
    public const CREDIT_NAME = 'Paideia Studio';

    /**
     * Filtro de salida de Smarty: marca el <body> e inserta el crédito.
     */
    public static function outputFilter(string $output, $template = null): string
    {
        if (stripos($output, '</body>') === false || str_contains($output, 'data-pdt-credit')) {
            return $output;
        }

        // 1. Clase raíz de la hoja de estilo.
        $count = 0;
        $output = preg_replace('/<body([^>]*?)\bclass="([^"]*)"/i', '<body$1class="$2 pdt"', $output, 1, $count);
        if (!$count) {
            $output = preg_replace('/<body\b/i', '<body class="pdt"', $output, 1);
        }

        // 2. Crédito: junto al logo de OJS/PKP si está, si no antes de </body>.
        $credit = self::markup();
        $count = 0;
        $output = preg_replace('/(<div class="pkp_brand_footer"[^>]*>)/i', $credit . '$1', $output, 1, $count);
        if (!$count) {
            $output = preg_replace('/<\/body>/i', '<footer class="pdt-credit-wrap">' . $credit . '</footer></body>', $output, 1);
        }

        return $output;
    }

    /** Isotipo de Paideia Studio (trazado del logo oficial). */
    public const MARK_PATH = 'M58.7 0.2 60.0 0.2 60.2 0.9 44.6 18.9 34.3 29.7 28.6 34.5 22.5 38.5 16.5 40.6 11.6 40.6 7.3 38.9 4.1 35.9 3.6 35.0 3.8 34.3 8.2 28.8 13.2 23.8 19.4 18.7 27.9 12.9 38.5 7.1 49.2 2.8 58.7 0.2ZM63.4 4.5 64.5 4.3 66.4 5.7 74.3 13.2 79.9 20.1 85.2 28.1 90.5 38.1 95.3 50.1 97.4 57.7 97.9 61.0 97.2 61.6 94.9 59.3 77.1 43.4 70.1 36.4 64.3 29.6 61.6 25.5 58.8 19.4 58.0 15.8 58.0 13.7 58.6 11.1 59.9 8.3 63.4 4.5ZM0.2 38.5 0.7 38.4 21.9 59.3 29.3 67.8 32.7 72.6 36.5 79.7 37.9 85.6 37.7 88.4 37.2 90.5 35.0 94.2 31.9 97.0 30.5 96.6 24.0 90.5 19.6 85.3 15.5 79.4 11.4 72.6 7.9 65.5 4.4 57.0 1.2 46.6 -0.1 39.2 0.2 38.5ZM80.5 59.3 84.2 59.2 87.0 59.9 89.8 61.4 92.8 64.3 92.9 65.7 88.5 71.2 83.5 76.2 79.2 79.9 69.5 86.6 58.2 92.9 46.6 97.5 38.3 99.7 36.9 99.9 36.4 99.3 52.4 80.9 62.4 70.3 68.1 65.5 74.7 61.3 80.5 59.3Z';

    public static function markup(): string
    {
        $by = __('plugins.themes.paideia.creditBy');
        if ($by === '' || str_starts_with($by, '##')) {
            $by = 'Theme by';
        }
        $url = self::CREDIT_URL . '?utm_source=ojs&utm_medium=theme-footer&utm_campaign=paideia-theme';
        // El nombre no sale de la traducción: editar el archivo de idioma no
        // quita el crédito.
        return '<p class="pdt-credit" data-pdt-credit>'
            . '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" rel="noopener" target="_blank" title="' . self::CREDIT_NAME . '">'
            . '<svg class="pdt-credit__logo" viewBox="0 0 98 100" width="22" height="22" aria-hidden="true" focusable="false">'
            . '<path fill="#7FBA34" d="' . self::MARK_PATH . '"/></svg>'
            . '<span class="pdt-credit__text"><span class="pdt-credit__by">' . htmlspecialchars($by, ENT_QUOTES) . '</span> '
            . '<strong class="pdt-credit__name">' . self::CREDIT_NAME . '</strong></span></a></p>';
    }

    /**
     * Script mínimo que fija el esquema de color antes del primer pintado.
     */
    public static function colorSchemeBootstrap(bool $darkModeEnabled): string
    {
        if (!$darkModeEnabled) {
            return '';
        }
        return "(function(){var d=document.documentElement;d.setAttribute('data-pdt-dm','');"
            . "try{var s=localStorage.getItem('pdt-scheme');"
            . "if(s==='dark'||s==='light'){d.setAttribute('data-pdt-scheme',s);}}catch(e){}})();";
    }
}
