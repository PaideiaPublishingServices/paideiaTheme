{**
 * plugins/themes/paideia/templates/frontend/pages/indexJournal.tpl
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * Inicio de la revista: portada, indexación, pestañas de artículos
 * (recientes / más vistos / más descargados), número actual y buscador de
 * números.
 *
 * @uses $pdtHome array Ver classes/HomeData.php
 * @uses $pdtIndexing string HTML del bloque de indexación (opción del theme)
 * @uses $pdtAnnouncements array Avisos normalizados (Compat::announcements)
 *
 * @hook Templates::Index::journal []
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$currentJournal->getLocalizedName()}

<div class="page_index_journal pdt-home pdt-home--{$pdtHome.layout|escape}">

	{call_hook name="Templates::Index::journal"}

	{if $highlights && $highlights->count()}
		{include file="frontend/components/highlights.tpl" highlights=$highlights}
	{/if}

	{* Portada *}
	<section class="pdt-hero" aria-labelledby="pdt-hero-title">
		<div class="pdt-hero__text">
			<h2 id="pdt-hero-title" class="pdt-hero__title">{$currentJournal->getLocalizedName()|escape}</h2>
			{if $currentJournal->getLocalizedData('description')}
				<div class="pdt-hero__desc pdt-prose">{$currentJournal->getLocalizedData('description')|strip_unsafe_html}</div>
				<a class="pdt-hero__more" href="{url page="about"}">{translate key="about.aboutContext"} →</a>
			{/if}
			<ul class="pdt-hero__facts">
				{if $currentJournal->getData('onlineIssn')}<li><span>{translate key="plugins.themes.paideia.eissn"}</span> {$currentJournal->getData('onlineIssn')|escape}</li>{/if}
				{if $currentJournal->getData('printIssn')}<li><span>{translate key="plugins.themes.paideia.pissn"}</span> {$currentJournal->getData('printIssn')|escape}</li>{/if}
				{if $currentJournal->getData('publisherInstitution')}<li><span>{translate key="plugins.themes.paideia.publisher"}</span> {$currentJournal->getData('publisherInstitution')|escape}</li>{/if}
			</ul>
			<div class="pdt-hero__cta">
				<a class="pdt-btn pdt-btn--primary" href="{url page="about" op="submissions"}">{translate key="plugins.themes.paideia.submit"}</a>
				{if $issue}<a class="pdt-btn pdt-btn--ghost" href="{url page="issue" op="current"}">{translate key="journal.currentIssue"}</a>{/if}
				<a class="pdt-btn pdt-btn--ghost" href="{url page="issue" op="archive"}">{translate key="navigation.archives"}</a>
			</div>
		</div>
		{if $issue && $issue->getLocalizedCoverImageUrl()}
			<a class="pdt-hero__cover" href="{url page="issue" op="view" path=$issue->getBestIssueId()}">
				<img src="{$issue->getLocalizedCoverImageUrl()|escape}" alt="{$issue->getLocalizedCoverImageAltText()|escape|default:$issue->getIssueIdentification()|strip_tags|escape}">
				<span class="pdt-hero__cover-label">{$issue->getIssueIdentification()|strip_tags|escape}</span>
			</a>
		{elseif $activeTheme && !$activeTheme->getOption('useHomepageImageAsHeader') && $homepageImage}
			<div class="pdt-hero__cover">
				<img src="{$publicFilesDir}/{$homepageImage.uploadName|escape:"url"}"{if $homepageImage.altText} alt="{$homepageImage.altText|escape}"{else} alt=""{/if}>
			</div>
		{/if}
	</section>

	{* Indexación (arriba; con la opción "abajo" la muestra footer.tpl) *}
	{if $pdtIndexingPosition == 'top'}
		{include file="frontend/components/pdt_indexing.tpl" pdtIndexingPlace="top"}
	{/if}

	{* Publicación continua: número en curso + último número cerrado *}
	{if $pdtHome.layout == 'continuous' && $pdtHome.open}
		<section class="pdt-section pdt-openissue" aria-labelledby="pdt-openissue-title">
			<div class="pdt-section__head">
				<div>
					<p class="pdt-openissue__badge"><span class="pdt-openissue__dot" aria-hidden="true"></span>{translate key="plugins.themes.paideia.openIssue"}</p>
					<h2 id="pdt-openissue-title" class="pdt-section__title"><a href="{$pdtHome.open.url|escape}">{$pdtHome.open.label|escape}</a></h2>
					<p class="pdt-openissue__note">{translate key="plugins.themes.paideia.openIssueNote"}</p>
				</div>
				{if $pdtHome.closed}
					<a class="pdt-closedissue" href="{$pdtHome.closed.url|escape}">
						{if $pdtHome.closed.cover}<img src="{$pdtHome.closed.cover|escape}" alt="" loading="lazy">{/if}
						<span>
							<span class="pdt-closedissue__kicker">{translate key="plugins.themes.paideia.closedIssue"}</span>
							<span class="pdt-closedissue__label">{$pdtHome.closed.label|escape}</span>
						</span>
					</a>
				{/if}
			</div>
			{if $pdtHome.open.cards}
				<div class="pdt-cards">
					{foreach from=$pdtHome.open.cards item=card}
						{include file="frontend/components/pdt_card.tpl" card=$card metricLabel=null}
					{/foreach}
				</div>
				{if $pdtHome.open.count > $pdtHome.open.cards|@count}
					<p class="pdt-openissue__all"><a class="pdt-link-more" href="{$pdtHome.open.url|escape}">{translate key="plugins.themes.paideia.openIssueAll" count=$pdtHome.open.count}</a></p>
				{/if}
			{else}
				<p class="pdt-openissue__empty">{translate key="plugins.themes.paideia.openIssueEmpty"}</p>
			{/if}
		</section>
	{/if}

	{* Pestañas de artículos *}
	{if $pdtHome.tabs}
		<section class="pdt-section pdt-hometabs" aria-labelledby="pdt-articles-title">
			<div class="pdt-section__head">
				<h2 id="pdt-articles-title" class="pdt-section__title">{translate key="plugins.themes.paideia.articles"}</h2>
				{if count($pdtHome.tabs) > 1}
					<div class="pdt-tabs" role="tablist">
						{foreach from=$pdtHome.tabs key=tabKey item=cards name=tabs}
							<button type="button" role="tab" id="pdt-tab-{$tabKey}" aria-controls="pdt-panel-{$tabKey}" aria-selected="{if $smarty.foreach.tabs.first}true{else}false{/if}"{if !$smarty.foreach.tabs.first} tabindex="-1"{/if}>{translate key="plugins.themes.paideia.tab.$tabKey"}</button>
						{/foreach}
					</div>
				{/if}
			</div>
			{foreach from=$pdtHome.tabs key=tabKey item=cards name=tabs}
				<div class="pdt-cards" id="pdt-panel-{$tabKey}"{if count($pdtHome.tabs) > 1} role="tabpanel" aria-labelledby="pdt-tab-{$tabKey}"{if !$smarty.foreach.tabs.first} hidden{/if}{/if}>
					{foreach from=$cards item=card}
						{if $tabKey == 'viewed'}{capture assign="metricLabel"}{translate key="plugins.themes.paideia.views"}{/capture}{elseif $tabKey == 'downloaded'}{capture assign="metricLabel"}{translate key="plugins.themes.paideia.downloads"}{/capture}{else}{assign var="metricLabel" value=null}{/if}
						{include file="frontend/components/pdt_card.tpl" card=$card metricLabel=$metricLabel}
					{/foreach}
				</div>
			{/foreach}
		</section>
	{/if}

	{* Avisos: tarjetas en lugar de la lista del Default *}
	{if $pdtAnnouncements}
		<section class="pdt-section pdt-news" aria-labelledby="pdt-news-title">
			<div class="pdt-section__head">
				<h2 class="pdt-section__title" id="pdt-news-title">{translate key="announcement.announcements"}</h2>
				<a href="{url page="announcement"}" class="pdt-link-more">{translate key="plugins.themes.paideia.allAnnouncements"}</a>
			</div>
			<div class="pdt-news__grid">
				{foreach from=$pdtAnnouncements item=ann}
					<article class="pdt-newscard">
						{if $ann.date}<time class="pdt-newscard__date" datetime="{$ann.date|escape}">{$ann.date|date_format:$dateFormatShort}</time>{/if}
						<h3 class="pdt-newscard__title"><a href="{$ann.url|escape}">{$ann.title|escape}</a></h3>
						{if $ann.summary}<p class="pdt-newscard__summary">{$ann.summary|strip_tags|truncate:220:"…"}</p>{/if}
					</article>
				{/foreach}
			</div>
		</section>
	{/if}

	{* Número actual *}
	{if $issue && $pdtHome.layout == 'issue'}
		<section class="pdt-section current_issue" aria-labelledby="homepageIssue">
			<div class="pdt-section__head">
				<h2 class="pdt-section__title" id="homepageIssue">{translate key="journal.currentIssue"}</h2>
				<a href="{url page="issue" op="archive"}" class="pdt-link-more read_more">{translate key="journal.viewAllIssues"}</a>
			</div>
			<p class="current_issue_title">{$issue->getIssueIdentification()|strip_tags|escape}</p>
			{include file="frontend/objects/issue_toc.tpl" heading="h3"}
		</section>
	{/if}

	{* Buscador de números *}
	{if $pdtHome.issuesByYear}
		<section class="pdt-section pdt-issuefinder" aria-labelledby="pdt-issuefinder-title">
			<div class="pdt-section__head">
				<h2 class="pdt-section__title" id="pdt-issuefinder-title">{translate key="plugins.themes.paideia.findIssue"}</h2>
				<a href="{url page="issue" op="archive"}" class="pdt-link-more">{translate key="journal.viewAllIssues"}</a>
			</div>
			<div class="pdt-issuefinder__grid">
				{foreach from=$pdtHome.issuesByYear key=year item=issues name=years}
					<details class="pdt-issuefinder__year"{if $smarty.foreach.years.first} open{/if}>
						<summary>{$year|escape}</summary>
						<ul>
							{foreach from=$issues item=i}
								<li><a href="{$i.url|escape}">{$i.label|escape}</a></li>
							{/foreach}
						</ul>
					</details>
				{/foreach}
			</div>
		</section>
	{/if}

	{if $additionalHomeContent}
		<div class="additional_content pdt-prose">
			{$additionalHomeContent}
		</div>
	{/if}
</div>

{include file="frontend/components/footer.tpl"}
