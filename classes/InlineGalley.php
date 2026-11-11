<?php

/**
 * @file plugins/themes/paideia/classes/InlineGalley.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Portions adapted from:
 *  - plugins/generic/htmlArticleGalley (c) 2014-2025 Simon Fraser University,
 *    (c) 2003-2025 John Willinsky, GNU GPL v3.
 *  - ulsdevteam/inlineHtmlGalley (c) University of Pittsburgh, GNU GPL v2 or later.
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class InlineGalley
 *
 * @brief Inserta la galerada HTML dentro de la página del artículo.
 *
 * En lugar del iframe del visor por defecto, el texto completo queda en el
 * DOM de la página de artículo: lo ven los lectores y los indexadores
 * (Google Scholar lee `citation_fulltext_html_url` y el propio HTML), y el
 * theme puede armar el índice lateral, las figuras ampliables y los tooltips
 * de referencias.
 *
 * Dos modos de estilo:
 *  - 'theme': se descartan los <style>/<link> de la galerada y el texto se
 *    muestra con la tipografía del theme. Es el modo recomendado para HTML
 *    "limpio" generado por un pipeline (JATS → HTML).
 *  - 'galley': se conservan los estilos de la galerada, encapsulados con
 *    @scope para que no afecten al resto de la página. Útil para galeradas
 *    maquetadas a mano que traen su propio diseño.
 */

namespace APP\plugins\themes\paideia\classes;

use APP\core\Application;
use APP\facades\Repo;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PKP\submissionFile\SubmissionFile;
use Throwable;

class InlineGalley
{
    public const HTML_MIMETYPES = ['text/html', 'application/xhtml+xml'];

    /**
     * Buscar la primera galerada HTML local entre las galeradas primarias.
     */
    public static function findHtmlGalley(iterable $galleys)
    {
        foreach ($galleys as $galley) {
            if ($galley->getData('urlRemote')) {
                continue;
            }
            $file = $galley->getFile();
            if ($file && in_array($file->getData('mimetype'), self::HTML_MIMETYPES, true)) {
                return $galley;
            }
        }
        return null;
    }

