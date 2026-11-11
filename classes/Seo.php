<?php

/**
 * @file plugins/themes/paideia/classes/Seo.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class Seo
 *
 * @brief Metadatos para buscadores y redes en la página de artículo.
 *
 * Complementa, sin duplicar, lo que ya emiten el core y sus plugins:
 *  - Google Scholar (citation_*) y Dublin Core los emiten sus plugins: acá
 *    sólo se agrega citation_fulltext_html_url cuando el texto completo HTML
 *    se muestra inline.
 *  - JSON-LD ScholarlyArticle (schema.org), que el core no emite.
 *  - Open Graph y Twitter Cards, para que el enlace se vea bien al compartir.
 *  - link rel=canonical sin el segmento de versión.
 */

namespace APP\plugins\themes\paideia\classes;

use APP\core\Application;
use Throwable;

class Seo
{
    public static function addArticleHeaders($templateMgr, $request, $article, $publication, $issue, $data, bool $fulltextInline = false, $htmlGalley = null): void
    {
        $context = $request->getContext();
        $canonical = $data['url'];

        $templateMgr->addHeader('pdtCanonical', '<link rel="canonical" href="' . self::e($canonical) . '">');

        if ($fulltextInline) {
            self::pointFulltextToArticle($templateMgr, $article, $htmlGalley, $canonical);
        }

        // Open Graph / Twitter
        $title = strip_tags((string) $publication->getLocalizedFullTitle(null, 'html'));
        $description = self::excerpt((string) $publication->getLocalizedData('abstract'), 300);
        $image = self::imageUrl($request, $article, $publication, $issue, $context);
        $og = [
            'og:type' => 'article',
            'og:site_name' => $context->getLocalizedName(),
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => $canonical,
            'og:locale' => ArticleData::currentLocale(),
            'article:published_time' => $data['dates']['published'] ? date('c', strtotime($data['dates']['published'])) : null,
            'og:image' => $image,
            'twitter:card' => $image ? 'summary_large_image' : 'summary',
        ];
        $tags = '';
        foreach ($og as $property => $content) {
            if ($content) {
                $attr = str_starts_with($property, 'twitter:') ? 'name' : 'property';
                $tags .= '<meta ' . $attr . '="' . self::e($property) . '" content="' . self::e($content) . '">' . "\n";
            }
        }
        $templateMgr->addHeader('pdtOpenGraph', $tags);

        // JSON-LD
        $templateMgr->addHeader(
            'pdtJsonLd',
            '<script type="application/ld+json">' . self::json(self::scholarlyArticle($request, $article, $publication, $issue, $data, $image)) . '</script>'
        );
    }

    /**
     * El plugin Google Scholar (3.4 y 3.5) declara citation_fulltext_html_url
     * con la URL de la galerada HTML. Con el texto inline, esa URL redirige a
     * la página del artículo: se reescribe la meta para que apunte directo
     * ahí, sin redirección. Si el plugin no está activo, se agrega.
     */
    protected static function pointFulltextToArticle($templateMgr, $article, $htmlGalley, string $canonical): void
    {
        $suffix = $htmlGalley ? '/view/' . $article->getBestId() . '/' . $htmlGalley->getBestGalleyId() : null;
        $meta = '<meta name="citation_fulltext_html_url" content="' . self::e($canonical) . '">';
        $templateMgr->registerFilter('output', function ($output) use ($suffix, $meta) {
            if (!str_contains($output, '</head>')) {
                return $output;
            }
            $found = false;
            $output = preg_replace_callback(
                '#<meta name="citation_fulltext_html_url" content="([^"]*)"\s*/?>#',
                function ($m) use ($suffix, $meta, &$found) {
                    if ($suffix && str_ends_with(rtrim($m[1], '/'), $suffix)) {
                        $found = true;
                        return $meta;
                    }
                    return $m[0];
                },
                $output
            ) ?? $output;
            if (!$found) {
                $output = preg_replace('#</head>#', $meta . "\n</head>", $output, 1);
            }
            return $output;
        });
    }

