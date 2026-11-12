<?php

/**
 * @file plugins/themes/paideia/classes/HomeData.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class HomeData
 *
 * @brief Datos de la página de inicio de la revista.
 *
 *  - Pestañas "Más recientes", "Más vistos" y "Más descargados" a partir de
 *    las estadísticas COUNTER del core (últimos 12 meses).
 *  - Buscador de números por año.
 *
 * Las consultas de estadísticas pueden ser pesadas en revistas grandes, así
 * que el resultado se guarda en un archivo de caché durante unas horas.
 */

namespace APP\plugins\themes\paideia\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\issue\Collector as IssueCollector;
use APP\submission\Submission;
use PKP\submission\Collector as SubmissionCollector;
use Throwable;

class HomeData
{
    public const LIMIT = 6;
    public const CACHE_TTL = 6 * 3600;

    public static function build($context, array $options): array
    {
        self::removeLegacyCache();
        $locale = ArticleData::currentLocale();
        $out = [
            'layout' => $options['layout'] ?? 'issue',
            'tabs' => [],
            'issuesByYear' => self::issuesByYear($context),
        ];

        // Publicación continua: el número actual es el número "en curso"
        // (abierto, recibe artículos hasta que se publica el siguiente) y el
        // anterior es el último número cerrado. Los artículos del número en
        // curso reemplazan a la pestaña "Más recientes".
        $tabs = [];
        if ($out['layout'] === 'continuous') {
            $out['open'] = self::openIssue($context);
            $out['closed'] = self::closedIssue($context, $out['open']['id'] ?? 0);
        }
        if ($out['layout'] !== 'continuous' || empty($out['open'])) {
            $tabs['recent'] = fn () => self::recentIds($context);
        }
        if (!empty($options['tabs'])) {
            $tabs['viewed'] = fn () => self::topIds($context, Application::ASSOC_TYPE_SUBMISSION);
            $tabs['downloaded'] = fn () => self::topIds($context, Application::ASSOC_TYPE_SUBMISSION_FILE);
        }

        // En caché por idioma y por huella del contenido publicado: cualquier
        // alta, baja o edición de un artículo publicado invalida la caché.
        $key = 'home.' . (int) $context->getId() . '.' . Compat::cacheLocale($locale) . '.' . Compat::contentFingerprint((int) $context->getId()) . '.' . implode('-', array_keys($tabs));
        $out['tabs'] = Compat::remember($key, self::CACHE_TTL, function () use ($tabs, $context) {
            $result = [];
            foreach ($tabs as $tab => $loader) {
                try {
                    $result[$tab] = self::cards($loader(), $context);
                } catch (Throwable $e) {
                    error_log('[paideia theme] Pestaña ' . $tab . ': ' . $e->getMessage());
                    $result[$tab] = [];
                }
            }
            // Las pestañas sin artículos (por ejemplo, sin estadísticas aún) no se muestran.
            return array_filter($result, fn ($cards, $tab) => $tab === 'recent' || count($cards), ARRAY_FILTER_USE_BOTH);
        });

        return $out;
    }

    public const OPEN_LIMIT = 12;

