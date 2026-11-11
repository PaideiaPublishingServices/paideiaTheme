<?php

/**
 * @file plugins/themes/paideia/classes/ArticleData.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class ArticleData
 *
 * @brief Arma el "view model" de la página de artículo.
 *
 * La plantilla article_details.tpl del theme sólo consume el array que
 * devuelve build(): así no depende de las diferencias de API entre OJS 3.4 y
 * 3.5 (ver Compat) y las plantillas no llaman funciones PHP que Smarty 4.5
 * marca como obsoletas.
 */

namespace APP\plugins\themes\paideia\classes;

use APP\core\Application;
use APP\facades\Repo;
use PKP\decision\Decision;
use Throwable;

class ArticleData
{
    /**
     * @param \APP\submission\Submission $article
     * @param \APP\publication\Publication $publication
     * @param \APP\journal\Journal $context
     */
    public static function build($article, $publication, $context, array $options = []): array
    {
        $currentLocale = self::currentLocale();

        $dates = self::dates($article, $publication);
        $metrics = !empty($options['metrics']) ? self::metrics($article, $context) : null;
        if ($metrics) {
            $metrics['viewsFmt'] = self::formatNumber($metrics['views'], $currentLocale);
            $metrics['downloadsFmt'] = self::formatNumber($metrics['downloads'], $currentLocale);
        }

        return [
            'locale' => $currentLocale,
            'authors' => self::authors($publication),
            'affiliations' => self::$affiliationIndex,
            'abstracts' => self::abstracts($publication, $currentLocale),
            'altTitles' => self::altTitles($publication, $currentLocale),
            'dates' => $dates,
            'iso' => array_map(fn ($d) => $d ? substr((string) $d, 0, 10) : null, $dates),
            'citationsRaw' => trim((string) $publication->getData('citationsRaw')),
            'doi' => self::doi($publication),
            'metrics' => $metrics,
            'versions' => self::versions($article, $publication),
            'url' => self::articleUrl($article),
        ];
    }

    /** Afiliaciones únicas del artículo, en orden de aparición. */
    protected static array $affiliationIndex = [];

    /**
     * Autores con ORCID y referencias numeradas a sus afiliaciones.
     */
    public static function authors($publication): array
    {
        self::$affiliationIndex = [];
        $keys = [];
        $out = [];
        $authors = $publication->getData('authors') ?? [];
        $userGroups = self::userGroupsById($publication);

        foreach ($authors as $author) {
            $refs = [];
            foreach (Compat::affiliations($author) as $affiliation) {
                $key = mb_strtolower(trim($affiliation['name'])) . '|' . ($affiliation['ror'] ?? '');
                if (!isset($keys[$key])) {
                    $keys[$key] = count(self::$affiliationIndex) + 1;
                    self::$affiliationIndex[] = $affiliation + ['n' => $keys[$key]];
                }
                $refs[] = $keys[$key];
            }
            $out[] = [
                'name' => (string) $author->getFullName(),
                'givenName' => (string) $author->getLocalizedGivenName(),
                'familyName' => (string) $author->getLocalizedFamilyName(),
                'orcid' => Compat::orcid($author),
                'affiliationRefs' => $refs,
                'role' => Compat::userGroupTitle($userGroups[$author->getData('userGroupId')] ?? null),
                'primaryContact' => (int) $publication->getData('primaryContactId') === (int) $author->getId(),
                'bio' => (string) $author->getLocalizedData('biography'),
            ];
        }
        return $out;
    }

    /**
     * Resúmenes y palabras clave en todos los idiomas cargados.
     * El idioma de la interfaz va primero.
     */
    public static function abstracts($publication, string $currentLocale): array
    {
        $abstracts = (array) ($publication->getData('abstract') ?? []);
        $out = [];
        $seen = [];
        foreach ($abstracts as $locale => $html) {
            if (trim(strip_tags((string) $html)) === '') {
                continue;
            }
            // Revistas migradas desde OJS 3.3 pueden conservar claves viejas
            // (en_US junto a en) con el mismo texto: se muestra una sola vez.
            $hash = md5(preg_replace('/\s+/u', ' ', trim(strip_tags((string) $html))));
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;
            $out[$locale] = [
                'locale' => $locale,
                'langTag' => str_replace('_', '-', $locale),
                'name' => self::localeName($locale),
                'html' => (string) $html,
                'keywords' => Compat::keywords($publication, $locale),
                'current' => $locale === $currentLocale,
            ];
        }
        uasort($out, fn ($a, $b) => ($b['current'] <=> $a['current']));
        return array_values($out);
    }

    /**
     * Títulos en los otros idiomas (revistas bilingües).
     */
    public static function altTitles($publication, string $currentLocale): array
    {
        $titles = (array) ($publication->getData('title') ?? []);
        $out = [];
        foreach ($titles as $locale => $title) {
            if ($locale === $currentLocale || trim((string) $title) === '') {
                continue;
            }
            $out[] = [
                'locale' => $locale,
                'langTag' => str_replace('_', '-', $locale),
                'title' => (string) $publication->getLocalizedTitle($locale, 'html'),
            ];
        }
        return $out;
    }

