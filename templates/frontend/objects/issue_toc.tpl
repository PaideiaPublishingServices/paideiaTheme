{**
 * plugins/themes/paideia/templates/frontend/objects/issue_toc.tpl
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * Sumario de un número: tapa, datos del número, número completo y artículos
 * por sección. Conserva las clases del Default (heading, cover, galleys,
 * sections) para no romper CSS personalizado de las revistas.
 *
 * @uses $issue Issue
 * @uses $issueGalleys array Galeradas del número completo
 * @uses $publishedSubmissions array Artículos por sección
 * @uses $heading string Encabezado de las secciones (h2 por defecto)
 * @uses $includeIssuePublishDate bool (3.5) false oculta la fecha
 *}
{if !$heading}
	{assign var="heading" value="h2"}
{/if}
{assign var="articleHeading" value="h3"}
{if $heading == "h3"}
	{assign var="articleHeading" value="h4"}
{elseif $heading == "h4"}
	{assign var="articleHeading" value="h5"}
{elseif $heading == "h5"}
	{assign var="articleHeading" value="h6"}
{/if}
<div class="obj_issue_toc pdt-issue">

	{if !$issue->getPublished()}
		{include file="frontend/components/notification.tpl" type="warning" messageKey="editor.issues.preview"}
	{/if}

	{assign var=issueCover value=$issue->getLocalizedCoverImageUrl()}
	{assign var=doiObject value=$issue->getData('doiObject')}
	<div class="heading pdt-issue__head{if !$issueCover} pdt-issue__head--nocover{/if}">

		{if $issueCover}
			<div class="cover pdt-issue__cover">
				{capture assign="defaultAltText"}{translate key="issue.viewIssueIdentification" identification=$issue->getIssueIdentification()|escape}{/capture}
				<img src="{$issueCover|escape}" alt="{$issue->getLocalizedCoverImageAltText()|escape|default:$defaultAltText}">
			</div>
		{/if}

		<div class="pdt-issue__info">
			{if $issue->hasDescription()}
				<div class="description pdt-prose">
					{$issue->getLocalizedDescription()|strip_unsafe_html}
				</div>
			{/if}

			<dl class="pdt-issue__meta">
				{if $doiObject}
					{assign var="doiUrl" value=$doiObject->getData('resolvingUrl')}
					<div class="pub_id doi"><dt class="type">DOI</dt><dd class="id"><a href="{$doiUrl|escape}">{$doiUrl|escape}</a></dd></div>
				{/if}
				{foreach from=$pubIdPlugins item=pubIdPlugin}
					{if $pubIdPlugin->getPubIdType() == 'doi'}{continue}{/if}
					{assign var=pubId value=$issue->getStoredPubId($pubIdPlugin->getPubIdType())}
					{if $pubId}
						{assign var="resolvingUrl" value=$pubIdPlugin->getResolvingURL($currentJournal->getId(), $pubId)}
						<div class="pub_id {$pubIdPlugin->getPubIdType()|escape}">
							<dt class="type">{$pubIdPlugin->getPubIdDisplayType()|escape}</dt>
							<dd class="id">{if $resolvingUrl}<a href="{$resolvingUrl|escape}">{$resolvingUrl|escape}</a>{else}{$pubId|escape}{/if}</dd>
						</div>
					{/if}
				{/foreach}
				{if $includeIssuePublishDate !== false && $issue->getDatePublished()}
					<div class="published"><dt class="label">{translate key="submissions.published"}</dt><dd class="value">{$issue->getDatePublished()|date_format:$dateFormatShort}</dd></div>
				{/if}
			</dl>

			{if $issueGalleys}
				<div class="galleys pdt-issue__galleys">
					<span class="pdt-issue__galleys-label" id="issueTocGalleyLabel">{translate key="issue.fullIssue"}</span>
					<ul class="galleys_links">
						{foreach from=$issueGalleys item=galley}
							<li>
								{include file="frontend/objects/galley_link.tpl" parent=$issue labelledBy="issueTocGalleyLabel" purchaseFee=$currentJournal->getData('purchaseIssueFee') purchaseCurrency=$currentJournal->getData('currency')}
							</li>
						{/foreach}
					</ul>
				</div>
			{/if}
		</div>
	</div>

	<div class="sections">
	{foreach name=sections from=$publishedSubmissions item=section}
		{if $section.articles}
			<section class="section pdt-issue__section">
				{if $section.title}
					<{$heading} class="pdt-issue__section-title">{$section.title|escape}</{$heading}>
				{/if}
				<ul class="cmp_article_list articles">
					{foreach from=$section.articles item=article}
						<li>
							{include file="frontend/objects/article_summary.tpl" heading=$articleHeading}
						</li>
					{/foreach}
				</ul>
			</section>
		{/if}
	{/foreach}
	</div>
</div>