    public static function scholarlyArticle($request, $article, $publication, $issue, array $data, ?string $image): array
    {
        $context = $request->getContext();

        $authors = [];
        foreach ($data['authors'] as $author) {
            $person = [
                '@type' => 'Person',
                'name' => $author['name'],
            ];
            if ($author['givenName']) {
                $person['givenName'] = $author['givenName'];
            }
            if ($author['familyName']) {
                $person['familyName'] = $author['familyName'];
            }
            if ($author['orcid']) {
                $person['identifier'] = $author['orcid']['url'];
                $person['sameAs'] = $author['orcid']['url'];
            }
            $affiliations = [];
            foreach ($author['affiliationRefs'] as $n) {
                $aff = $data['affiliations'][$n - 1] ?? null;
                if (!$aff) {
                    continue;
                }
                $org = ['@type' => 'Organization', 'name' => $aff['name']];
                if ($aff['ror']) {
                    $org['identifier'] = $aff['ror'];
                    $org['sameAs'] = $aff['ror'];
                }
                $affiliations[] = $org;
            }
            if ($affiliations) {
                $person['affiliation'] = count($affiliations) === 1 ? $affiliations[0] : $affiliations;
            }
            $authors[] = $person;
        }

        $periodical = [
            '@type' => 'Periodical',
            'name' => $context->getLocalizedName(),
            'url' => $request->url(null, 'index'),
        ];
        $issn = array_values(array_filter([$context->getData('onlineIssn'), $context->getData('printIssn')]));
        if ($issn) {
            $periodical['issn'] = count($issn) === 1 ? $issn[0] : $issn;
        }
        if ($context->getData('publisherInstitution')) {
            $periodical['publisher'] = ['@type' => 'Organization', 'name' => $context->getData('publisherInstitution')];
        }

        $isPartOf = $periodical;
        if ($issue) {
            $issueNode = [
                '@type' => 'PublicationIssue',
                'url' => $request->url(null, 'issue', 'view', [$issue->getBestIssueId()]),
                'isPartOf' => $periodical,
            ];
            if ($issue->getNumber()) {
                $issueNode['issueNumber'] = (string) $issue->getNumber();
            }
            if ($issue->getVolume()) {
                $issueNode['isPartOf'] = [
                    '@type' => 'PublicationVolume',
                    'volumeNumber' => (string) $issue->getVolume(),
                    'isPartOf' => $periodical,
                ];
            }
            if ($issue->getDatePublished()) {
                $issueNode['datePublished'] = substr($issue->getDatePublished(), 0, 10);
            }
            $isPartOf = $issueNode;
        }

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'ScholarlyArticle',
            '@id' => $data['url'],
            'url' => $data['url'],
            'headline' => mb_substr(strip_tags((string) $publication->getLocalizedTitle(null, 'html')), 0, 250),
            'name' => strip_tags((string) $publication->getLocalizedFullTitle(null, 'html')),
            'inLanguage' => str_replace('_', '-', (string) ($publication->getData('locale') ?: ArticleData::currentLocale())),
            'author' => $authors,
            'isPartOf' => $isPartOf,
            'isAccessibleForFree' => true,
        ];

        foreach ($data['altTitles'] as $alt) {
            $node['alternativeHeadline'][] = strip_tags($alt['title']);
        }
        if ($data['abstracts']) {
            $node['abstract'] = self::excerpt($data['abstracts'][0]['html'], 5000);
            $keywords = $data['abstracts'][0]['keywords'];
            if ($keywords) {
                $node['keywords'] = implode(', ', $keywords);
            }
        }
        if ($data['dates']['published']) {
            $node['datePublished'] = substr($data['dates']['published'], 0, 10);
        }
        if ($data['dates']['received']) {
            $node['dateCreated'] = substr($data['dates']['received'], 0, 10);
        }
        if ($data['dates']['updated']) {
            $node['dateModified'] = substr($data['dates']['updated'], 0, 10);
        }
        if ($data['doi']) {
            $node['identifier'] = [
                '@type' => 'PropertyValue',
                'propertyID' => 'DOI',
                'value' => $data['doi']['id'],
            ];
            $node['sameAs'] = $data['doi']['url'];
        }
        if ($publication->getData('licenseUrl')) {
            $node['license'] = $publication->getData('licenseUrl');
        }
        if ($publication->getData('pages')) {
            $node['pagination'] = (string) $publication->getData('pages');
        }
        if ($image) {
            $node['image'] = $image;
        }
        try {
            $section = \APP\facades\Repo::section()->get((int) $publication->getData('sectionId'));
            if ($section) {
                $node['articleSection'] = $section->getLocalizedTitle();
            }
        } catch (Throwable $e) {
        }

        return $node;
    }

    /**
     * Portada del artículo, del número o de la revista (en ese orden).
     */
    protected static function imageUrl($request, $article, $publication, $issue, $context): ?string
    {
        try {
            if ($publication->getLocalizedData('coverImage')) {
                return $publication->getLocalizedCoverImageUrl($article->getData('contextId'));
            }
            if ($issue && $issue->getLocalizedCoverImageUrl()) {
                return $issue->getLocalizedCoverImageUrl();
            }
            $thumb = $context->getLocalizedData('journalThumbnail') ?: $context->getLocalizedData('homepageImage');
            if ($thumb && !empty($thumb['uploadName'])) {
                $publicFileManager = new \PKP\file\PublicFileManager();
                return $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($context->getId()) . '/' . $thumb['uploadName'];
            }
        } catch (Throwable $e) {
        }
        return null;
    }

    public static function excerpt(string $html, int $length): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }

    public static function json(array $data): string
    {
        // JSON_HEX_TAG evita que un "</script>" en un título cierre el bloque.
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
