<?php

/**
 * @file plugins/themes/paideia/classes/ArticleExtras.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class ArticleExtras
 *
 * @brief Contenido del panel lateral que no repite datos del artículo:
 *  - Artículos relacionados: palabras clave compartidas (una consulta SQL,
 *    con caché), sólo de la misma revista.
 *  - Citado por: OpenAlex (API abierta, sin clave). La consulta se hace desde
 *    el servidor, con el DOI como único dato enviado, y se guarda en caché
 *    24 h (6 h si falla), para no frenar la página ni exponer a los lectores.
 */

namespace APP\plugins\themes\paideia\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use Throwable;

class ArticleExtras
{
    public const RELATED_COUNT = 4;
    public const CITED_BY_COUNT = 5;
    public const CACHE_TTL = 24 * 3600;
    public const CACHE_TTL_ERROR = 6 * 3600;

    /**
     * Artículos relacionados por palabras clave compartidas.
     *
     * No usa el índice de búsqueda (el método del plugin "Recommend by
     * similarity" es lento en revistas grandes): una sola consulta cuenta,
     * para cada artículo publicado de la revista, cuántas palabras clave
     * comparte con éste. Si no hay coincidencias, usa los últimos artículos
     * de la misma sección. Resultado en caché por 24 h.
     *
     * @return array<int, array{url: string, title: string, authors: string, year: string}>
     */
    public static function related($article, $publication, $context): array
    {
        $locale = \PKP\facades\Locale::getLocale();
        $file = Core::getBaseDir() . '/cache/pdt-related-' . (int) $article->getId() . '-' . preg_replace('/[^a-zA-Z_@]/', '', $locale) . '.json';
        if (is_readable($file) && filemtime($file) >= time() - self::CACHE_TTL) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $ids = [];
        try {
            $keywords = [];
            foreach ((array) $publication->getData('keywords') as $list) {
                foreach ((array) $list as $kw) {
                    $kw = is_array($kw) ? ($kw['name'] ?? '') : $kw;
                    $kw = mb_strtolower(trim((string) $kw));
                    if ($kw !== '') {
                        $keywords[$kw] = true;
                    }
                }
            }
            $keywords = array_slice(array_keys($keywords), 0, 30);
            if ($keywords) {
                $ids = DB::table('controlled_vocab_entry_settings as cves')
                    ->join('controlled_vocab_entries as cve', 'cve.controlled_vocab_entry_id', '=', 'cves.controlled_vocab_entry_id')
                    ->join('controlled_vocabs as cv', 'cv.controlled_vocab_id', '=', 'cve.controlled_vocab_id')
                    ->join('submissions as s', 's.current_publication_id', '=', 'cv.assoc_id')
                    ->join('publications as p', 'p.publication_id', '=', 's.current_publication_id')
                    ->where('cv.symbolic', '=', 'submissionKeyword')
                    ->where('cv.assoc_type', '=', Application::ASSOC_TYPE_PUBLICATION)
                    ->where('cves.setting_name', '=', 'name')
                    ->where('s.context_id', '=', (int) $context->getId())
                    ->where('s.submission_id', '!=', (int) $article->getId())
                    ->where('s.status', '=', Submission::STATUS_PUBLISHED)
                    ->whereIn(DB::raw('LOWER(cves.setting_value)'), $keywords)
                    ->groupBy('s.submission_id', 'p.date_published')
                    ->orderByRaw('COUNT(DISTINCT LOWER(cves.setting_value)) DESC')
                    ->orderBy('p.date_published', 'desc')
                    ->limit(self::RELATED_COUNT)
                    ->pluck('s.submission_id')
                    ->all();
            }
            if (!$ids && $publication->getData('sectionId')) {
                $ids = DB::table('submissions as s')
                    ->join('publications as p', 'p.publication_id', '=', 's.current_publication_id')
                    ->where('s.context_id', '=', (int) $context->getId())
                    ->where('s.submission_id', '!=', (int) $article->getId())
                    ->where('s.status', '=', Submission::STATUS_PUBLISHED)
                    ->where('p.section_id', '=', (int) $publication->getData('sectionId'))
                    ->orderBy('p.date_published', 'desc')
                    ->limit(self::RELATED_COUNT)
                    ->pluck('s.submission_id')
                    ->all();
            }
        } catch (Throwable $e) {
            error_log('[paideia theme] related: ' . $e->getMessage());
            return [];
        }

        $request = Application::get()->getRequest();
        $items = [];
        foreach ($ids as $id) {
            $submission = Repo::submission()->get((int) $id);
            $pub = $submission ? $submission->getCurrentPublication() : null;
            if (!$pub) {
                continue;
            }
            $date = (string) $pub->getData('datePublished');
            $items[] = [
                'url' => $request->url(null, 'article', 'view', [$submission->getBestId()]),
                'title' => (string) $pub->getLocalizedTitle(null, 'html'),
                'authors' => (string) $pub->getShortAuthorString(),
                'year' => $date ? substr($date, 0, 4) : '',
            ];
        }
        @file_put_contents($file, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $items;
    }

    /**
     * @return array{count: int, url: string, works: array}|null
     */
    public static function citedBy(?string $doi): ?array
    {
        $doi = strtolower(trim((string) $doi));
        if ($doi === '' || !preg_match('#^10\.\d{4,9}/\S+$#', $doi)) {
            return null;
        }
        $file = Core::getBaseDir() . '/cache/pdt-citedby-' . md5($doi) . '.json';
        if (is_readable($file)) {
            $cached = json_decode((string) file_get_contents($file), true);
            $ttl = !empty($cached['error']) ? self::CACHE_TTL_ERROR : self::CACHE_TTL;
            if (is_array($cached) && filemtime($file) >= time() - $ttl) {
                return empty($cached['error']) && $cached['count'] > 0 ? $cached : null;
            }
        }

        $data = ['count' => 0, 'url' => '', 'works' => [], 'error' => false];
        try {
            $client = Application::get()->getHttpClient();
            $opts = ['timeout' => 3, 'connect_timeout' => 2, 'headers' => ['Accept' => 'application/json']];
            $work = json_decode((string) $client->request('GET', 'https://api.openalex.org/works/doi:' . rawurlencode($doi) . '?select=id,cited_by_count', $opts)->getBody(), true);
            $data['count'] = (int) ($work['cited_by_count'] ?? 0);
            $workId = basename((string) ($work['id'] ?? ''));
            if ($data['count'] > 0 && $workId !== '') {
                $data['url'] = 'https://openalex.org/works?filter=cites:' . rawurlencode($workId);
                $list = json_decode((string) $client->request(
                    'GET',
                    'https://api.openalex.org/works?filter=cites:' . rawurlencode($workId)
                        . '&sort=publication_date:desc&per-page=' . self::CITED_BY_COUNT
                        . '&select=display_name,doi,publication_year,primary_location,authorships',
                    $opts
                )->getBody(), true);
                foreach ($list['results'] ?? [] as $r) {
                    $authors = array_map(fn ($a) => (string) ($a['author']['display_name'] ?? ''), array_slice($r['authorships'] ?? [], 0, 3));
                    $data['works'][] = [
                        'title' => (string) ($r['display_name'] ?? ''),
                        'url' => preg_match('#^https?://#i', (string) ($r['doi'] ?? '')) ? (string) $r['doi'] : '',
                        'year' => (string) ($r['publication_year'] ?? ''),
                        'source' => (string) ($r['primary_location']['source']['display_name'] ?? ''),
                        'authors' => implode(', ', array_filter($authors)) . (count($r['authorships'] ?? []) > 3 ? ' et al.' : ''),
                    ];
                }
            }
        } catch (Throwable $e) {
            // 404 (DOI no indexado todavía) o red caída: se reintenta más tarde.
            $data['error'] = !str_contains($e->getMessage(), '404');
        }
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return !$data['error'] && $data['count'] > 0 ? $data : null;
    }
}
