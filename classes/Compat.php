<?php

/**
 * @file plugins/themes/paideia/classes/Compat.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class Compat
 *
 * @brief Capa de compatibilidad entre OJS 3.4 y 3.5.
 *
 * Toda diferencia de API entre versiones se resuelve acá, para que las
 * plantillas del theme reciban arrays planos y no tengan que preguntar en qué
 * versión están. Diferencias cubiertas:
 *
 *  - Servicios: 3.4 usa APP\core\Services (Pimple); 3.5 el contenedor de
 *    Laravel (app()->get()).
 *  - Afiliaciones: 3.4 es un texto localizado (+ rorId opcional); 3.5 son
 *    objetos Affiliation con nombre y ROR.
 *  - ORCID: 3.5 distingue verificado (hasVerifiedOrcid) y valor a mostrar;
 *    3.4 usa orcidAccessToken.
 *  - Palabras clave: 3.4 devuelve strings; 3.5 arrays con 'name'.
 *  - Grupo de usuario del autor: getShowTitle()/getLocalizedName() en 3.4,
 *    propiedad showTitle / getLocalizedData('name') en 3.5.
 */

namespace APP\plugins\themes\paideia\classes;

use Throwable;

class Compat
{
    /**
     * Obtener un servicio del core por nombre ('file', 'publicationStats', ...).
     */
    public static function service(string $name): mixed
    {
        if (function_exists('app')) {
            try {
                $container = app();
                if ($container->bound($name)) {
                    return $container->get($name);
                }
            } catch (Throwable $e) {
                // Sigue con el fallback de 3.4.
            }
        }
        if (class_exists('\APP\core\Services')) {
            return \APP\core\Services::get($name);
        }
        return null;
    }

    /**
     * Afiliaciones del autor como [['name' => string, 'ror' => ?string], ...].
     */
    public static function affiliations($author): array
    {
        $out = [];
        if (method_exists($author, 'getAffiliations')) {
            foreach ($author->getAffiliations() as $affiliation) {
                $name = (string) $affiliation->getLocalizedName();
                $ror = $affiliation->getRor() ?: null;
                if ($name !== '' || $ror) {
                    $out[] = ['name' => $name, 'ror' => $ror];
                }
            }
            return $out;
        }
        $name = (string) $author->getLocalizedData('affiliation');
        $ror = $author->getData('rorId') ?: null;
        if ($name !== '' || $ror) {
            // En 3.4 el campo es libre: algunos editores separan varias
            // afiliaciones con punto y coma.
            foreach (array_filter(array_map('trim', explode(';', $name))) as $i => $part) {
                $out[] = ['name' => $part, 'ror' => $i === 0 ? $ror : null];
            }
        }
        return $out;
    }

    /**
     * ORCID del autor: ['url' => string, 'id' => string, 'verified' => bool] o null.
     */
    public static function orcid($author): ?array
    {
        $orcid = (string) $author->getData('orcid');
        if ($orcid === '') {
            return null;
        }
        if (method_exists($author, 'hasVerifiedOrcid')) {
            $verified = (bool) $author->hasVerifiedOrcid();
            $display = method_exists($author, 'getOrcidDisplayValue') ? (string) $author->getOrcidDisplayValue() : $orcid;
        } else {
            $verified = (bool) $author->getData('orcidAccessToken');
            $display = $orcid;
        }
        $id = preg_replace('#^https?://(www\.)?orcid\.org/#i', '', $orcid);
        return [
            'url' => str_starts_with($orcid, 'http') ? $orcid : 'https://orcid.org/' . $orcid,
            'id' => $id,
            'display' => $display,
            'verified' => $verified,
        ];
    }