    /**
     * Procesar la galerada y devolver lo que necesita la plantilla.
     *
     * @return array{html: string, css: string, links: array, toc: array, mode: string}|null
     */
    public static function render($request, $article, $galley, string $mode = 'theme', string $xpath = ''): ?array
    {
        $file = $galley->getFile();
        if (!$file) {
            return null;
        }
        $contents = Compat::readFile($file->getData('path'));
        if ($contents === null || trim($contents) === '') {
            return null;
        }

        try {
            $contents = self::replaceDependentFiles($request, $article, $galley, $contents);
            $contents = self::replaceVariables($request, $article, $contents);
            return self::extract($contents, $mode, $xpath);
        } catch (Throwable $e) {
            error_log('[paideia theme] No se pudo procesar la galerada HTML ' . $galley->getId() . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Reescribir las referencias a archivos dependientes (imágenes, CSS...)
     * a sus URLs de descarga en OJS. Igual que htmlArticleGalley.
     */
    protected static function replaceDependentFiles($request, $article, $galley, string $contents): string
    {
        $submissionFile = $galley->getFile();
        $embeddableFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(Application::ASSOC_TYPE_SUBMISSION_FILE, [$submissionFile->getId()])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_DEPENDENT])
            ->includeDependentFiles()
            ->getMany();

        foreach ($embeddableFiles as $embeddableFile) {
            $params = [];
            if (in_array($embeddableFile->getData('mimetype'), ['text/plain', 'text/css'], true)) {
                $params['inline'] = 'true';
            }
            $fileUrl = $request->url(
                null,
                'article',
                'download',
                [$article->getBestId(), 'version', $galley->getData('publicationId'), $galley->getBestGalleyId(), $embeddableFile->getId(), $embeddableFile->getLocalizedData('name')],
                $params
            );
            $name = (string) $embeddableFile->getLocalizedData('name');
            foreach (array_unique([preg_quote(rawurlencode($name), '/'), preg_quote($name, '/')]) as $pattern) {
                $contents = preg_replace('/(src|href|data)\s*=\s*"([^"]*' . $pattern . ')"/i', '\1="' . $fileUrl . '"', $contents) ?? $contents;
                $contents = preg_replace('/url\(\s*[\'"]?' . $pattern . '[\'"]?\s*\)/i', 'url(' . $fileUrl . ')', $contents) ?? $contents;
            }
        }

        // ojs://article/123, ojs://issue/4, ojs://journal
        $contents = preg_replace_callback(
            '/(<[^<>]*")ojs:\/\/([^"]+)("[^<>]*>)/i',
            function ($m) use ($request) {
                $url = $m[2];
                $anchor = null;
                if (($i = strpos($url, '#')) !== false) {
                    $anchor = substr($url, $i + 1);
                    $url = substr($url, 0, $i);
                }
                $parts = explode('/', $url);
                $type = strtolower($parts[0] ?? '');
                $target = match (true) {
                    $type === 'article' && isset($parts[1]) => $request->url(null, 'article', 'view', [$parts[1]], null, $anchor),
                    $type === 'issue' && isset($parts[1]) => $request->url(null, 'issue', 'view', [$parts[1]], null, $anchor),
                    $type === 'issue' => $request->url(null, 'issue', 'current', null, null, $anchor),
                    default => $request->url(null, null, null, null, null, $anchor),
                };
                return $m[1] . $target . $m[3];
            },
            $contents
        ) ?? $contents;

        return $contents;
    }

    protected static function replaceVariables($request, $article, string $contents): string
    {
        $context = $request->getContext();
        $issue = null;
        try {
            $issue = Repo::issue()->getBySubmissionId($article->getId());
        } catch (Throwable $e) {
        }
        $vars = [
            'issueTitle' => $issue ? $issue->getIssueIdentification() : '',
            'journalTitle' => $context ? $context->getLocalizedName() : '',
            'siteTitle' => $request->getSite()->getLocalizedTitle(),
            'currentUrl' => $request->getRequestUrl(),
        ];
        foreach ($vars as $key => $value) {
            $contents = str_replace('{$' . $key . '}', htmlspecialchars((string) $value), $contents);
        }
        return $contents;
    }

    /**
     * Extraer el cuerpo (o el nodo elegido por XPath), limpiar scripts y
     * atributos de eventos, numerar encabezados y armar el índice.
     */
    public static function extract(string $html, string $mode = 'theme', string $xpath = ''): ?array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        // El prefijo fuerza UTF-8 aunque la galerada no declare charset.
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $domXpath = new DOMXPath($dom);

        // 1. Estilos de la galerada (sólo se usan en modo 'galley').
        $css = '';
        $links = [];
        if ($mode === 'galley') {
            foreach ($domXpath->query('//style') as $style) {
                $css .= $style->textContent . "\n";
            }
            foreach ($domXpath->query('//link[@rel="stylesheet"][@href]') as $link) {
                $href = $link->getAttribute('href');
                if (preg_match('#^https?://#i', $href)) {
                    $links[] = $href;
                }
            }
        }

        // 2. Seguridad: fuera scripts, manejadores de eventos y javascript: URLs.
        //    También contenido embebido/activo (iframe, object, formularios) y
        //    las animaciones SVG que pueden reescribir un href a javascript:.
        $drop = ['script', 'noscript', 'style', 'link', 'meta', 'base', 'title', 'iframe', 'frame', 'frameset',
            'object', 'embed', 'applet', 'portal', 'form', 'input', 'textarea', 'select', 'button',
            'set', 'animate', 'animatemotion', 'animatetransform', 'handler', 'listener'];
        $query = '//*[' . implode(' or ', array_map(fn ($t) => "translate(local-name(),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='{$t}'", $drop)) . ']';
        foreach (iterator_to_array($domXpath->query($query)) as $node) {
            $node->parentNode?->removeChild($node);
        }
        $urlAttrs = ['href', 'src', 'action', 'formaction', 'xlink:href', 'data', 'poster', 'background', 'cite', 'longdesc', 'srcset', 'ping'];
        foreach (iterator_to_array($domXpath->query('//@*')) as $attr) {
            $name = strtolower($attr->nodeName);
            if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'formaction', 'http-equiv'], true)) {
                $attr->ownerElement->removeAttributeNode($attr);
                continue;
            }
            if (in_array($name, $urlAttrs, true) || str_ends_with($name, ':href')) {
                // Los navegadores ignoran espacios y caracteres de control
                // dentro del esquema ("java\tscript:"): se quitan antes de mirar.
                $value = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($attr->nodeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $isDataImage = str_starts_with($value, 'data:image/') && !str_starts_with($value, 'data:image/svg');
                if (preg_match('/^(javascript|vbscript|livescript|mocha):/', $value)
                    || (str_starts_with($value, 'data:') && !($isDataImage && $name === 'src'))) {
                    $attr->ownerElement->removeAttributeNode($attr);
                }
            }
        }

        // 3. Nodos a insertar.
        $nodes = [];
        if ($xpath !== '') {
            $result = @$domXpath->query($xpath);
            if ($result !== false) {
                foreach ($result as $node) {
                    $nodes[] = $node;
                }
            }
        }
        if (!$nodes) {
            $body = $dom->getElementsByTagName('body')->item(0);
            if (!$body) {
                return null;
            }
            foreach ($body->childNodes as $child) {
                $nodes[] = $child;
            }
        }

        // 4. Encabezados: ids estables e índice.
        $toc = [];
        $usedIds = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $headings = (new DOMXPath($dom))->query('descendant-or-self::*[self::h2 or self::h3]', $node);
            foreach ($headings as $heading) {
                $text = trim(preg_replace('/\s+/u', ' ', $heading->textContent));
                if ($text === '') {
                    continue;
                }
                $id = $heading->getAttribute('id');
                if ($id === '') {
                    $id = 'sec-' . self::slug($text);
                }
                $base = $id;
                $n = 2;
                while (isset($usedIds[$id])) {
                    $id = $base . '-' . $n++;
                }
                $usedIds[$id] = true;
                $heading->setAttribute('id', $id);
                $toc[] = ['id' => $id, 'text' => $text, 'level' => (int) substr($heading->nodeName, 1)];
            }
        }

        // Si la galerada no usa h2 (empieza en h3), el índice arranca en nivel 2.
        if ($toc && min(array_column($toc, 'level')) === 3) {
            foreach ($toc as &$item) {
                $item['level'] = 2;
            }
            unset($item);
        }

        // 5. Tablas anchas: envolver para scroll horizontal; imágenes lazy.
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            foreach (iterator_to_array((new DOMXPath($dom))->query('descendant-or-self::table', $node)) as $table) {
                if ($table->parentNode instanceof DOMElement && str_contains($table->parentNode->getAttribute('class'), 'pdt-table-wrap')) {
                    continue;
                }
                $wrap = $dom->createElement('div');
                $wrap->setAttribute('class', 'pdt-table-wrap');
                $wrap->setAttribute('tabindex', '0');
                $table->parentNode->replaceChild($wrap, $table);
                $wrap->appendChild($table);
            }
            foreach ((new DOMXPath($dom))->query('descendant-or-self::img', $node) as $img) {
                if (!$img->hasAttribute('loading')) {
                    $img->setAttribute('loading', 'lazy');
                }
                if (!$img->hasAttribute('decoding')) {
                    $img->setAttribute('decoding', 'async');
                }
            }
        }

        $out = '';
        foreach ($nodes as $node) {
            // Una tabla de primer nivel quedó dentro de su envoltorio: sale éste.
            $parent = $node->parentNode;
            if ($node instanceof DOMElement && $node->nodeName === 'table' && $parent instanceof DOMElement && $parent->getAttribute('class') === 'pdt-table-wrap') {
                $node = $parent;
            }
            $out .= $dom->saveHTML($node);
        }

        return [
            'html' => $out,
            'css' => $mode === 'galley' ? self::scopeCss($css) : '',
            'links' => array_values(array_unique($links)),
            'toc' => $toc,
            'mode' => $mode,
        ];
    }

    /**
     * Encapsular el CSS de la galerada con @scope (.pdt-galley) para que no
     * afecte al resto de la página.
     *
     * Cada selector se reescribe como ":scope <selector>" (y html/body/:root
     * como ":scope"). El :scope explícito suma (0,1,0) de especificidad, lo
     * que hace falta para que la galerada le gane a reglas del theme padre
     * como ".pkp_structure_main h3". @font-face, @keyframes e @import quedan
     * fuera del bloque. Navegadores sin @scope descartan el bloque y el texto
     * se ve con la tipografía del theme, que es un fallback aceptable.
     */
    public static function scopeCss(string $css): string
    {
        if (trim($css) === '') {
            return '';
        }
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $outside = '';
        $inside = self::scopeRules($css, $outside);
        // Evitar que la galerada cierre el <style> antes de tiempo.
        $result = $outside . "@scope (.pdt-galley) {\n" . $inside . "\n}";
        return str_ireplace('</style', '<\/style', $result);
    }

    /**
     * Recorre una lista de reglas CSS y prefija los selectores con :scope.
     * Las at-rules que no pueden ir dentro de @scope se acumulan en $outside.
     */
    protected static function scopeRules(string $css, string &$outside): string
    {
        $out = '';
        $len = strlen($css);
        $i = 0;
        while ($i < $len) {
            // Prelude hasta '{' o ';'
            $j = $i;
            while ($j < $len && $css[$j] !== '{' && $css[$j] !== ';' && $css[$j] !== '}') {
                if ($css[$j] === '"' || $css[$j] === "'") {
                    $q = $css[$j];
                    $j++;
                    while ($j < $len && $css[$j] !== $q) {
                        $j += $css[$j] === '\\' ? 2 : 1;
                    }
                }
                $j++;
            }
            $prelude = trim(substr($css, $i, $j - $i));
            if ($j >= $len) {
                break;
            }
            if ($css[$j] === '}' ) {
                $i = $j + 1;
                continue;
            }
            if ($css[$j] === ';') {
                // @import / @charset / declaración suelta
                if (stripos($prelude, '@import') === 0) {
                    $outside = $prelude . ";\n" . $outside;
                }
                $i = $j + 1;
                continue;
            }
            // Bloque: buscar la llave de cierre equilibrada.
            $depth = 0;
            $k = $j;
            for (; $k < $len; $k++) {
                if ($css[$k] === '{') {
                    $depth++;
                } elseif ($css[$k] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
            $body = substr($css, $j + 1, $k - $j - 1);
            $i = $k + 1;

            if ($prelude === '') {
                continue;
            }
            if ($prelude[0] === '@') {
                if (preg_match('/^@(media|supports|container|layer)\b/i', $prelude)) {
                    $out .= $prelude . " {\n" . self::scopeRules($body, $outside) . "}\n";
                } else {
                    // @font-face, @keyframes, @page...: fuera del @scope, intactas.
                    $outside .= $prelude . ' {' . $body . "}\n";
                }
                continue;
            }
            $selectors = [];
            foreach (preg_split('/,(?![^(]*\))/', $prelude) as $selector) {
                $selector = trim($selector);
                if ($selector === '') {
                    continue;
                }
                $selector = preg_replace('/^(html\s+body|html|body|:root)(?=$|[\s.:#\[>~+])/i', ':scope', $selector);
                $selector = preg_replace('/^:scope\s+(html|body)(?=$|[\s.:#\[>~+])/i', ':scope', $selector);
                if (str_starts_with($selector, ':scope')) {
                    $selectors[] = $selector;
                    continue;
                }
                $selectors[] = ':scope ' . $selector;
                // ".dark h3": la galerada pone la clase en <html>/<body>; acá
                // la lleva el contenedor (paideia.js la sincroniza con el modo
                // oscuro del theme), así que también se acepta en :scope.
                if (preg_match('/^[.\[]/', $selector)) {
                    $selectors[] = ':scope' . $selector;
                }
            }
            $out .= implode(', ', $selectors) . ' {' . $body . "}\n";
        }
        return $out;
    }

    public static function slug(string $text): string
    {
        $text = mb_strtolower($text);
        if (function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text) ?: $text;
        } else {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        }
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim(substr($text, 0, 60), '-') ?: 'section';
    }
}
