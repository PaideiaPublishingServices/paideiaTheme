<?php

/**
 * @file plugins/themes/paideia/PaideiaThemePlugin.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class PaideiaThemePlugin
 *
 * @brief Theme moderno para OJS 3.4 y 3.5, child del theme Default.
 *
 * Qué agrega sobre el Default:
 *  - Página de artículo de revista indexada: tipo de artículo, autores con
 *    ORCID y afiliaciones numeradas (con ROR), fechas editoriales, DOI
 *    copiable, licencia, cómo citar, métricas, índice lateral y texto
 *    completo HTML inline (opcional).
 *  - Inicio con número actual o "publicación continua", pestañas de artículos
 *    recientes / más vistos / más descargados y bloque de indexación.
 *  - SEO: JSON-LD ScholarlyArticle, Open Graph, canónica y
 *    citation_fulltext_html_url.
 *  - Modo oscuro, tipografías locales, accesibilidad (foco visible, saltos,
 *    contraste) y configuración sin código desde Apariencia.
 *
 * Diseño: se sobreescriben las menos plantillas posibles y los datos se
 * preparan en PHP (classes/) para que las plantillas no dependan de la
 * versión de OJS.
 */

namespace APP\plugins\themes\paideia;

use APP\core\Application;
use APP\plugins\themes\paideia\classes\ArticleData;
use APP\plugins\themes\paideia\classes\ArticleExtras;
use APP\plugins\themes\paideia\classes\Branding;
use APP\plugins\themes\paideia\classes\Compat;
use APP\plugins\themes\paideia\classes\Footer;
use APP\plugins\themes\paideia\classes\HomeData;
use APP\plugins\themes\paideia\classes\Indexes;
use APP\plugins\themes\paideia\classes\InlineGalley;
use APP\plugins\themes\paideia\classes\Seo;
use PKP\plugins\Hook;
use PKP\plugins\ThemePlugin;
use PKP\template\PKPTemplateManager;
use Throwable;

class PaideiaThemePlugin extends ThemePlugin
{
    /** Evita registrar el filtro de salida más de una vez por request. */
    protected bool $filterRegistered = false;

    public const SUPPORT_URL = 'https://shop.paideiahosting.net/shop/paideia-theme-annual-support-210';
    public const DOCS_URL = 'https://paideiapublishingservices.github.io/paideiaTheme/';
    public const REPO_URL = 'https://github.com/PaideiaPublishingServices/paideiaTheme';

    /**
     * @copydoc ThemePlugin::isActive()
     */
    public function isActive()
    {
        if (defined('SESSION_DISABLE_INIT')) {
            return true;
        }
        return parent::isActive();
    }

