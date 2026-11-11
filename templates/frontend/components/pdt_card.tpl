{**
 * templates/frontend/components/pdt_card.tpl
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * Tarjeta de artículo del inicio.
 *
 * @uses $card array Datos de HomeData::cards()
 * @uses $metricLabel string|null Etiqueta de la métrica (vistas/descargas)
 *}
<article class="pdt-cardart">
	<div class="pdt-cardart__meta">
		{if $card.section}<span class="pdt-cardart__section">{$card.section|escape}</span>{/if}
		{if $card.datePublished}<time datetime="{$card.datePublished|escape}">{$card.datePublished|date_format:$dateFormatShort}</time>{/if}
	</div>
	<h3 class="pdt-cardart__title"><a href="{$card.url|escape}">{$card.title|strip_unsafe_html}</a></h3>
	{if $card.authors}<p class="pdt-cardart__authors">{$card.authors|escape}</p>{/if}
	<div class="pdt-cardart__foot">
		{if $card.doi}<a class="pdt-cardart__doi" href="{$card.doi.url|escape}">DOI {$card.doi.id|escape}</a>{/if}
		{if $card.metric !== null && $metricLabel}
			<span class="pdt-cardart__metric">{$card.metric|escape} {$metricLabel|escape}</span>
		{/if}
		{if $card.pdfUrl}<a class="pdt-cardart__pdf" href="{$card.pdfUrl|escape}">PDF</a>{/if}
	</div>
</article>
