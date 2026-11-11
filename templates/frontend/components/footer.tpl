{**
 * plugins/themes/paideia/templates/frontend/components/footer.tpl
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * Pie de página en columnas. Mantiene la estructura y los hooks del footer
 * de OJS (barra lateral, Templates::Common::Footer::PageFooter).
 *
 * El contenido de las columnas sale del "Pie de página" de OJS, repartido
 * por classes/Footer.php: una línea horizontal (<hr>) separa columnas.
 *
 * @uses $isFullWidth bool La página no muestra barras laterales
 * @uses $pdtFooter array Ver classes/Footer.php
 * @uses $pdtBlocks string bottom|sidebar
 * @uses $pdtTheme PaideiaThemePlugin (filterSidebar: quita bloques que el theme reemplaza)
 *
 * @hook Templates::Common::Sidebar []
 * @hook Templates::Common::Footer::PageFooter []
 *}

	</div><!-- pkp_structure_main -->

	{* Indexación abajo, en todas las páginas (opción del theme) *}
	{if $pdtIndexingPosition == 'bottom'}
		<div class="pdt-indexing-wrap">
			{include file="frontend/components/pdt_indexing.tpl" pdtIndexingPlace="bottom"}
		</div>
	{/if}

	{* Barras laterales *}
	{if empty($isFullWidth)}
		{capture assign="sidebarCode"}{call_hook name="Templates::Common::Sidebar"}{/capture}
		{if $pdtTheme}{assign var="sidebarCode" value=$pdtTheme->filterSidebar($sidebarCode)}{/if}
		{if $sidebarCode|trim}
			<div class="pkp_structure_sidebar left pdt-sidebar pdt-sidebar--{$pdtBlocks|default:'bottom'|escape}" role="complementary">
				{$sidebarCode}
			</div><!-- pkp_sidebar.left -->
		{/if}
	{/if}
</div><!-- pkp_structure_content -->

<div class="pkp_structure_footer_wrapper" role="contentinfo">
	<a id="pkp_content_footer"></a>

	<div class="pkp_structure_footer">

		{if $pdtFooter.identity || $pdtFooter.columns}
			<div class="pkp_footer_content pdt-footer__grid pdt-footer__grid--{$pdtFooter.grid|escape}">
				{if $pdtFooter.identity}
					{assign var="pdtId" value=$pdtFooter.identity}
					<div class="pdt-footer__col pdt-footer__identity">
						<p class="pdt-footer__name">{$pdtId.name|escape}</p>
						<ul class="pdt-footer__facts">
							{if $pdtId.onlineIssn}<li>{translate key="plugins.themes.paideia.eissn"} {$pdtId.onlineIssn|escape}</li>{/if}
							{if $pdtId.printIssn}<li>{translate key="plugins.themes.paideia.pissn"} {$pdtId.printIssn|escape}</li>{/if}
							{if $pdtId.publisher}
								<li>{if $pdtId.publisherUrl}<a href="{$pdtId.publisherUrl|escape}">{$pdtId.publisher|escape}</a>{else}{$pdtId.publisher|escape}{/if}</li>
							{/if}
						</ul>
						{if !$pdtFooter.separateLinks}
							<ul class="pdt-footer__links">
								{foreach from=$pdtId.links item=link}
									<li><a href="{$link.url|escape}">{$link.label|escape}</a></li>
								{/foreach}
							</ul>
						{/if}
					</div>
					{if $pdtFooter.separateLinks}
						<nav class="pdt-footer__col pdt-footer__nav" aria-label="{translate key="plugins.themes.paideia.journal"}">
							<p class="pdt-footer__heading">{translate key="plugins.themes.paideia.journal"}</p>
							<ul class="pdt-footer__links pdt-footer__links--stack">
								{foreach from=$pdtId.links item=link}
									<li><a href="{$link.url|escape}">{$link.label|escape}</a></li>
								{/foreach}
							</ul>
						</nav>
					{/if}
				{/if}
				{foreach from=$pdtFooter.columns item=column}
					<div class="pdt-footer__col">{$column}</div>
				{/foreach}
			</div>
		{elseif $pageFooter}
			<div class="pkp_footer_content">{$pageFooter}</div>
		{/if}

		<div class="pdt-footer__bar">
			<div class="pkp_brand_footer">
				<a href="{url page="about" op="aboutThisPublishingSystem"}">
					<img alt="{translate key="about.aboutThisPublishingSystem"}" src="{$baseUrl}/{$brandImage}">
				</a>
			</div>
		</div>
	</div>
</div><!-- pkp_structure_footer_wrapper -->

</div><!-- pkp_structure_page -->

{load_script context="frontend"}

{call_hook name="Templates::Common::Footer::PageFooter"}
</body>
</html>