    public function init()
    {
        $this->setParent('defaultthemeplugin');

        // Opciones del theme padre que Paideia no usa: tipografía (trae la
        // suya), sumario de la revista en el inicio (la portada ya muestra la
        // descripción) y gráfico de estadísticas (las métricas van en el panel
        // lateral del artículo).
        foreach (['typography', 'showDescriptionInJournalIndex', 'displayStats'] as $unused) {
            $this->removeOption($unused);
        }
        // El color del padre es el color principal de todo el theme.
        if ($this->parent && isset($this->parent->options['baseColour']) && $this->isActive()) {
            $this->parent->options['baseColour']->label = __('plugins.themes.paideia.option.colour.label');
            $this->parent->options['baseColour']->description = htmlspecialchars(__('plugins.themes.paideia.option.colour.description'), ENT_QUOTES, 'UTF-8');
        }

        $this->addThemeOptions();

        // ------------------------------------------------------------------
        // Estilos
        // ------------------------------------------------------------------
        // Misma clave 'font' que el padre: así reemplaza a Noto Sans en vez
        // de sumar una segunda familia que nadie usa.
        $this->addStyle('font', 'styles/fonts.css');
        $this->addStyle('paideia', 'styles/paideia.css', [
            'priority' => PKPTemplateManager::STYLE_SEQUENCE_LATE,
        ]);
        $this->addStyle('paideiaVars', $this->getCssVariables(), [
            'inline' => true,
            'priority' => PKPTemplateManager::STYLE_SEQUENCE_LATE,
        ]);

        // ------------------------------------------------------------------
        // Scripts
        // ------------------------------------------------------------------
        $this->addScript('paideiaData', 'window.PDT = ' . json_encode($this->getScriptData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';', [
            'inline' => true,
            'contexts' => 'frontend',
        ]);
        $this->addScript('paideia', 'js/paideia.js', [
            'contexts' => 'frontend',
            'priority' => PKPTemplateManager::STYLE_SEQUENCE_LATE,
        ]);

        // ------------------------------------------------------------------
        // Hooks. init() corre para todos los themes registrados, no sólo el
        // activo: cada callback comprueba isActive().
        // ------------------------------------------------------------------
        Hook::add('TemplateManager::display', [$this, 'onTemplateDisplay']);
        Hook::add('ArticleHandler::view::galley', [$this, 'onGalleyView'], Hook::SEQUENCE_CORE);
    }

    /**
     * El aviso de soporte va antes que las opciones heredadas del Default.
     */
    public function getOptionsConfig()
    {
        $options = parent::getOptionsConfig();
        if (isset($options['pdtSupport'])) {
            $options = ['pdtSupport' => $options['pdtSupport']] + $options;
        }
        return $options;
    }

    /**
     * Antes de 1.0.1 no existía el interruptor de indexación: si la revista
     * ya tenía índices marcados o logos propios, arranca encendido.
     */
    protected function hasLegacyIndexing(): bool
    {
        try {
            $context = Application::get()->getRequest()->getContext();
            $contextId = $context ? (int) $context->getId() : 0;
            foreach (array_keys(Indexes::CATALOG) as $key) {
                if ($this->getSetting($contextId, Indexes::enabledOption($key))) {
                    return true;
                }
            }
            foreach ((array) $this->getSetting($contextId, 'pdtIndexing') as $html) {
                if (trim(strip_tags((string) $html, '<img>')) !== '') {
                    return true;
                }
            }
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * Estilos del formulario del tema en el panel: la lista de índices se
     * muestra compacta (una casilla por línea) y los enlaces, sangrados
     * debajo de su índice. Van dentro del aviso de soporte, que sólo se
     * muestra con Paideia elegido, así que no afectan a otros formularios.
     */
    protected static function backendStyles(): string
    {
        $idx = '.pkpFormField:has(> .pkpFormField__control input[name^="pdtIdx_"])';
        $url = '.pkpFormField:has(input[name^="pdtIdxUrl_"])';
        return '<style>'
            . "{$idx}{margin-top:0 !important;padding-top:.1rem !important;padding-bottom:.1rem !important;border:0 !important;box-shadow:none !important;background:none !important}"
            . "{$idx} > legend{position:absolute !important;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}"
            . "{$url}{margin:.1rem 0 .6rem 2.75rem !important;padding-top:0 !important}"
            . "{$url} .pkpFormFieldLabel{font-weight:400;font-size:.875rem}"
            // Con el interruptor general apagado, también se ocultan los
            // enlaces de los índices que hubieran quedado marcados.
            . '.pkpFormGroup__fields:has(input[name="pdtIndexingOn"]:not(:checked)) ' . $url . '{display:none !important}'
            . '</style>';
    }

    /**
     * Opciones visibles en Ajustes → Sitio web → Apariencia → Tema.
     *
     * Cada opción toma de los archivos de idioma su etiqueta (.label), una
     * descripción breve (.description) y, si hace falta más detalle, un
     * tooltip (.tooltip). Los textos se escapan: OJS los muestra como HTML.
     */
    protected function addThemeOptions(): void
    {
        $t = function (string $key): ?string {
            $full = "plugins.themes.paideia.option.{$key}";
            $text = __($full);
            return ($text === '' || $text === $full || str_starts_with($text, '##')) ? null : $text;
        };
        $add = function (string $name, string $type, string $key, array $args) use ($t) {
            $args['label'] ??= (string) $t("{$key}.label");
            // La descripción se muestra como HTML: se escapa. El tooltip
            // también se lee como texto (lectores de pantalla), así que va
            // tal cual; sólo se quitan < > & para que no se interpreten.
            if (!isset($args['description']) && ($text = $t("{$key}.description")) !== null) {
                $args['description'] = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
            if (!isset($args['tooltip']) && ($text = $t("{$key}.tooltip")) !== null) {
                $args['tooltip'] = str_replace(['<', '>', '&'], ['‹', '›', 'y'], $text);
            }
            $this->addOption($name, $type, $args);
        };
        $checkbox = fn (string $key) => [['value' => true, 'label' => (string) $t("{$key}.option")]];
        $radio = fn (string $key, array $values) => array_map(
            fn ($v) => ['value' => $v, 'label' => (string) $t("{$key}.{$v}")],
            $values
        );

        // Soporte: aviso informativo (FieldHTML no guarda valor), primero de
        // la lista; ver getOptionsConfig().
        $supportText = $t('support.description');
        $supportLink = $t('support.link');
        if ($supportText !== null && $supportLink !== null) {
            $this->addOption('pdtSupport', 'FieldHTML', [
                'label' => (string) $t('support.label'),
                'description' => '<p>' . htmlspecialchars($supportText, ENT_QUOTES, 'UTF-8') . '</p><p>'
                    . '<a href="' . htmlspecialchars(self::SUPPORT_URL, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
                    . htmlspecialchars($supportLink, ENT_QUOTES, 'UTF-8') . '</a> · '
                    . '<a href="' . htmlspecialchars(self::DOCS_URL, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
                    . htmlspecialchars((string) $t('support.docs'), ENT_QUOTES, 'UTF-8') . '</a></p>'
                    . self::backendStyles(),
            ]);
        }

        // Diseño
        $add('pdtFonts', 'FieldOptions', 'fonts', ['type' => 'radio', 'options' => $radio('fonts', ['serif', 'sans', 'system']), 'default' => 'serif']);
        $add('pdtDarkMode', 'FieldOptions', 'darkMode', ['options' => $checkbox('darkMode'), 'default' => true]);

        // Inicio
        $add('pdtHomeLayout', 'FieldOptions', 'homeLayout', ['type' => 'radio', 'options' => $radio('homeLayout', ['issue', 'continuous']), 'default' => 'issue']);
        $add('pdtHomeTabs', 'FieldOptions', 'homeTabs', ['options' => $checkbox('homeTabs'), 'default' => true]);
        $add('pdtAnnouncementBar', 'FieldOptions', 'announcementBar', ['options' => $checkbox('announcementBar'), 'default' => false]);

        // Indexación: un interruptor general; con él apagado la sección
        // ocupa una sola línea. Los índices van como lista compacta de
        // casillas y el enlace de cada uno aparece sólo si está marcado
        // (estilos en backendStyles()).
        $add('pdtIndexingOn', 'FieldOptions', 'indexingOn', ['options' => $checkbox('indexingOn'), 'default' => $this->hasLegacyIndexing()]);
        $add('pdtIndexingPosition', 'FieldOptions', 'indexingPosition', ['type' => 'radio', 'options' => $radio('indexingPosition', ['top', 'bottom']), 'default' => 'top', 'showWhen' => 'pdtIndexingOn']);
        foreach (Indexes::CATALOG as $key => [$name, $sub]) {
            $label = $sub ? "{$name} ({$sub})" : $name;
            $this->addOption(Indexes::enabledOption($key), 'FieldOptions', [
                'label' => $label,
                'options' => [['value' => true, 'label' => $label]],
                'default' => false,
                'showWhen' => 'pdtIndexingOn',
            ]);
            $this->addOption(Indexes::urlOption($key), 'FieldText', [
                'label' => __('plugins.themes.paideia.option.index.url', ['name' => $label]),
                'showWhen' => Indexes::enabledOption($key),
                'size' => 'large',
                'default' => '',
            ]);
        }
        $add('pdtIndexing', 'FieldRichTextarea', 'indexing', [
            'showWhen' => 'pdtIndexingOn',
            'isMultilingual' => true,
            'size' => 'large',
            'toolbar' => 'link image',
            'plugins' => 'link image',
            'default' => '',
        ]);

        // Artículo
        $add('pdtInlineHtml', 'FieldOptions', 'inlineHtml', ['options' => $checkbox('inlineHtml'), 'default' => true]);
        $add('pdtInlineStyle', 'FieldOptions', 'inlineStyle', ['type' => 'radio', 'options' => $radio('inlineStyle', ['theme', 'galley']), 'default' => 'theme', 'showWhen' => 'pdtInlineHtml']);
        $add('pdtInlineXpath', 'FieldText', 'inlineXpath', ['size' => 'large', 'default' => '', 'showWhen' => 'pdtInlineHtml']);
        $add('pdtShowMetrics', 'FieldOptions', 'showMetrics', ['options' => $checkbox('showMetrics'), 'default' => true]);
        $add('pdtArticleRail', 'FieldOptions', 'articleRail', ['options' => $radio('articleRail', ['citedBy', 'related']), 'default' => ['citedBy', 'related']]);
        $add('pdtCrossmark', 'FieldOptions', 'crossmark', [
            'description' => htmlspecialchars(__('plugins.themes.paideia.option.crossmark.description'), ENT_QUOTES, 'UTF-8')
                . ' <a href="https://www.crossref.org/documentation/crossmark/participating-in-crossmark/" target="_blank" rel="noopener">'
                . htmlspecialchars(__('plugins.themes.paideia.option.crossmark.docs'), ENT_QUOTES, 'UTF-8') . '</a>',
            'options' => $checkbox('crossmark'),
            'default' => false,
        ]);
        $add('pdtSeo', 'FieldOptions', 'seo', ['options' => $checkbox('seo'), 'default' => true]);

        // Pie de página
        $add('pdtFooterColumns', 'FieldOptions', 'footerColumns', [
            'type' => 'radio',
            'options' => [['value' => '2', 'label' => '2'], ['value' => '3', 'label' => '3'], ['value' => '4', 'label' => '4']],
            'default' => '3',
        ]);
        $add('pdtFooterIdentity', 'FieldOptions', 'footerIdentity', ['options' => $checkbox('footerIdentity'), 'default' => true]);

        // Cookies
        $add('pdtCookieBar', 'FieldOptions', 'cookieBar', ['options' => $checkbox('cookieBar'), 'default' => false]);
        $add('pdtCookieText', 'FieldTextarea', 'cookieText', ['isMultilingual' => true, 'default' => '', 'showWhen' => 'pdtCookieBar']);
    }

    /**
     * Variables CSS derivadas de las opciones. El color base es la opción
     * "baseColour" del theme padre, para no duplicar configuración.
     */
    protected function getCssVariables(): string
    {
        $primary = $this->sanitizeColour($this->getOption('baseColour'), '#1E6292');
        // El acento (tipo de artículo, detalles) es parte del diseño del theme.
        $accent = '#B45309';
        $fonts = match ($this->getOption('pdtFonts')) {
            'sans' => ['var(--pdt-font-sans)', 'var(--pdt-font-sans)'],
            'system' => ['var(--pdt-font-system)', 'var(--pdt-font-system)'],
            default => ['var(--pdt-font-serif)', 'var(--pdt-font-sans)'],
        };
        return ':root{--pdt-primary:' . $primary . ';--pdt-accent:' . $accent
            . ';--pdt-font-heading:' . $fonts[0] . ';--pdt-font-body:' . $fonts[1] . ';}';
    }

    protected function sanitizeColour($value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $value) ? $value : $fallback;
    }

    protected function getScriptData(): array
    {
        return [
            'darkMode' => (bool) $this->getOption('pdtDarkMode'),
            'i18n' => [
                'copy' => __('plugins.themes.paideia.copy'),
                'copied' => __('plugins.themes.paideia.copied'),
                'toc' => __('plugins.themes.paideia.toc'),
                'darkMode' => __('plugins.themes.paideia.darkMode'),
                'lightMode' => __('plugins.themes.paideia.lightMode'),
                'close' => __('common.close'),
                'enlarge' => __('plugins.themes.paideia.enlarge'),
                'showAllRefs' => __('plugins.themes.paideia.showAllRefs'),
                'more' => __('plugins.themes.paideia.navMore'),
                'copyCitation' => __('plugins.themes.paideia.copyCitation'),
                'backToTop' => __('plugins.themes.paideia.backToTop'),
            ],
        ];
    }

    /**
     * Prepara datos y metadatos según la plantilla que se va a mostrar.
     */
    public function onTemplateDisplay(string $hookName, array $args): bool
    {
        if (!$this->isActive()) {
            return Hook::CONTINUE;
        }
        $templateMgr = $args[0];
        $template = (string) $args[1];

        if (!str_contains($template, 'frontend/')) {
            return Hook::CONTINUE;
        }

        if (!$this->filterRegistered) {
            $templateMgr->registerFilter('output', [Branding::class, 'outputFilter']);
            $templateMgr->registerFilter('output', [$this, 'injectHeaderExtras']);
            // Va en el <head> (los scripts del theme se cargan al final del
            // <body>) para fijar el modo oscuro antes del primer pintado.
            if ($this->getOption('pdtDarkMode')) {
                $templateMgr->addHeader('pdtColorScheme', '<script>' . Branding::colorSchemeBootstrap(true) . '</script>');
            }
            $this->filterRegistered = true;
        }

        $request = Application::get()->getRequest();
        $indexingOn = (bool) $this->getOption('pdtIndexingOn');
        try {
            $columns = (int) $this->getOption('pdtFooterColumns');
            $templateMgr->assign([
                'pdtFooter' => Footer::build($templateMgr, $request, (bool) $this->getOption('pdtFooterIdentity'), in_array($columns, [2, 3, 4], true) ? $columns : 3),
                // Los bloques de la barra lateral van siempre al final de la página.
                'pdtBlocks' => 'bottom',
                'pdtTheme' => $this,
                'pdtRepoUrl' => self::DOCS_URL,
                'pdtIndexes' => $indexingOn ? Indexes::active($this, $request->getBaseUrl()) : [],
                'pdtIndexingCustom' => $indexingOn ? $this->localizedOption('pdtIndexing') : '',
                'pdtIndexingPosition' => $this->getOption('pdtIndexingPosition') === 'bottom' ? 'bottom' : 'top',
            ]);
            $this->prepareHeaderExtras($request);
            if ($template === 'frontend/pages/article.tpl') {
                $this->prepareArticle($templateMgr, $request);
            } elseif ($template === 'frontend/pages/indexJournal.tpl') {
                $this->prepareHome($templateMgr, $request);
            }
        } catch (Throwable $e) {
            // Un error en los extras no debe tumbar la página: el theme cae a
            // la plantilla con lo que haya.
            error_log('[paideia theme] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        }

        return Hook::CONTINUE;
    }

    /** HTML del selector de idioma y de la barra de avisos (ver injectHeaderExtras). */
    protected string $langHtml = '';
    protected string $annBarHtml = '';
    protected string $cookieHtml = '';
    protected bool $headerPrepared = false;

    /**
     * Selector de idioma en el menú (reemplaza al bloque "Idioma") y, si
     * está activada, barra con el último aviso.
     */
    protected function prepareHeaderExtras($request): void
    {
        if ($this->headerPrepared) {
            return;
        }
        $this->headerPrepared = true;

        $languages = Compat::languages($request);
        if ($languages) {
            $current = null;
            foreach ($languages as $l) {
                if ($l['current']) {
                    $current = $l;
                }
            }
            $current ??= $languages[0];
            $label = Seo::e(__('common.language'));
            if (count($languages) <= 3) {
                $html = '<ul class="pdt-lang pdt-lang--inline" aria-label="' . $label . '">';
                foreach ($languages as $l) {
                    $html .= '<li><a href="' . Seo::e($l['url']) . '" lang="' . Seo::e(str_replace('_', '-', $l['code'])) . '" title="' . Seo::e($l['name']) . '"'
                        . ($l['current'] ? ' aria-current="true"' : '') . '>' . Seo::e($l['short'])
                        . '<span class="pkp_screen_reader"> ' . Seo::e($l['name']) . '</span></a></li>';
                }
                $html .= '</ul>';
            } else {
                $html = '<details class="pdt-lang pdt-lang--menu"><summary aria-label="' . $label . ': ' . Seo::e($current['name']) . '">'
                    . '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm6.9 6h-2.9a15.7 15.7 0 0 0-1.4-3.6A8 8 0 0 1 18.9 8zM12 4c.8 1.2 1.5 2.5 1.9 4h-3.8c.4-1.4 1.1-2.8 1.9-4zM4.3 14a8.2 8.2 0 0 1 0-4h3.4a16.5 16.5 0 0 0 0 4H4.3zm.8 2h2.9c.3 1.3.8 2.5 1.4 3.6A8 8 0 0 1 5.1 16zM8 8H5.1a8 8 0 0 1 4.3-3.6C8.8 5.5 8.3 6.7 8 8zm4 12c-.8-1.2-1.5-2.5-1.9-4h3.8c-.4 1.4-1.1 2.8-1.9 4zm2.3-6H9.7a14.7 14.7 0 0 1 0-4h4.6a14.7 14.7 0 0 1 0 4zm.3 5.6c.6-1.1 1.1-2.3 1.4-3.6h2.9a8 8 0 0 1-4.3 3.6zm1.8-5.6a16.5 16.5 0 0 0 0-4h3.4a8.2 8.2 0 0 1 0 4h-3.4z"/></svg>'
                    . '<span>' . Seo::e($current['short']) . '</span></summary><ul>';
                foreach ($languages as $l) {
                    $html .= '<li><a href="' . Seo::e($l['url']) . '" lang="' . Seo::e(str_replace('_', '-', $l['code'])) . '"'
                        . ($l['current'] ? ' aria-current="true"' : '') . '>' . Seo::e($l['name']) . '</a></li>';
                }
                $html .= '</ul></details>';
            }
            $this->langHtml = $html;
        }

        if ($this->getOption('pdtCookieBar')) {
            $this->cookieHtml = $this->buildCookieBar($request);
        }

        if ($this->getOption('pdtAnnouncementBar')) {
            $latest = Compat::announcements($request->getContext(), null, 1);
            if ($latest) {
                $a = $latest[0];
                $this->annBarHtml = '<div class="pdt-annbar" data-pdt-ann="' . (int) $a['id'] . '" role="region" aria-label="' . Seo::e(__('announcement.announcements')) . '">'
                    . '<div class="pdt-annbar__in"><span class="pdt-annbar__label">' . Seo::e(__('plugins.themes.paideia.announcement')) . '</span>'
                    . '<a class="pdt-annbar__link" href="' . Seo::e($a['url']) . '">' . Seo::e($a['title']) . '</a>'
                    . '<button type="button" class="pdt-annbar__close" aria-label="' . Seo::e(__('common.close')) . '">'
                    . '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 6.4 17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z"/></svg>'
                    . '</button></div></div>';
            }
        }
    }

    /**
     * Valor de una opción multilingüe en el idioma actual (con respaldo).
     */
    public function localizedOption(string $name): string
    {
        $value = $this->getOption($name);
        if (is_array($value)) {
            $value = $this->getLocalizedOption($name);
        }
        return is_string($value) ? $value : '';
    }

    /**
     * Barra de aviso de cookies (opcional). Texto propio del editor o el del
     * theme, enlace a la declaración de privacidad de la revista y dos
     * botones. La elección se guarda en el navegador (localStorage) y se
     * avisa con el evento "pdt:cookies" para que otras integraciones
     * (analítica, etc.) puedan esperar el consentimiento.
     */
    protected function buildCookieBar($request): string
    {
        $text = trim(strip_tags($this->localizedOption('pdtCookieText')));
        if ($text === '') {
            $text = __('plugins.themes.paideia.cookies.text');
        }
        $privacy = $request->getContext()
            ? $request->url(null, 'about', 'privacy')
            : $request->url('index', 'about', 'privacy');
        return '<div class="pdt-cookies" role="region" aria-label="' . Seo::e(__('plugins.themes.paideia.cookies.label')) . '" hidden>'
            . '<p class="pdt-cookies__text">' . nl2br(Seo::e($text), false)
            . ' <a href="' . Seo::e($privacy) . '">' . Seo::e(__('plugins.themes.paideia.cookies.privacy')) . '</a></p>'
            . '<div class="pdt-cookies__actions">'
            . '<button type="button" class="pdt-btn" data-pdt-cookies="necessary">' . Seo::e(__('plugins.themes.paideia.cookies.necessary')) . '</button>'
            . '<button type="button" class="pdt-btn pdt-btn--primary" data-pdt-cookies="all">' . Seo::e(__('plugins.themes.paideia.cookies.accept')) . '</button>'
            . '</div></div>';
    }

    /**
     * Filtro de salida: coloca el selector de idioma junto a "Buscar" y la
     * barra de avisos arriba de la cabecera.
     */
    public function injectHeaderExtras(string $output, $template = null): string
    {
        if (stripos($output, '</body>') === false) {
            return $output;
        }
        if ($this->langHtml !== '' && !str_contains($output, 'class="pdt-lang pdt-lang--')) {
            $count = 0;
            $output = preg_replace('/(<div class="pkp_navigation_search_wrapper")/', addcslashes($this->langHtml, '\\$') . '$1', $output, 1, $count);
        }
        if ($this->cookieHtml !== '' && !str_contains($output, 'class="pdt-cookies"')) {
            $output = preg_replace('#</body>#i', addcslashes($this->cookieHtml, '\\$') . '</body>', $output, 1);
        }
        if ($this->annBarHtml !== '' && !str_contains($output, 'class="pdt-annbar"')) {
            $output = preg_replace('/(<header class="pkp_structure_head")/', addcslashes($this->annBarHtml, '\\$') . '$1', $output, 1);
        }
        return $output;
    }

    /**
     * Quita de un fragmento HTML los elementos de primer nivel con alguna de
     * las clases indicadas. Lo usan las plantillas para anular bloques y
     * salidas de plugins que el theme ya resuelve.
     *
     * @param string[] $classes
     */
    public function removeByClass(?string $html, array $classes): string
    {
        $html = (string) $html;
        if (trim($html) === '' || !$classes) {
            return $html;
        }
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="pdt-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = (new \DOMXPath($doc))->query('//div[@id="pdt-root"]')->item(0);
        if (!$root) {
            return $html;
        }
        $changed = false;
        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node instanceof \DOMElement) {
                $nodeClasses = preg_split('/\s+/', (string) $node->getAttribute('class'));
                if (array_intersect($classes, $nodeClasses)) {
                    $root->removeChild($node);
                    $changed = true;
                }
            }
        }
        if (!$changed) {
            return $html;
        }
        // Comentarios sueltos que dejan las plantillas de los bloques quitados.
        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node instanceof \DOMComment) {
                $root->removeChild($node);
            }
        }
        $out = '';
        foreach ($root->childNodes as $node) {
            $out .= $doc->saveHTML($node);
        }
        return $out;
    }

    /**
     * Bloques de la barra lateral que el theme reemplaza: "Enviar un
     * artículo" (llamadas a la acción del theme) e "Idioma" (selector del
     * menú).
     */
    public function filterSidebar(?string $html): string
    {
        $remove = ['block_make_submission'];
        if ($this->langHtml !== '') {
            $remove[] = 'block_language';
        }
        return $this->removeByClass($html, $remove);
    }

    protected function prepareArticle($templateMgr, $request): void
    {
        $article = $templateMgr->getTemplateVars('article');
        $publication = $templateMgr->getTemplateVars('publication');
        $issue = $templateMgr->getTemplateVars('issue');
        $context = $request->getContext();
        if (!$article || !$publication || !$context) {
            return;
        }

        $data = ArticleData::build($article, $publication, $context, [
            'metrics' => (bool) $this->getOption('pdtShowMetrics'),
        ]);

        // Texto completo inline
        $fulltext = null;
        $htmlGalley = null;
        $hasAccess = $templateMgr->getTemplateVars('hasAccess');
        // Falla cerrado: sin acceso confirmado (revistas por suscripción) no se
        // inserta el texto completo.
        if ($this->getOption('pdtInlineHtml') && $hasAccess === true) {
            $styleMode = $this->getOption('pdtInlineStyle') === 'galley' ? 'galley' : 'theme';

            // Punto de integración para plugins de producción (JATS → HTML,
            // galeradas asistidas por IA): pueden entregar el HTML del texto
            // completo sin que exista una galerada HTML. El theme lo limpia,
            // arma el índice y lo muestra igual que una galerada.
            //   Hook::add('PaideiaTheme::articleFulltext', function ($hookName, $args) {
            //       [$html, $galley, $article, $publication] = ...; $args[0] = '<section>...</section>';
            //   });
            $pluginHtml = null;
            $pluginGalley = null;
            Hook::call('PaideiaTheme::articleFulltext', [&$pluginHtml, &$pluginGalley, $article, $publication, $request]);
            if (is_string($pluginHtml) && trim($pluginHtml) !== '') {
                $fulltext = InlineGalley::extract('<html><body>' . $pluginHtml . '</body></html>', $styleMode);
                $htmlGalley = $pluginGalley;
            }

            $htmlGalley = $fulltext ? $htmlGalley : InlineGalley::findHtmlGalley($templateMgr->getTemplateVars('primaryGalleys') ?? []);
            if ($htmlGalley && !$fulltext) {
                $fulltext = InlineGalley::render(
                    $request,
                    $article,
                    $htmlGalley,
                    $styleMode,
                    trim((string) $this->getOption('pdtInlineXpath'))
                );
                if ($fulltext) {
                    $this->logInlineUsage($request, $article, $htmlGalley, $issue);
                }
            }
        }

        $templateMgr->assign([
            'pdt' => $data,
            'pdtFulltext' => $fulltext,
            // Si el texto completo ya trae su sección de referencias, el theme
            // no repite la lista (ni la entrada del índice).
            'pdtGalleyHasRefs' => $fulltext && !empty($fulltext['toc']) && (bool) array_filter(
                $fulltext['toc'],
                fn ($i) => preg_match('/^[\d.\s]*(referencias|references|referências|refer[eê]ncias bibliogr[aá]ficas|bibliograf[ií]a|bibliography|daftar pustaka|obras citadas|works cited)\b/iu', $i['text'])
            ),
            'pdtHtmlGalleyId' => $fulltext && $htmlGalley ? $htmlGalley->getId() : null,
            'pdtTheme' => $this,
        ]);

        // Formato de cita principal configurado en el plugin Citation Style
        // Language (para preseleccionarlo en la ventana "Cómo citar").
        $csl = \PKP\plugins\PluginRegistry::getPlugin('generic', 'citationstylelanguageplugin');
        if ($csl && method_exists($csl, 'getPrimaryStyleName')) {
            $templateMgr->assign('pdtCitePrimary', $csl->getPrimaryStyleName((int) $context->getId()));
        }

        // Panel lateral: citado por (OpenAlex) y artículos relacionados.
        $rail = (array) $this->getOption('pdtArticleRail');
        $templateMgr->assign([
            'pdtCitedBy' => in_array('citedBy', $rail, true) && !empty($data['doi']['id']) ? ArticleExtras::citedBy($data['doi']['id']) : null,
            'pdtRelated' => in_array('related', $rail, true) ? ArticleExtras::related($article, $publication, $context) : [],
            'pdtLicense' => self::licenseInfo((string) $publication->getData('licenseUrl')),
        ]);

        // Crossmark (Crossref): sólo con DOI. El botón va junto al título.
        $crossmark = $this->getOption('pdtCrossmark') && !empty($data['doi']['id']);
        $templateMgr->assign('pdtCrossmark', $crossmark);
        if ($crossmark) {
            $templateMgr->addHeader('pdtCrossmarkMeta', '<meta name="dc.identifier" content="doi:' . Seo::e($data['doi']['id']) . '">');
            $templateMgr->addHeader('pdtCrossmarkJs', '<script src="https://crossmark-cdn.crossref.org/widget/v2.0/widget.js" defer></script>');
        }

        if ($this->getOption('pdtSeo')) {
            Seo::addArticleHeaders($templateMgr, $request, $article, $publication, $issue, $data, (bool) $fulltext, $htmlGalley);
        }
    }

    protected function prepareHome($templateMgr, $request): void
    {
        $context = $request->getContext();
        if (!$context) {
            return;
        }
        $templateMgr->assign([
            'pdtHome' => HomeData::build($context, [
                'tabs' => (bool) $this->getOption('pdtHomeTabs'),
                'layout' => $this->getOption('pdtHomeLayout') === 'continuous' ? 'continuous' : 'issue',
            ]),
            'pdtAnnouncements' => $templateMgr->getTemplateVars('announcements')
                ? Compat::announcements($context, $templateMgr->getTemplateVars('announcements'), (int) $templateMgr->getTemplateVars('numAnnouncementsHomepage'))
                : [],
            'pdtTheme' => $this,
        ]);
        if ($this->getOption('pdtSeo')) {
            $templateMgr->addHeader('pdtCanonical', '<link rel="canonical" href="' . Seo::e(Compat::canonicalUrl($request, 'index')) . '">');
            $templateMgr->addHeader('pdtJsonLd', '<script type="application/ld+json">' . Seo::json(HomeData::periodicalJsonLd($request, $context)) . '</script>');
        }
    }

    /**
     * Nombre corto de la licencia a partir de su URL (Creative Commons).
     */
    public static function licenseInfo(string $url): ?array
    {
        if ($url === '') {
            return null;
        }
        $short = null;
        if (preg_match('#creativecommons\.org/licenses/([a-z-]+)/(\d\.\d)#i', $url, $m)) {
            $short = 'CC ' . strtoupper($m[1]) . ' ' . $m[2];
        } elseif (preg_match('#creativecommons\.org/publicdomain/zero/(\d\.\d)#i', $url, $m)) {
            $short = 'CC0 ' . $m[1];
        }
        return ['url' => $url, 'short' => $short];
    }

    /**
     * Si el texto completo se muestra inline, el enlace a la galerada HTML
     * lleva a la página del artículo en lugar del visor con iframe.
     */
    public function onGalleyView(string $hookName, array $args): bool
    {
        if (!$this->isActive() || !$this->getOption('pdtInlineHtml')) {
            return Hook::CONTINUE;
        }
        $request = $args[0];
        $galley = $args[2] ?? null;
        $article = $args[3] ?? null;
        if (!$galley || !$article || $galley->getData('urlRemote') || $request->getUserVar('viewer') === 'iframe') {
            return Hook::CONTINUE;
        }
        $file = $galley->getFile();
        if (!$file || !in_array($file->getData('mimetype'), InlineGalley::HTML_MIMETYPES, true)) {
            return Hook::CONTINUE;
        }
        $publicationId = (int) $galley->getData('publicationId');
        $path = $publicationId && $publicationId !== (int) $article->getData('currentPublicationId')
            ? [$article->getBestId(), 'version', $publicationId]
            : [$article->getBestId()];
        $request->redirectUrl($request->url(null, 'article', 'view', $path, null, 'pdt-fulltext'));
        return Hook::ABORT;
    }

    /**
     * Registrar la lectura del texto completo inline como una solicitud HTML
     * (COUNTER "Request"), igual que lo haría el visor de galeradas.
     */
    protected function logInlineUsage($request, $article, $galley, $issue): void
    {
        try {
            $submissionFile = $galley->getFile();
            event(new \APP\observers\events\UsageEvent(
                Application::ASSOC_TYPE_SUBMISSION_FILE,
                $request->getContext(),
                $article,
                $galley,
                $submissionFile,
                $issue
            ));
        } catch (Throwable $e) {
            error_log('[paideia theme] No se pudo registrar el uso inline: ' . $e->getMessage());
        }
    }

    public function getDisplayName()
    {
        return __('plugins.themes.paideia.name');
    }

    public function getDescription()
    {
        return __('plugins.themes.paideia.description');
    }
}