    /**
     * Número en curso (el número actual de OJS) con sus artículos, del más
     * nuevo al más antiguo. Las tarjetas se guardan en caché según la lista
     * de artículos: un artículo nuevo se ve en el acto.
     */
    protected static function openIssue($context): array
    {
        try {
            $issue = Repo::issue()->getCurrent($context->getId());
            if (!$issue) {
                return [];
            }
            $ids = Repo::submission()->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByIssueIds([$issue->getId()])
                ->filterByStatus([Submission::STATUS_PUBLISHED])
                ->getIds()
                ->all();
            $key = 'home-open.' . (int) $context->getId() . '.' . Compat::cacheLocale(ArticleData::currentLocale()) . '.' . (int) $issue->getId() . '.' . Compat::contentFingerprint((int) $context->getId());
            $cards = Compat::remember($key, self::CACHE_TTL, function () use ($ids, $context) {
                $cards = self::cards(array_map(fn ($id) => ['id' => (int) $id, 'metric' => null], $ids), $context);
                usort($cards, fn ($a, $b) => strcmp((string) $b['datePublished'], (string) $a['datePublished']));
                return $cards;
            });
            return [
                'id' => $issue->getId(),
                'label' => strip_tags((string) $issue->getIssueIdentification()),
                'url' => Application::get()->getRequest()->url(null, 'issue', 'view', [$issue->getBestIssueId()]),
                'count' => count($cards),
                'cards' => array_slice($cards, 0, self::OPEN_LIMIT),
            ];
        } catch (Throwable $e) {
            error_log('[paideia theme] Número en curso: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Último número cerrado: el publicado más reciente que no es el actual.
     */
    protected static function closedIssue($context, int $openId): array
    {
        try {
            $best = null;
            $issues = Repo::issue()->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByPublished(true)
                ->getMany();
            foreach ($issues as $issue) {
                if ((int) $issue->getId() === $openId) {
                    continue;
                }
                if (!$best || (string) $issue->getDatePublished() > (string) $best->getDatePublished()) {
                    $best = $issue;
                }
            }
            if (!$best) {
                return [];
            }
            return [
                'label' => strip_tags((string) $best->getIssueIdentification()),
                'url' => Application::get()->getRequest()->url(null, 'issue', 'view', [$best->getBestIssueId()]),
                'cover' => (string) $best->getLocalizedCoverImageUrl(),
                'date' => (string) $best->getDatePublished(),
            ];
        } catch (Throwable $e) {
            error_log('[paideia theme] Número cerrado: ' . $e->getMessage());
            return [];
        }
    }

    protected static function recentIds($context): array
    {
        $ids = [];
        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->orderBy(SubmissionCollector::ORDERBY_DATE_PUBLISHED, SubmissionCollector::ORDER_DIR_DESC)
            ->limit(self::LIMIT)
            ->getMany();
        foreach ($submissions as $submission) {
            $ids[] = ['id' => $submission->getId(), 'metric' => null];
        }
        return $ids;
    }

    protected static function topIds($context, int $assocType): array
    {
        $service = Compat::service('publicationStats');
        if (!$service) {
            return [];
        }
        $rows = $service->getTotals([
            'contextIds' => [$context->getId()],
            'assocTypes' => [$assocType],
            'dateStart' => date('Y-m-d', strtotime('-12 months')),
            'dateEnd' => date('Y-m-d', strtotime('yesterday')),
            'count' => self::LIMIT,
            'offset' => 0,
        ]);
        $ids = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            if (!empty($row['submission_id'])) {
                $ids[] = ['id' => (int) $row['submission_id'], 'metric' => (int) ($row['metric'] ?? 0)];
            }
        }
        return array_slice($ids, 0, self::LIMIT);
    }

    /**
     * Datos planos de cada artículo para las tarjetas.
     */
    protected static function cards(array $ids, $context): array
    {
        $request = Application::get()->getRequest();
        $cards = [];
        $sections = [];
        foreach ($ids as $item) {
            $submission = Repo::submission()->get($item['id']);
            if (!$submission || (int) $submission->getData('contextId') !== (int) $context->getId()
                || (int) $submission->getData('status') !== Submission::STATUS_PUBLISHED) {
                continue;
            }
            $publication = $submission->getCurrentPublication();
            $sectionId = (int) $publication->getData('sectionId');
            if (!array_key_exists($sectionId, $sections)) {
                $section = Repo::section()->get($sectionId);
                $sections[$sectionId] = $section ? $section->getLocalizedTitle() : '';
            }
            $doi = ArticleData::doi($publication);
            $pdfUrl = null;
            foreach ($publication->getData('galleys') ?? [] as $galley) {
                if ($galley->isPdfGalley()) {
                    $pdfUrl = $request->url(null, 'article', 'view', [$submission->getBestId(), $galley->getBestGalleyId()]);
                    break;
                }
            }
            $cards[] = [
                'id' => $submission->getId(),
                'url' => $request->url(null, 'article', 'view', [$submission->getBestId()]),
                'title' => (string) $publication->getLocalizedTitle(null, 'html'),
                'authors' => (string) $publication->getShortAuthorString(),
                'section' => $sections[$sectionId],
                'datePublished' => $publication->getData('datePublished'),
                'doi' => $doi,
                'pdfUrl' => $pdfUrl,
                'metric' => $item['metric'],
            ];
        }
        return $cards;
    }

    /**
     * Números publicados agrupados por año, para el buscador del inicio.
     */
    protected static function issuesByYear($context): array
    {
        $request = Application::get()->getRequest();
        $out = [];
        try {
            $issues = Repo::issue()->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByPublished(true)
                ->orderBy(IssueCollector::ORDERBY_PUBLISHED_ISSUES)
                ->getMany();
            foreach ($issues as $issue) {
                $year = $issue->getYear() ?: substr((string) $issue->getDatePublished(), 0, 4);
                $out[$year][] = [
                    'label' => strip_tags((string) $issue->getIssueIdentification()),
                    'url' => $request->url(null, 'issue', 'view', [$issue->getBestIssueId()]),
                ];
            }
        } catch (Throwable $e) {
            error_log('[paideia theme] Números: ' . $e->getMessage());
        }
        krsort($out);
        return $out;
    }

    public static function periodicalJsonLd($request, $context): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Periodical',
            'name' => $context->getLocalizedName(),
            'url' => $request->url(null, 'index'),
        ];
        $issn = array_values(array_filter([$context->getData('onlineIssn'), $context->getData('printIssn')]));
        if ($issn) {
            $node['issn'] = count($issn) === 1 ? $issn[0] : $issn;
        }
        if ($context->getData('publisherInstitution')) {
            $node['publisher'] = ['@type' => 'Organization', 'name' => $context->getData('publisherInstitution')];
        }
        $description = Seo::excerpt((string) $context->getLocalizedData('description'), 300);
        if ($description) {
            $node['description'] = $description;
        }
        return $node;
    }

    /**
     * Hasta 1.0.3 la caché eran archivos cache/pdt-*.json que no se borraban
     * con "Borrar caché de datos". Se eliminan una vez.
     */
    protected static function removeLegacyCache(): void
    {
        if (Compat::cacheGet('legacy-cache-removed')) {
            return;
        }
        foreach (glob(\PKP\core\Core::getBaseDir() . '/cache/pdt-*.json') ?: [] as $file) {
            @unlink($file);
        }
        Compat::cachePut('legacy-cache-removed', 1, 30 * 24 * 3600);
    }
}