    /**
     * Palabras clave localizadas como lista de strings.
     */
    public static function keywords($publication, ?string $locale = null): array
    {
        $keywords = $locale ? ($publication->getData('keywords', $locale) ?? []) : ($publication->getLocalizedData('keywords') ?? []);
        $out = [];
        foreach ((array) $keywords as $keyword) {
            $value = is_array($keyword) ? ($keyword['name'] ?? '') : (string) $keyword;
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return $out;
    }

    /**
     * Rol a mostrar junto al autor (por ejemplo "Traductor"), o null.
     */
    public static function userGroupTitle($userGroup): ?string
    {
        if (!$userGroup) {
            return null;
        }
        if (method_exists($userGroup, 'getShowTitle')) {
            return $userGroup->getShowTitle() ? (string) $userGroup->getLocalizedName() : null;
        }
        $show = $userGroup->showTitle ?? false;
        return $show ? (string) $userGroup->getLocalizedData('name') : null;
    }

    /**
     * Leer el contenido de un archivo del almacenamiento de OJS.
     */
    public static function readFile(string $path): ?string
    {
        try {
            $fileService = self::service('file');
            return $fileService ? $fileService->fs->read($path) : null;
        } catch (Throwable $e) {
            error_log('[paideia theme] No se pudo leer ' . $path . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * URL de página sin el segmento de idioma que agrega OJS 3.5
     * (/revista/es/article/...). Se usa para la canónica: una sola URL por
     * artículo, igual que la que declara el plugin Google Scholar.
     */
    public static function canonicalUrl($request, string $page, ?string $op = null, ?array $path = null): string
    {
        if (version_compare(self::appVersion(), '3.5', '>=')) {
            try {
                return $request->getDispatcher()->url(
                    $request,
                    \PKP\core\PKPApplication::ROUTE_PAGE,
                    null,
                    $page,
                    $op,
                    $path,
                    ...['urlLocaleForPage' => '']
                );
            } catch (Throwable $e) {
                // Sigue con la URL normal.
            }
        }
        return $request->url(null, $page, $op, $path);
    }

    /**
     * Versión mayor.menor de la aplicación, por ejemplo "3.5".
     */
    public static function appVersion(): string
    {
        try {
            $version = \APP\core\Application::get()->getCurrentVersion()->getVersionString(false);
            return implode('.', array_slice(explode('.', $version), 0, 2));
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Avisos normalizados. 3.4: DataObject (getLocalizedTitle, getDatePosted).
     * 3.5: modelo Eloquent (getLocalizedData, ->datePosted Carbon).
     *
     * @param iterable|null $announcements Si es null, se consultan los últimos activos.
     */
    public static function announcements($context, $announcements = null, int $limit = 3): array
    {
        try {
            if ($announcements === null) {
                if (!$context || !$context->getData('enableAnnouncements')) {
                    return [];
                }
                if (class_exists('\PKP\announcement\Announcement') && method_exists('\PKP\announcement\Announcement', 'scopeWithActiveByDate')) {
                    $announcements = \PKP\announcement\Announcement::withActiveByDate()
                        ->withContextIds([$context->getId()])
                        ->orderBy(\PKP\announcement\Announcement::CREATED_AT, 'desc')
                        ->limit($limit)
                        ->get();
                } else {
                    $announcements = \APP\facades\Repo::announcement()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByActive()
                        ->limit($limit)
                        ->getMany();
                }
            }
            $request = \APP\core\Application::get()->getRequest();
            $items = [];
            foreach ($announcements as $a) {
                if (count($items) >= $limit) {
                    break;
                }
                $id = method_exists($a, 'getId') ? $a->getId() : $a->id;
                $title = method_exists($a, 'getLocalizedTitle') ? $a->getLocalizedTitle() : $a->getLocalizedData('title');
                $summary = method_exists($a, 'getLocalizedDescriptionShort') ? $a->getLocalizedDescriptionShort() : $a->getLocalizedData('descriptionShort');
                $date = method_exists($a, 'getDatePosted') ? $a->getDatePosted() : ($a->datePosted ? $a->datePosted->format('Y-m-d') : null);
                $items[] = [
                    'id' => (int) $id,
                    'title' => (string) $title,
                    'summary' => (string) $summary,
                    'date' => $date ? substr((string) $date, 0, 10) : null,
                    'url' => $request->url(null, 'announcement', 'view', [$id]),
                ];
            }
            return $items;
        } catch (Throwable $e) {
            error_log('[paideia theme] Avisos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Idiomas de la revista para el selector del menú.
     *
     * @return array<int, array{code: string, short: string, name: string, url: string, current: bool}>
     */
    public static function languages($request): array
    {
        try {
            $context = $request->getContext();
            $supported = $context ? $context->getSupportedLocales() : $request->getSite()->getSupportedLocales();
            if (count($supported) < 2) {
                return [];
            }
            $names = \PKP\facades\Locale::getFormattedDisplayNames($supported, \PKP\facades\Locale::getLocales(), \PKP\i18n\LocaleMetadata::LANGUAGE_LOCALE_ONLY);
            $current = \PKP\facades\Locale::getLocale();
            // 3.5 vuelve a la página con la URL completa; 3.4 con la ruta.
            $source = version_compare(self::appVersion(), '3.5', '>=')
                ? ($_SERVER['SERVER_NAME'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')
                : ($_SERVER['REQUEST_URI'] ?? '');
            $items = [];
            foreach ($names as $code => $name) {
                $items[] = [
                    'code' => (string) $code,
                    'short' => strtoupper(substr((string) $code, 0, 2)),
                    'name' => (string) $name,
                    'url' => $request->url(null, 'user', 'setLocale', [$code], ['source' => $source]),
                    'current' => $code === $current,
                ];
            }
            return $items;
        } catch (Throwable $e) {
            return [];
        }
    }
}
