{**
 * templates/frontend/components/pdt_indexing.tpl
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * Bloque "Indexada en": índices del catálogo activados en las opciones del
 * theme (Indexes::CATALOG) y logos propios cargados por el editor.
 *
 * @uses $pdtIndexes array
 * @uses $pdtIndexingCustom string HTML
 * @uses $pdtIndexingPlace string 'top' | 'bottom'
 *}
{if $pdtIndexes || $pdtIndexingCustom}
	<section class="pdt-indexing pdt-indexing--{$pdtIndexingPlace|escape}" aria-labelledby="pdt-indexing-title-{$pdtIndexingPlace|escape}">
		<h2 id="pdt-indexing-title-{$pdtIndexingPlace|escape}" class="pdt-eyebrow">{translate key="plugins.themes.paideia.indexedIn"}</h2>
		<ul class="pdt-indexing__list">
			{foreach from=$pdtIndexes item=idx}
				<li class="pdt-index pdt-index--{$idx.key|escape}{if $idx.img} pdt-index--img{/if}">
					{if $idx.url}<a class="pdt-index__in" href="{$idx.url|escape}" target="_blank" rel="noopener">{else}<span class="pdt-index__in">{/if}
						{if $idx.img}
							<img src="{$idx.img|escape}" alt="{$idx.alt|escape}" loading="lazy">
						{else}
							<span class="pdt-index__name">{$idx.name|escape}</span>
							{if $idx.sub}<span class="pdt-index__sub">{$idx.sub|escape}</span>{/if}
						{/if}
					{if $idx.url}</a>{else}</span>{/if}
				</li>
			{/foreach}
		</ul>
		{if $pdtIndexingCustom}
			<div class="pdt-indexing__logos">{$pdtIndexingCustom|strip_unsafe_html}</div>
		{/if}
	</section>
{/if}
