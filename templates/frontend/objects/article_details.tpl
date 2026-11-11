{**
 * plugins/themes/paideia/templates/frontend/objects/article_details.tpl
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * Página de artículo con estructura de revista indexada.
 *
 * Los datos que difieren entre OJS 3.4 y 3.5 (autores, afiliaciones, ORCID,
 * palabras clave, fechas, métricas) llegan ya normalizados en $pdt desde
 * classes/ArticleData.php. El resto usa sólo métodos comunes a ambas
 * versiones.
 *
 * @uses $pdt array Datos normalizados (ver ArticleData::build)
 * @uses $pdtFulltext array|null Texto completo inline (ver InlineGalley::render)
 * @uses $pdtHtmlGalleyId int|null Galerada HTML mostrada inline
 *
 * @hook Templates::Article::Main []
 * @hook Templates::Article::Details::Reference []
 * @hook Templates::Article::Details []
 *}
{assign var="pdtSectionTitle" value=""}
{if $section}{assign var="pdtSectionTitle" value=$section->getLocalizedTitle()}{/if}
{capture assign="orcidSvg"}<svg class="pdt-icon pdt-icon--orcid" viewBox="0 0 256 256" width="16" height="16" aria-hidden="true" focusable="false"><circle cx="128" cy="128" r="128" fill="#A6CE39"/><path fill="#fff" d="M86.3 186.2H70.9V79.1h15.4v107.1zM108.9 79.1h41.6c39.6 0 57 28.3 57 53.6 0 27.5-21.5 53.6-56.8 53.6h-41.8V79.1zm15.4 93.3h24.5c34.9 0 42.9-26.5 42.9-39.7 0-21.5-13.7-39.7-43.7-39.7h-23.7v79.4zM88.7 56.8c0 5.5-4.5 10.1-10.1 10.1s-10.1-4.6-10.1-10.1c0-5.6 4.5-10.1 10.1-10.1s10.1 4.6 10.1 10.1z"/></svg>{/capture}