    /**
     * Fechas editoriales: recibido, aceptado, publicado y, si corresponde,
     * fecha de la versión actual.
     */
    public static function dates($article, $publication): array
    {
        $dates = [
            'received' => $article->getData('dateSubmitted') ?: null,
            'accepted' => null,
            'published' => null,
            'updated' => null,
        ];
        try {
            $decisions = Repo::decision()->getCollector()
                ->filterBySubmissionIds([$article->getId()])
                ->filterByDecisionTypes([Decision::ACCEPT])
                ->getMany();
            foreach ($decisions as $decision) {
                $date = $decision->getData('dateDecided');
                if ($date && (!$dates['accepted'] || $date > $dates['accepted'])) {
                    $dates['accepted'] = $date;
                }
            }
        } catch (Throwable $e) {
            // Si el repositorio de decisiones falla, simplemente no se muestra.
        }

        $published = $article->getPublishedPublications();
        $first = $published ? reset($published) : null;
        if ($first) {
            $dates['published'] = $first->getData('datePublished');
            if ($first->getId() !== $publication->getId()) {
                $dates['updated'] = $publication->getData('datePublished');
            }
        } else {
            $dates['published'] = $publication->getData('datePublished');
        }
        return $dates;
    }

    /**
     * DOI de la publicación: ['id' => '10.x/y', 'url' => 'https://doi.org/...'] o null.
     */
    public static function doi($publication): ?array
    {
        $doiObject = $publication->getData('doiObject');
        if (!$doiObject) {
            return null;
        }
        return [
            'id' => (string) $doiObject->getData('doi'),
            'url' => (string) $doiObject->getResolvingUrl(),
        ];
    }

    /**
     * Vistas del resumen y descargas de galeradas (estadísticas COUNTER del core).
     */
    public static function metrics($article, $context): ?array
    {
        try {
            $service = Compat::service('publicationStats');
            if (!$service) {
                return null;
            }
            $totals = $service->getTotalsByType((int) $article->getId(), (int) $context->getId(), null, null);
            $downloads = (int) ($totals['pdfViews'] ?? 0) + (int) ($totals['htmlViews'] ?? 0) + (int) ($totals['otherViews'] ?? 0);
            return [
                'views' => (int) ($totals['abstractViews'] ?? 0),
                'downloads' => $downloads,
                'pdf' => (int) ($totals['pdfViews'] ?? 0),
                'html' => (int) ($totals['htmlViews'] ?? 0),
            ];
        } catch (Throwable $e) {
            error_log('[paideia theme] Métricas no disponibles: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Versiones publicadas, de la más nueva a la más vieja.
     */
    public static function versions($article, $publication): array
    {
        $published = $article->getPublishedPublications() ?: [];
        if (count($published) < 2) {
            return [];
        }
        $request = Application::get()->getRequest();
        $currentId = $article->getData('currentPublicationId');
        $out = [];
        foreach (array_reverse($published) as $iPublication) {
            $path = $iPublication->getId() === $currentId
                ? [$article->getBestId()]
                : [$article->getBestId(), 'version', $iPublication->getId()];
            $out[] = [
                'version' => $iPublication->getData('version'),
                'date' => $iPublication->getData('datePublished'),
                'url' => $request->url(null, 'article', 'view', $path),
                'isShown' => $iPublication->getId() === $publication->getId(),
            ];
        }
        return $out;
    }

    public static function articleUrl($article): string
    {
        return Compat::canonicalUrl(Application::get()->getRequest(), 'article', 'view', [$article->getBestId()]);
    }

    public static function formatNumber(int $n, string $locale): string
    {
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $out = $formatter->format($n);
            if ($out !== false) {
                return $out;
            }
        }
        return number_format($n, 0, ',', '.');
    }

    public static function currentLocale(): string
    {
        try {
            return \PKP\facades\Locale::getLocale();
        } catch (Throwable $e) {
            return 'en';
        }
    }

    /**
     * Nombre del idioma. Acepta códigos viejos de OJS 3.3 (en_US, es_ES,
     * pt_BR...) que OJS 3.4/3.5 ya no registra: prueba el código, luego el
     * idioma base y, por último, la extensión intl de PHP.
     */
    public static function localeName(string $locale): string
    {
        $base = strtolower((string) preg_replace('/[_@-].*$/', '', $locale));
        foreach (array_unique([$locale, $base]) as $code) {
            try {
                $metadata = \PKP\facades\Locale::getMetadata($code);
                if ($metadata) {
                    return (string) $metadata->getDisplayName(null, true);
                }
            } catch (Throwable $e) {
            }
        }
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayLanguage($base, $base);
            if ($name !== '' && $name !== $base) {
                return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
            }
        }
        return $locale;
    }

    /**
     * Grupos de usuario de los autores indexados por id (para mostrar roles
     * como "Traductor").
     */
    protected static function userGroupsById($publication): array
    {
        $out = [];
        try {
            $ids = [];
            foreach ($publication->getData('authors') ?? [] as $author) {
                $ids[] = (int) $author->getData('userGroupId');
            }
            foreach (array_unique($ids) as $id) {
                if ($id) {
                    $out[$id] = Repo::userGroup()->get($id);
                }
            }
        } catch (Throwable $e) {
        }
        return $out;
    }
}