<article class="pdt-article obj_article_details">

	{* Aviso de versión anterior *}
	{if $currentPublication && $publication->getId() !== $currentPublication->getId()}
		<div class="pdt-notice" role="note">
			{capture assign="latestVersionUrl"}{url page="article" op="view" path=$article->getBestId()}{/capture}
			{translate key="submission.outdatedVersion"
				datePublished=$publication->getData('datePublished')|date_format:$dateFormatShort
				urlRecentVersion=$latestVersionUrl|escape
			}
		</div>
	{/if}

	{* ================================================================
	   Encabezado
	   ================================================================ *}
	<header class="pdt-article__header">
		<div class="pdt-kicker">
			{if $pdtSectionTitle}<span class="pdt-kicker__type">{$pdtSectionTitle|escape}</span>{/if}
			{if $currentContext->getData('publishingMode') == 0 || $publication->getData('accessStatus') == 1}
				<span class="pdt-badge pdt-badge--oa">
					<svg class="pdt-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 1a6 6 0 0 0-6 6v1h2V7a4 4 0 0 1 8 0v4H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2V7a6 6 0 0 0-6-6zm0 13a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/></svg>
					{translate key="plugins.themes.paideia.openAccess"}
				</span>
			{/if}
			{if $pdtLicense}
				<a class="pdt-badge pdt-badge--license" href="#pdt-license" title="{$pdtLicense.url|escape}">
					<svg class="pdt-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm-1.6-5.2c-.4.4-1 .7-1.7.7-1.4 0-2.3-1.1-2.3-2.5s.9-2.5 2.3-2.5c.7 0 1.3.3 1.7.7l-.9.9a1.2 1.2 0 0 0-.8-.3c-.6 0-1 .5-1 1.2s.4 1.2 1 1.2c.3 0 .6-.1.8-.3l.9.9zm5.6 0c-.4.4-1 .7-1.7.7-1.4 0-2.3-1.1-2.3-2.5s.9-2.5 2.3-2.5c.7 0 1.3.3 1.7.7l-.9.9a1.2 1.2 0 0 0-.8-.3c-.6 0-1 .5-1 1.2s.4 1.2 1 1.2c.3 0 .6-.1.8-.3l.9.9z"/></svg>
					{if $pdtLicense.short}{$pdtLicense.short|escape}{else}{translate key="submission.license"}{/if}
				</a>
			{/if}
			{if $issue}
				<a class="pdt-kicker__issue" href="{url page="issue" op="view" path=$issue->getBestIssueId()}">{$issue->getIssueIdentification()|strip_tags|escape}</a>
			{/if}
			{if $publication->getData('pages')}
				<span class="pdt-kicker__pages">{translate key="plugins.themes.paideia.pages"} {$publication->getData('pages')|escape}</span>
			{/if}
			{if $pdtCrossmark}
				{* Crossmark (Crossref). Snippet v2.0 sin modificar, salvo el tamaño. *}
				<a class="pdt-crossmark" data-target="crossmark"><img src="https://crossmark-cdn.crossref.org/widget/v2.0/logos/CROSSMARK_Color_horizontal.svg" width="150" alt="Crossmark"></a>
			{/if}
		</div>

		<h1 class="pdt-article__title page_title">
			{$publication->getLocalizedTitle(null, 'html')|strip_unsafe_html}
		</h1>
		{if $publication->getLocalizedData('subtitle')}
			<p class="pdt-article__subtitle subtitle">{$publication->getLocalizedSubTitle(null, 'html')|strip_unsafe_html}</p>
		{/if}
		{foreach from=$pdt.altTitles item=altTitle}
			<p class="pdt-article__alt-title" lang="{$altTitle.langTag|escape}">{$altTitle.title|strip_unsafe_html}</p>
		{/foreach}

		{* Autores *}
		{if $pdt.authors}
			<ul class="pdt-authors" aria-label="{translate key="article.authors"}">
				{foreach from=$pdt.authors item=author}
					<li class="pdt-author">
						<span class="pdt-author__name">{$author.name|escape}</span>
						{if $author.affiliationRefs && count($pdt.affiliations) > 1}
							<sup class="pdt-author__refs">{foreach from=$author.affiliationRefs item=ref name=refs}<a href="#pdt-aff-{$ref}">{$ref}</a>{if !$smarty.foreach.refs.last},{/if}{/foreach}</sup>
						{/if}
						{if $author.orcid}
							<a class="pdt-author__orcid" href="{$author.orcid.url|escape}" target="_blank" rel="noopener" title="ORCID {$author.orcid.id|escape}{if !$author.orcid.verified} ({translate key="plugins.themes.paideia.orcidUnverified"}){/if}">
								{$orcidSvg}<span class="pkp_screen_reader">ORCID {$author.orcid.id|escape}</span>
							</a>
						{/if}
						{if $author.primaryContact}
							<span class="pdt-author__contact" title="{translate key="plugins.themes.paideia.correspondingAuthor"}">
								<svg class="pdt-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg>
								<span class="pkp_screen_reader">{translate key="plugins.themes.paideia.correspondingAuthor"}</span>
							</span>
						{/if}
						{if $author.role}<span class="pdt-author__role">({$author.role|escape})</span>{/if}
					</li>
				{/foreach}
			</ul>

			{if $pdt.affiliations}
				<details class="pdt-affiliations"{if count($pdt.affiliations) <= 2} open{/if}>
					<summary>{translate key="plugins.themes.paideia.affiliations"}</summary>
					<ol>
						{foreach from=$pdt.affiliations item=aff}
							<li id="pdt-aff-{$aff.n}" value="{$aff.n}">
								{$aff.name|escape}
								{if $aff.ror}
									<a class="pdt-ror" href="{$aff.ror|escape}" target="_blank" rel="noopener" title="ROR"><span aria-hidden="true">ROR</span><span class="pkp_screen_reader">ROR {$aff.ror|escape}</span></a>
								{/if}
							</li>
						{/foreach}
					</ol>
				</details>
			{/if}
		{/if}

		{* Fechas, DOI y licencia *}
		<dl class="pdt-meta">
			{if $pdt.dates.received}
				<div><dt>{translate key="plugins.themes.paideia.dateReceived"}</dt><dd><time datetime="{$pdt.iso.received|escape}">{$pdt.dates.received|date_format:$dateFormatShort}</time></dd></div>
			{/if}
			{if $pdt.dates.accepted}
				<div><dt>{translate key="plugins.themes.paideia.dateAccepted"}</dt><dd><time datetime="{$pdt.iso.accepted|escape}">{$pdt.dates.accepted|date_format:$dateFormatShort}</time></dd></div>
			{/if}
			{if $pdt.dates.published}
				<div><dt>{translate key="plugins.themes.paideia.datePublished"}</dt><dd><time datetime="{$pdt.iso.published|escape}">{$pdt.dates.published|date_format:$dateFormatShort}</time></dd></div>
			{/if}
			{if $pdt.dates.updated}
				<div><dt>{translate key="plugins.themes.paideia.dateUpdated"}</dt><dd><time datetime="{$pdt.iso.updated|escape}">{$pdt.dates.updated|date_format:$dateFormatShort}</time></dd></div>
			{/if}
			{if $pdt.doi}
				<div class="pdt-meta__doi">
					<dt>DOI</dt>
					<dd>
						<a href="{$pdt.doi.url|escape}">{$pdt.doi.url|escape}</a>
						<button type="button" class="pdt-copy" data-pdt-copy="{$pdt.doi.url|escape}">
							<svg class="pdt-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/></svg>
							<span>{translate key="plugins.themes.paideia.copy"}</span>
						</button>
					</dd>
				</div>
			{/if}
		</dl>

		{* Acciones principales *}
		<div class="pdt-actions">
			{foreach from=$primaryGalleys item=galley}
				{if $pdtHtmlGalleyId && $galley->getId() == $pdtHtmlGalleyId}
					<a class="pdt-btn pdt-btn--ghost" href="#pdt-fulltext">{translate key="plugins.themes.paideia.readFulltext"}</a>
				{else}
					{include file="frontend/objects/galley_link.tpl" parent=$article publication=$publication galley=$galley purchaseFee=$currentJournal->getData('purchaseArticleFee') purchaseCurrency=$currentJournal->getData('currency')}
				{/if}
			{/foreach}
			{if $citation}
				<button type="button" class="pdt-btn pdt-btn--ghost" data-pdt-dialog="pdt-cite-dialog" aria-haspopup="dialog">
					<svg class="pdt-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6 17h3l2-4V7H5v6h3l-2 4zm8 0h3l2-4V7h-6v6h3l-2 4z"/></svg>
					{translate key="submission.howToCite"}
				</button>
			{/if}
			<div class="pdt-share">
				<button type="button" class="pdt-btn pdt-btn--ghost pdt-share-toggle" data-pdt-share data-title="{$publication->getLocalizedTitle()|strip_tags|escape}" data-url="{$pdt.url|escape}" aria-haspopup="true">
					<svg class="pdt-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18 16a3 3 0 0 0-2.4 1.2l-6.7-3.4a3 3 0 0 0 0-1.6l6.7-3.4A3 3 0 1 0 15 7l-6.7 3.4a3 3 0 1 0 0 3.2L15 17a3 3 0 1 0 3-1z"/></svg>
					{translate key="plugins.themes.paideia.share"}
				</button>
			</div>
		</div>

		{* Cómo citar: ventana con los formatos del plugin Citation Style Language *}
		{if $citation}
			<dialog class="pdt-dialog pdt-cite" id="pdt-cite-dialog" aria-labelledby="pdt-cite-title">
				<div class="pdt-dialog__head">
					<h2 class="pdt-dialog__title" id="pdt-cite-title">{translate key="submission.howToCite"}</h2>
					<button type="button" class="pdt-dialog__close" data-pdt-dialog-close aria-label="{translate key="common.close"}">
						<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 6.4 17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z"/></svg>
					</button>
				</div>
				<div class="pdt-dialog__body">
					{if $citationStyles && count($citationStyles) > 1}
						<label class="pdt-cite__label" for="pdt-cite-style">{translate key="plugins.themes.paideia.citeFormat"}</label>
						<select class="pdt-cite__select" id="pdt-cite-style" data-pdt-cite-style>
							{foreach from=$citationStyles item="citationStyle"}
								<option value="{url page="citationstylelanguage" op="get" path=$citationStyle.id params=$citationArgsJson}"{if ($pdtCitePrimary && $citationStyle.id == $pdtCitePrimary) || (!$pdtCitePrimary && $citationStyle.isPrimary)} selected{/if}>{$citationStyle.title|escape}</option>
							{/foreach}
						</select>
					{/if}
					<div class="pdt-cite__output" id="pdt-cite-output" aria-live="polite">{$citation}</div>
					<div class="pdt-cite__actions">
						<button type="button" class="pdt-btn pdt-btn--primary" data-pdt-copy-from="#pdt-cite-output">
							<svg class="pdt-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/></svg>
							<span>{translate key="plugins.themes.paideia.copyCitation"}</span>
						</button>
						{if $citationDownloads}
							<span class="pdt-cite__dl-label">{translate key="submission.howToCite.downloadCitation"}</span>
							{foreach from=$citationDownloads item="citationDownload"}
								<a class="pdt-btn pdt-btn--ghost" rel="nofollow" href="{url page="citationstylelanguage" op="download" path=$citationDownload.id params=$citationArgs}">
									<svg class="pdt-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M5 20h14v-2H5v2zM19 9h-4V3H9v6H5l7 7 7-7z"/></svg>
									{$citationDownload.title|escape}
								</a>
							{/foreach}
						{/if}
					</div>
				</div>
			</dialog>
		{/if}
	</header>

	{* ================================================================
	   Cuerpo: índice | contenido | panel lateral
	   ================================================================ *}
	<div class="pdt-article__layout">

		<nav class="pdt-toc" aria-label="{translate key="plugins.themes.paideia.toc"}">
			<details class="pdt-toc__details" open>
				<summary class="pdt-toc__title">{translate key="plugins.themes.paideia.toc"}</summary>
				<ol class="pdt-toc__list">
					{if $pdt.abstracts}<li><a href="#pdt-abstract">{translate key="common.abstract"}</a></li>{/if}
					{if $pdtFulltext}
						{foreach from=$pdtFulltext.toc item=item}
							<li class="pdt-toc__l{$item.level}"><a href="#{$item.id|escape}">{$item.text|escape}</a></li>
						{/foreach}
					{/if}
					{if ($parsedCitations && count($parsedCitations)) || $pdt.citationsRaw}<li><a href="#pdt-references">{translate key="submission.citations"}</a></li>{/if}
					<li><a href="#pdt-info">{translate key="plugins.themes.paideia.articleInfo"}</a></li>
				</ol>
			</details>
		</nav>

		<div class="pdt-article__main main_entry">

			{* Resúmenes por idioma *}
			{if $pdt.abstracts}
				<section class="pdt-section pdt-abstracts item abstract" id="pdt-abstract">
					<div class="pdt-section__head">
						<h2 class="pdt-section__title">{translate key="common.abstract"}</h2>
						{if count($pdt.abstracts) > 1}
							<div class="pdt-langtabs" role="tablist" aria-label="{translate key="common.language"}">
								{foreach from=$pdt.abstracts item=abs name=abs}
									<button type="button" role="tab" id="pdt-abs-tab-{$smarty.foreach.abs.index}" aria-controls="pdt-abs-{$smarty.foreach.abs.index}" aria-selected="{if $smarty.foreach.abs.first}true{else}false{/if}"{if !$smarty.foreach.abs.first} tabindex="-1"{/if}>{$abs.name|escape}</button>
								{/foreach}
							</div>
						{/if}
					</div>
					{foreach from=$pdt.abstracts item=abs name=abs}
						<div class="pdt-abstract" id="pdt-abs-{$smarty.foreach.abs.index}" lang="{$abs.langTag|escape}"{if count($pdt.abstracts) > 1} role="tabpanel" aria-labelledby="pdt-abs-tab-{$smarty.foreach.abs.index}"{if !$smarty.foreach.abs.first} hidden{/if}{/if}>
							<div class="pdt-prose">{$abs.html|strip_unsafe_html}</div>
							{if $abs.keywords}
								<div class="pdt-keywords">
									<h3 class="pdt-keywords__label">{translate key="common.keywords"}</h3>
									<ul>
										{foreach from=$abs.keywords item=keyword}
											<li><a href="{url page="search" op="search" query=$keyword}">{$keyword|escape}</a></li>
										{/foreach}
									</ul>
								</div>
							{/if}
						</div>
					{/foreach}
				</section>
			{/if}

			{call_hook name="Templates::Article::Main"}

			{* Texto completo inline *}
			{if $pdtFulltext}
				<section class="pdt-section pdt-fulltext" id="pdt-fulltext" aria-label="{translate key="plugins.themes.paideia.fulltext"}">
					{foreach from=$pdtFulltext.links item=href}
						<link rel="stylesheet" href="{$href|escape}">
					{/foreach}
					{if $pdtFulltext.css}<style>{$pdtFulltext.css}</style>{/if}
					<div class="pdt-galley pdt-galley--{$pdtFulltext.mode|escape}{if $pdtFulltext.mode == 'theme'} pdt-prose{/if}">
						{$pdtFulltext.html}
					</div>
				</section>
			{/if}

			{* Referencias *}
			{if ($parsedCitations && count($parsedCitations)) || $pdt.citationsRaw}
				<section class="pdt-section pdt-references item references" id="pdt-references">
					<h2 class="pdt-section__title">{translate key="submission.citations"}</h2>
					<ol class="pdt-references__list" data-pdt-references>
						{if ($parsedCitations && count($parsedCitations))}
							{foreach from=$parsedCitations item="parsedCitation"}
								<li>{$parsedCitation->getCitationWithLinks()|strip_unsafe_html}{call_hook name="Templates::Article::Details::Reference" citation=$parsedCitation}</li>
							{/foreach}
						{else}
							<li class="pdt-references__raw">{$pdt.citationsRaw|escape|nl2br}</li>
						{/if}
					</ol>
				</section>
			{/if}

			{* Biografías *}
			{assign var="pdtHasBios" value=false}
			{foreach from=$pdt.authors item=author}{if $author.bio}{assign var="pdtHasBios" value=true}{/if}{/foreach}
			{if $pdtHasBios}
				<section class="pdt-section pdt-bios item author_bios">
					<h2 class="pdt-section__title">{translate key="submission.authorBiographies"}</h2>
					{foreach from=$pdt.authors item=author}
						{if $author.bio}
							<div class="pdt-bio">
								<h3 class="pdt-bio__name">{$author.name|escape}</h3>
								<div class="pdt-prose">{$author.bio|strip_unsafe_html}</div>
							</div>
						{/if}
					{/foreach}
				</section>
			{/if}

			{* Información del artículo: al final del texto, como en PLOS o eLife *}
			<section class="pdt-section pdt-info" id="pdt-info">
				<h2 class="pdt-section__title">{translate key="plugins.themes.paideia.articleInfo"}</h2>

				{if $publication->getLocalizedData('dataAvailability')}
					<div class="pdt-info__block item dataAvailability" id="data-availability-statement">
						<h3 class="pdt-info__label">{translate key="submission.dataAvailability"}</h3>
						<div class="pdt-prose">{$publication->getLocalizedData('dataAvailability')|strip_unsafe_html}</div>
					</div>
				{/if}

				{if $currentContext->getLocalizedData('licenseTerms') || $publication->getData('licenseUrl') || $publication->getLocalizedData('copyrightHolder')}
					<div class="pdt-info__block pdt-license item copyright" id="pdt-license">
						<h3 class="pdt-info__label">{translate key="plugins.themes.paideia.licenseRights"}</h3>
						{if $publication->getLocalizedData('copyrightHolder')}
							<p>{translate key="submission.copyrightStatement" copyrightHolder=$publication->getLocalizedData('copyrightHolder') copyrightYear=$publication->getData('copyrightYear')}</p>
						{/if}
						{if $publication->getData('licenseUrl')}
							{if $ccLicenseBadge}<div class="pdt-license__badge">{$ccLicenseBadge}</div>{else}<p><a href="{$publication->getData('licenseUrl')|escape}">{$publication->getData('licenseUrl')|escape}</a></p>{/if}
						{/if}
						{if $currentContext->getLocalizedData('licenseTerms')}
							{* Los términos de la revista suelen ser largos: van plegados. *}
							<details class="pdt-license__more">
								<summary>{translate key="plugins.themes.paideia.licenseTerms"}</summary>
								<div class="pdt-license__terms pdt-prose">{$currentContext->getLocalizedData('licenseTerms')}</div>
							</details>
						{/if}
					</div>
				{/if}

				<dl class="pdt-info__list item issue">
					{if $issue}
						<div><dt>{translate key="issue.issue"}</dt><dd><a href="{url page="issue" op="view" path=$issue->getBestIssueId()}">{$issue->getIssueIdentification()|strip_tags|escape}</a></dd></div>
					{/if}
					{if $pdtSectionTitle}
						<div><dt>{translate key="section.section"}</dt><dd>{$pdtSectionTitle|escape}</dd></div>
					{/if}
					{if $categories}
						<div><dt>{translate key="category.category"}</dt><dd>
							{foreach from=$categories item=category name=cats}<a href="{url page="catalog" op="category" path=$category->getPath()|escape}">{$category->getLocalizedTitle()|escape}</a>{if !$smarty.foreach.cats.last}, {/if}{/foreach}
						</dd></div>
					{/if}
					{foreach from=$pubIdPlugins item=pubIdPlugin}
						{if $pubIdPlugin->getPubIdType() == 'doi'}{continue}{/if}
						{assign var=pubId value=$publication->getStoredPubId($pubIdPlugin->getPubIdType())}
						{if $pubId}
							<div><dt>{$pubIdPlugin->getPubIdDisplayType()|escape}</dt><dd>
								{assign var=pubIdUrl value=$pubIdPlugin->getResolvingURL($currentJournal->getId(), $pubId)}
								{if $pubIdUrl}<a href="{$pubIdUrl|escape}">{$pubIdUrl|escape}</a>{else}{$pubId|escape}{/if}
							</dd></div>
						{/if}
					{/foreach}
					{if $pdt.versions}
						<div><dt>{translate key="submission.versions"}</dt><dd>
							<ul class="pdt-versions">
								{foreach from=$pdt.versions item=v}
									<li>{if $v.isShown}<strong>v{$v.version|escape} · {$v.date|date_format:$dateFormatShort}</strong>{else}<a href="{$v.url|escape}">v{$v.version|escape} · {$v.date|date_format:$dateFormatShort}</a>{/if}</li>
								{/foreach}
							</ul>
						</dd></div>
					{/if}
				</dl>
			</section>
		</div>

		{* ============================================================
		   Panel lateral: lo que no está en el resto de la página
		   (métricas, citas, relacionados). Las descargas van arriba.
		   ============================================================ *}
		<aside class="pdt-rail entry_details" aria-label="{translate key="plugins.themes.paideia.moreAbout"}">

			{if $pdt.metrics || $pdtCitedBy}
				<section class="pdt-card pdt-metrics">
					<h2 class="pdt-card__title">{translate key="plugins.themes.paideia.metrics"}</h2>
					<dl>
						{if $pdt.metrics}
							<div><dt>{translate key="plugins.themes.paideia.views"}</dt><dd>{$pdt.metrics.viewsFmt|escape}</dd></div>
							<div><dt>{translate key="plugins.themes.paideia.downloads"}</dt><dd>{$pdt.metrics.downloadsFmt|escape}</dd></div>
						{/if}
						{if $pdtCitedBy}
							<div><dt>{translate key="plugins.themes.paideia.citations"}</dt><dd><a href="#pdt-citedby">{$pdtCitedBy.count|escape}</a></dd></div>
						{/if}
					</dl>
				</section>
			{/if}

			{if $pdtCitedBy && $pdtCitedBy.works}
				<section class="pdt-card pdt-citedby" id="pdt-citedby">
					<h2 class="pdt-card__title">{translate key="plugins.themes.paideia.citedBy"}</h2>
					<ul class="pdt-linklist">
						{foreach from=$pdtCitedBy.works item=w}
							<li>
								{if $w.url}<a href="{$w.url|escape}" target="_blank" rel="noopener">{$w.title|escape}</a>{else}{$w.title|escape}{/if}
								<span class="pdt-linklist__meta">{if $w.authors}{$w.authors|escape} · {/if}{if $w.source}<em>{$w.source|escape}</em> · {/if}{$w.year|escape}</span>
							</li>
						{/foreach}
					</ul>
					<p class="pdt-card__foot">
						{if $pdtCitedBy.count > $pdtCitedBy.works|@count}<a href="{$pdtCitedBy.url|escape}" target="_blank" rel="noopener">{translate key="plugins.themes.paideia.citedByAll" count=$pdtCitedBy.count}</a> · {/if}
						{translate key="plugins.themes.paideia.citedBySource"}
					</p>
				</section>
			{/if}

			{if $pdtRelated}
				<section class="pdt-card pdt-related">
					<h2 class="pdt-card__title">{translate key="plugins.themes.paideia.related"}</h2>
					<ul class="pdt-linklist">
						{foreach from=$pdtRelated item=r}
							<li>
								<a href="{$r.url|escape}">{$r.title|strip_unsafe_html}</a>
								<span class="pdt-linklist__meta">{$r.authors|escape}{if $r.year} · {$r.year|escape}{/if}</span>
							</li>
						{/foreach}
					</ul>
				</section>
			{/if}

			{if $issue}
				<section class="pdt-card pdt-fromissue">
					<h2 class="pdt-card__title">{translate key="plugins.themes.paideia.publishedIn"}</h2>
					<a class="pdt-fromissue__link" href="{url page="issue" op="view" path=$issue->getBestIssueId()}">
						{if $publication->getLocalizedData('coverImage')}
							{assign var="coverImage" value=$publication->getLocalizedData('coverImage')}
							<img src="{$publication->getLocalizedCoverImageUrl($article->getData('contextId'))|escape}" alt="{$coverImage.altText|escape|default:''}" loading="lazy">
						{elseif $issue->getLocalizedCoverImageUrl()}
							<img src="{$issue->getLocalizedCoverImageUrl()|escape}" alt="" loading="lazy">
						{/if}
						<span>{$issue->getIssueIdentification()|strip_tags|escape}</span>
					</a>
				</section>
			{/if}

			{if $supplementaryGalleys}
				<section class="pdt-card item galleys">
					<h2 class="pdt-card__title">{translate key="submission.additionalFiles"}</h2>
					<ul class="value supplementary_galleys_links">
						{foreach from=$supplementaryGalleys item=galley}
							<li>{include file="frontend/objects/galley_link.tpl" parent=$article publication=$publication galley=$galley isSupplementary="1"}</li>
						{/foreach}
					</ul>
				</section>
			{/if}

			{* Salida de otros plugins. Se quita lo que el theme ya resuelve:
			   "Cómo citar" del plugin Citation Style Language (va en la ventana). *}
			{capture assign="pdtDetailsHook"}{call_hook name="Templates::Article::Details"}{/capture}
			{if $pdtTheme}{assign var="pdtDetailsHook" value=$pdtTheme->removeByClass($pdtDetailsHook, ['citation'])}{/if}
			{if $pdtDetailsHook|trim}
				<div class="pdt-card pdt-plugins">{$pdtDetailsHook}</div>
			{/if}
		</aside>
	</div>
</article>
