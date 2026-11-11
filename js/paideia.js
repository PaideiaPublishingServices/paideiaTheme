/**
 * plugins/themes/paideia/js/paideia.js
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * Comportamiento del theme. JavaScript sin dependencias (no usa jQuery,
 * aunque el theme padre lo cargue). Todo es mejora progresiva: sin JS la
 * página se lee completa.
 */
(function () {
	'use strict';

	var PDT = window.PDT || { i18n: {} };
	var t = function (key, fallback) { return (PDT.i18n && PDT.i18n[key]) || fallback; };
	var $ = function (sel, root) { return (root || document).querySelector(sel); };
	var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

	// ---------------------------------------------------------------------
	// 1. Crédito del pie (condición de la licencia, ver LICENSE y
	//    classes/Branding.php). Si el crédito no está o no se ve, el theme
	//    se desactiva y el sitio vuelve al aspecto del theme Default.
	// ---------------------------------------------------------------------
	function creditOk() {
		var el = $('[data-pdt-credit]');
		if (!el) { return false; }
		var link = el.querySelector('a[href*="paideiastudio.net"]');
		if (!link || !/Paideia/.test(link.textContent || '')) { return false; }
		var node = el;
		while (node && node.nodeType === 1) {
			var cs = window.getComputedStyle(node);
			if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity) < 0.3 ||
				cs.contentVisibility === 'hidden') {
				return false;
			}
			node = node.parentElement;
		}
		var logo = link.querySelector('.pdt-credit__logo');
		if (!logo) { return false; }
		var lc = window.getComputedStyle(logo);
		if (lc.display === 'none' || lc.visibility === 'hidden' || parseFloat(lc.opacity) < 0.3) { return false; }
		var r = el.getBoundingClientRect();
		var lr = link.getBoundingClientRect();
		var gr = logo.getBoundingClientRect();
		var fs = parseFloat(window.getComputedStyle(link).fontSize);
		return r.width > 20 && r.height > 8 && lr.width > 20 && fs >= 9 && gr.width >= 12 && gr.height >= 12;
	}
	function disableTheme() {
		document.body.classList.remove('pdt');
		document.body.classList.add('pdt-off');
		if (window.console) {
			console.warn('[Paideia theme] El crédito del pie de página fue quitado u ocultado. ' +
				'El theme se desactiva: la licencia (GPL v3, sección 7b) exige conservarlo. https://paideiastudio.net/');
		}
	}
	function guardCredit() {
		if (!creditOk()) { disableTheme(); return; }
		if ('MutationObserver' in window) {
			var mo = new MutationObserver(function () {
				if (document.body.classList.contains('pdt') && !creditOk()) { disableTheme(); mo.disconnect(); }
			});
			var footer = $('.pkp_structure_footer_wrapper') || document.body;
			mo.observe(footer, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
			// Estilos agregados después (CSS personalizado, extensiones, scripts).
			var recheck = function () {
				window.setTimeout(function () {
					if (document.body.classList.contains('pdt') && !creditOk()) { disableTheme(); }
				}, 150);
			};
			new MutationObserver(recheck).observe(document.head, { childList: true, subtree: true, characterData: true });
			new MutationObserver(recheck).observe(document.body, { attributes: true, attributeFilter: ['style', 'class'] });
		}
		window.addEventListener('load', function () { if (document.body.classList.contains('pdt') && !creditOk()) { disableTheme(); } });
		// Segunda comprobación cuando ya cargaron estilos tardíos (CSS personalizado).
		window.setTimeout(function () { if (document.body.classList.contains('pdt') && !creditOk()) { disableTheme(); } }, 2500);
	}

	// ---------------------------------------------------------------------
	// 2. Modo oscuro
	// ---------------------------------------------------------------------
	var sunIcon = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM11 1h2v3h-2zm0 19h2v3h-2zM3.5 4.9l1.4-1.4 2.1 2.1-1.4 1.4zm12.7 12.7 1.4-1.4 2.1 2.1-1.4 1.4zM1 11h3v2H1zm19 0h3v2h-3zM3.5 19.1l2.1-2.1 1.4 1.4-2.1 2.1zM16.2 5.6l2.1-2.1 1.4 1.4-2.1 2.1z"/></svg>';
	var moonIcon = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';

	function isDark() {
		var s = document.documentElement.getAttribute('data-pdt-scheme');
		if (s) { return s === 'dark'; }
		return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
	}
	function initDarkMode() {
		if (!PDT.darkMode || !document.documentElement.hasAttribute('data-pdt-dm')) { return; }
		var wrapper = $('.pkp_head_wrapper');
		if (!wrapper) { return; }
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'pdt-scheme-toggle';
		var paint = function () {
			var dark = isDark();
			btn.innerHTML = dark ? sunIcon : moonIcon;
			btn.setAttribute('aria-label', dark ? t('lightMode', 'Light mode') : t('darkMode', 'Dark mode'));
			btn.title = btn.getAttribute('aria-label');
			$$('.pdt-galley--galley').forEach(function (g) { g.classList.toggle('dark', dark); });
		};
		btn.addEventListener('click', function () {
			var next = isDark() ? 'light' : 'dark';
			document.documentElement.setAttribute('data-pdt-scheme', next);
			try { localStorage.setItem('pdt-scheme', next); } catch (e) {}
			paint();
		});
		paint();
		// Escritorio: junto a "Buscar". Móvil: junto al botón de menú (el menú
		// está cerrado y el botón quedaría oculto).
		var mq = window.matchMedia ? window.matchMedia('(max-width: 991px)') : null;
		var place = function () {
			var nameWrap = $('.pkp_site_name_wrapper', wrapper);
			var search = $('.pkp_navigation_search_wrapper', wrapper);
			if (mq && mq.matches && nameWrap) {
				nameWrap.appendChild(btn);
			} else if (search && search.parentNode) {
				search.parentNode.insertBefore(btn, search.nextSibling);
			} else {
				wrapper.appendChild(btn);
			}
		};
		place();
		if (mq) {
			if (mq.addEventListener) { mq.addEventListener('change', place); } else if (mq.addListener) { mq.addListener(place); }
		}
	}

	// ---------------------------------------------------------------------
	// 3. Pestañas accesibles (role="tablist")
	// ---------------------------------------------------------------------
	function initTabs() {
		$$('[role="tablist"]').forEach(function (list) {
			var tabs = $$('[role="tab"]', list);
			var select = function (tab, focus) {
				tabs.forEach(function (other) {
					var on = other === tab;
					other.setAttribute('aria-selected', on ? 'true' : 'false');
					other.tabIndex = on ? 0 : -1;
					var panel = document.getElementById(other.getAttribute('aria-controls'));
					if (panel) { panel.hidden = !on; }
				});
				if (focus) { tab.focus(); }
			};
			tabs.forEach(function (tab, i) {
				tab.addEventListener('click', function () { select(tab); });
				tab.addEventListener('keydown', function (e) {
					var k = e.key, n = null;
					if (k === 'ArrowRight') { n = tabs[(i + 1) % tabs.length]; }
					if (k === 'ArrowLeft') { n = tabs[(i - 1 + tabs.length) % tabs.length]; }
					if (k === 'Home') { n = tabs[0]; }
					if (k === 'End') { n = tabs[tabs.length - 1]; }
					if (n) { e.preventDefault(); select(n, true); }
				});
			});
		});
	}

	// ---------------------------------------------------------------------
	// 4. Copiar (DOI, enlace)
	// ---------------------------------------------------------------------
	function copyText(text, host) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		host = host || document.body;
		return new Promise(function (resolve, reject) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			host.appendChild(ta);
			ta.select();
			try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); }
			host.removeChild(ta);
		});
	}
	function initCopy() {
		document.addEventListener('click', function (e) {
			var btn = e.target.closest && e.target.closest('[data-pdt-copy], [data-pdt-copy-from]');
			if (!btn) { return; }
			var text = btn.getAttribute('data-pdt-copy');
			if (text === null) {
				var src = $(btn.getAttribute('data-pdt-copy-from'));
				text = src ? (src.innerText || src.textContent || '').trim() : '';
			}
			copyText(text, btn.closest('dialog') || document.body).then(function () {
				var label = btn.querySelector('span') || btn;
				var prev = label.textContent;
				label.textContent = t('copied', 'Copied');
				btn.setAttribute('data-copied', '');
				window.setTimeout(function () { label.textContent = prev; btn.removeAttribute('data-copied'); }, 1800);
			});
		});
	}

	// ---------------------------------------------------------------------
	// 5. Compartir (sin scripts de terceros)
	// ---------------------------------------------------------------------
	function initShare() {
		$$('[data-pdt-share]').forEach(function (btn) {
			var url = btn.getAttribute('data-url') || location.href;
			var title = btn.getAttribute('data-title') || document.title;
			btn.setAttribute('aria-expanded', 'false');
			btn.addEventListener('click', function () {
				if (navigator.share && window.matchMedia('(pointer: coarse)').matches) {
					navigator.share({ title: title, url: url }).catch(function () {});
					return;
				}
				var open = btn.parentNode.querySelector('.pdt-share-menu');
				if (open) { open.remove(); btn.setAttribute('aria-expanded', 'false'); return; }
				var u = encodeURIComponent(url), ti = encodeURIComponent(title);
				var items = [
					['LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' + u],
					['Bluesky', 'https://bsky.app/intent/compose?text=' + ti + '%20' + u],
					['X', 'https://x.com/intent/post?text=' + ti + '&url=' + u],
					['Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' + u],
					['WhatsApp', 'https://wa.me/?text=' + ti + '%20' + u],
					['Email', 'mailto:?subject=' + ti + '&body=' + u]
				];
				var menu = document.createElement('ul');
				menu.className = 'pdt-share-menu';
				items.forEach(function (it) {
					var li = document.createElement('li');
					var a = document.createElement('a');
					a.href = it[1];
					a.target = '_blank';
					a.rel = 'noopener';
					a.textContent = it[0];
					li.appendChild(a);
					menu.appendChild(li);
				});
				var li = document.createElement('li');
				var copy = document.createElement('button');
				copy.type = 'button';
				copy.setAttribute('data-pdt-copy', url);
				copy.innerHTML = '<span>' + t('copy', 'Copy') + ' URL</span>';
				li.appendChild(copy);
				menu.appendChild(li);
				btn.insertAdjacentElement('afterend', menu);
				btn.setAttribute('aria-expanded', 'true');
				// Si no entra a la derecha (móvil), se alinea al borde derecho del botón.
				if (menu.getBoundingClientRect().right > document.documentElement.clientWidth - 8) {
					menu.classList.add('pdt-share-menu--end');
				}
				var close = function (ev) {
					if (ev.type === 'keydown' && ev.key !== 'Escape') { return; }
					if (ev.type === 'click' && (menu.contains(ev.target) || btn.contains(ev.target))) { return; }
					menu.remove();
					btn.setAttribute('aria-expanded', 'false');
					document.removeEventListener('click', close);
					document.removeEventListener('keydown', close);
				};
				window.setTimeout(function () {
					document.addEventListener('click', close);
					document.addEventListener('keydown', close);
				}, 0);
			});
		});
	}

	// ---------------------------------------------------------------------
	// 6. Índice lateral con resaltado de la sección visible
	// ---------------------------------------------------------------------
	function initToc() {
		var links = $$('.pdt-toc__list a[href^="#"]');
		if (!links.length || !('IntersectionObserver' in window)) { return; }
		var map = {};
		var targets = [];
		links.forEach(function (a) {
			var el = document.getElementById(decodeURIComponent(a.getAttribute('href').slice(1)));
			if (el) { map[el.id] = a; targets.push(el); }
		});
		var current = null;
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) {
					if (current) { current.removeAttribute('aria-current'); }
					current = map[en.target.id];
					if (current) { current.setAttribute('aria-current', 'true'); }
				}
			});
		}, { rootMargin: '-20% 0px -70% 0px' });
		targets.forEach(function (el) { io.observe(el); });

		// En pantallas chicas, el índice se cierra al elegir una sección.
		links.forEach(function (a) {
			a.addEventListener('click', function () {
				var d = a.closest('details');
				if (d && window.matchMedia('(max-width: 1199px)').matches) { d.open = false; }
			});
		});
		var d = $('.pdt-toc__details');
		if (d && window.matchMedia('(max-width: 1199px)').matches) { d.open = false; }
	}

	// ---------------------------------------------------------------------
	// 7. Referencias: mostrar las primeras 10 y "ver todas"
	// ---------------------------------------------------------------------
	function initReferences() {
		$$('[data-pdt-references]').forEach(function (list) {
			var items = list.children.length;
			if (items <= 12) { return; }
			list.classList.add('pdt-references--collapsed');
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'pdt-btn pdt-references__more';
			btn.textContent = (PDT.i18n.showAllRefs || 'Ver todas') + ' (' + items + ')';
			btn.addEventListener('click', function () {
				list.classList.remove('pdt-references--collapsed');
				btn.remove();
			});
			list.insertAdjacentElement('afterend', btn);
			// Si se llega a una referencia por enlace, expandir.
			var expandIfTarget = function () {
				if (location.hash && list.querySelector(location.hash.replace(/([^\w#-])/g, '\\$1'))) {
					list.classList.remove('pdt-references--collapsed');
					btn.remove();
				}
			};
			window.addEventListener('hashchange', expandIfTarget);
			expandIfTarget();
		});
	}

	// ---------------------------------------------------------------------
	// 8. Texto completo: figuras ampliables y tooltips de citas
	// ---------------------------------------------------------------------
	function initLightbox() {
		var galley = $('.pdt-galley');
		if (!galley || typeof HTMLDialogElement === 'undefined') { return; }
		var dialog = document.createElement('dialog');
		dialog.className = 'pdt-lightbox';
		dialog.innerHTML = '<img alt=""><p></p><button type="button" class="pdt-btn"></button>';
		document.body.appendChild(dialog);
		var img = dialog.querySelector('img'), cap = dialog.querySelector('p'), close = dialog.querySelector('button');
		close.textContent = t('close', 'Close');
		close.addEventListener('click', function () { dialog.close(); });
		img.addEventListener('click', function () { dialog.close(); });
		dialog.addEventListener('click', function (e) { if (e.target === dialog) { dialog.close(); } });
		galley.addEventListener('click', function (e) {
			var target = e.target;
			if (target.tagName !== 'IMG' || target.closest('a')) { return; }
			if (target.naturalWidth && target.naturalWidth < 160) { return; }
			img.src = target.currentSrc || target.src;
			img.alt = target.alt || '';
			var fig = target.closest('figure');
			var fc = fig && fig.querySelector('figcaption');
			cap.textContent = fc ? fc.textContent.trim() : (target.alt || '');
			cap.hidden = !cap.textContent;
			dialog.showModal();
		});
		$$('img', galley).forEach(function (im) { if (!im.title) { im.title = t('enlarge', 'Enlarge'); } });
	}

	function initCitationTips() {
		var galley = $('.pdt-galley');
		if (!galley) { return; }
		var tip = null;
		var hide = function () { if (tip) { tip.remove(); tip = null; } };
		var show = function (a) {
			var id = decodeURIComponent((a.getAttribute('href') || '').slice(1));
			var ref = id && document.getElementById(id);
			if (!ref || ref.closest('.pdt-toc')) { return; }
			// Sólo referencias bibliográficas, no encabezados ni figuras.
			if (/^H[1-6]$/.test(ref.tagName) || ref.tagName === 'FIGURE' || ref.tagName === 'TABLE') { return; }
			var text = (ref.textContent || '').trim();
			if (!text || text.length > 1200) { return; }
			hide();
			tip = document.createElement('div');
			tip.className = 'pdt-tip';
			tip.setAttribute('role', 'tooltip');
			tip.textContent = text;
			var doi = text.match(/10\.\d{4,9}\/[^\s"<>]+/);
			if (doi) {
				var link = document.createElement('a');
				link.href = 'https://doi.org/' + doi[0].replace(/[.,;)]+$/, '');
				link.textContent = ' DOI';
				link.target = '_blank';
				link.rel = 'noopener';
				tip.appendChild(document.createTextNode(' '));
				tip.appendChild(link);
			}
			document.body.appendChild(tip);
			var r = a.getBoundingClientRect();
			var left = Math.max(16, Math.min(r.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - tip.offsetWidth - 16));
			tip.style.left = left + 'px';
			tip.style.top = (r.bottom + window.scrollY + 8) + 'px';
			a.setAttribute('aria-describedby', 'pdt-tip');
			tip.id = 'pdt-tip';
		};
		var timer = null;
		galley.addEventListener('mouseover', function (e) {
			var a = e.target.closest && e.target.closest('a[href^="#"]');
			if (!a) { return; }
			clearTimeout(timer);
			timer = setTimeout(function () { show(a); }, 150);
		});
		galley.addEventListener('mouseout', function (e) {
			var a = e.target.closest && e.target.closest('a[href^="#"]');
			if (!a) { return; }
			clearTimeout(timer);
			timer = setTimeout(function () { if (tip && !tip.matches(':hover')) { hide(); } }, 250);
		});
		galley.addEventListener('focusin', function (e) {
			var a = e.target.closest && e.target.closest('a[href^="#"]');
			if (a) { show(a); }
		});
		galley.addEventListener('focusout', hide);
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { hide(); } });
		document.addEventListener('mouseover', function (e) {
			if (tip && !tip.contains(e.target) && !(e.target.closest && e.target.closest('.pdt-galley a[href^="#"]'))) {
				clearTimeout(timer);
				timer = setTimeout(hide, 250);
			}
		});
	}

	// ---------------------------------------------------------------------
	// 10. Ventanas (Cómo citar)
	// ---------------------------------------------------------------------
	function initDialogs() {
		$$('[data-pdt-dialog]').forEach(function (opener) {
			var dlg = document.getElementById(opener.getAttribute('data-pdt-dialog'));
			if (!dlg || typeof dlg.showModal !== 'function') {
				// Sin <dialog> (navegadores muy viejos): se muestra en línea.
				if (dlg) { dlg.setAttribute('open', ''); dlg.classList.add('pdt-dialog--inline'); opener.hidden = true; }
				return;
			}
			opener.addEventListener('click', function () { dlg.showModal(); });
			dlg.addEventListener('click', function (e) {
				// Clic en el fondo: cierra.
				if (e.target === dlg || e.target.closest('[data-pdt-dialog-close]')) { dlg.close(); }
			});
			dlg.addEventListener('close', function () { opener.focus(); });
		});

		var select = $('[data-pdt-cite-style]');
		var out = $('#pdt-cite-output');
		if (select && out && window.fetch) {
			select.addEventListener('change', function () {
				out.setAttribute('aria-busy', 'true');
				fetch(select.value, { credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (r) { if (r && r.content) { out.innerHTML = r.content; } })
					.catch(function () {})
					.then(function () { out.removeAttribute('aria-busy'); });
			});
		}
	}

	// ---------------------------------------------------------------------
	// 11. Menú principal: los ítems que no entran en una línea pasan a "Más"
	//     (patrón priority+). No hace falta limitar la cantidad de ítems.
	// ---------------------------------------------------------------------
	function initNavOverflow() {
		var nav = $('.pkp_navigation_primary');
		if (!nav || !window.matchMedia) { return; }
		var mq = window.matchMedia('(min-width: 992px)');
		var items = $$(':scope > li', nav);
		if (items.length < 3) { return; }

		var more = document.createElement('li');
		more.className = 'pdt-nav-more';
		more.innerHTML = '<button type="button" class="pdt-nav-more__btn" aria-expanded="false" aria-haspopup="true">' +
			'<span></span><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg></button>' +
			'<ul class="pdt-nav-more__menu"></ul>';
		more.querySelector('span').textContent = t('more', 'More');
		var btn = more.firstChild;
		var menu = more.lastChild;

		var setOpen = function (open) {
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			more.classList.toggle('is-open', open);
		};
		btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); setOpen(btn.getAttribute('aria-expanded') !== 'true'); });
		// Dentro de "Más" los submenús se muestran abiertos: el clic en el
		// título de un grupo no debe disparar el desplegable del Default.
		menu.addEventListener('click', function (e) {
			var a = e.target.closest('a');
			if (a && a.matches('[data-toggle="dropdown"], [aria-haspopup="true"]') && a.parentNode.parentNode === menu) {
				e.preventDefault();
				e.stopPropagation();
			}
		}, true);
		document.addEventListener('click', function (e) { if (!more.contains(e.target)) { setOpen(false); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && more.classList.contains('is-open')) { setOpen(false); btn.focus(); } });

		// Un solo submenú abierto a la vez: al pasar a otro ítem se cierran los
		// abiertos con clic (Bootstrap) y el "Más".
		items.concat([more]).forEach(function (li) {
			li.addEventListener('mouseenter', function () {
				if (!mq.matches) { return; }
				$$(':scope > li', nav).forEach(function (other) {
					if (other === li) { return; }
					var sub = other.querySelector(':scope > ul.show');
					if (sub) {
						sub.classList.remove('show');
						var t2 = other.querySelector(':scope > [aria-expanded]');
						if (t2) { t2.setAttribute('aria-expanded', 'false'); }
					}
				});
				if (li !== more) { setOpen(false); }
			});
		});

		var oneLine = function () {
			var visible = $$(':scope > li', nav);
			var top = visible[0].offsetTop;
			for (var i = 1; i < visible.length; i++) {
				if (visible[i].offsetTop > top + 4) { return false; }
			}
			return true;
		};
		var layout = function () {
			setOpen(false);
			if (more.parentNode) { nav.removeChild(more); }
			items.forEach(function (li) { nav.appendChild(li); });
			nav.classList.remove('pdt-nav--overflow');
			if (!mq.matches || oneLine()) { return; }
			nav.appendChild(more);
			nav.classList.add('pdt-nav--overflow');
			for (var i = items.length - 1; i > 0 && !oneLine(); i--) {
				menu.insertBefore(items[i], menu.firstChild);
			}
		};
		var timer;
		window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(layout, 120); });
		layout();
		if (document.fonts && document.fonts.ready) { document.fonts.ready.then(layout); }
	}

	// ---------------------------------------------------------------------
	// 12. Selector de idioma (desplegable) y barra de avisos
	// ---------------------------------------------------------------------
	function initHeaderExtras() {
		var lang = $('details.pdt-lang');
		if (lang) {
			document.addEventListener('click', function (e) { if (lang.open && !lang.contains(e.target)) { lang.open = false; } });
			document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && lang.open) { lang.open = false; lang.querySelector('summary').focus(); } });
		}
		var bar = $('.pdt-annbar');
		if (bar) {
			var key = 'pdt-ann-closed';
			var id = bar.getAttribute('data-pdt-ann');
			try { if (localStorage.getItem(key) === id) { bar.hidden = true; } } catch (e) {}
			var close = bar.querySelector('.pdt-annbar__close');
			if (close) {
				close.addEventListener('click', function () {
					bar.hidden = true;
					try { localStorage.setItem(key, id); } catch (e) {}
				});
			}
		}
	}

	// ---------------------------------------------------------------------
	// 13. Volver arriba, con anillo de progreso de lectura
	// ---------------------------------------------------------------------
	// Aviso de cookies: se muestra hasta que el lector elige. La elección
	// queda en <html data-pdt-cookies> y se emite el evento "pdt:cookies".
	function initCookies() {
		var bar = $('.pdt-cookies');
		var key = 'pdt-cookies';
		var saved = null;
		try { saved = localStorage.getItem(key); } catch (e) {}
		if (saved === 'all' || saved === 'necessary') {
			document.documentElement.setAttribute('data-pdt-cookies', saved);
		}
		if (!bar || saved) { return; }
		bar.hidden = false;
		var setH = function () { document.documentElement.style.setProperty('--pdt-cookies-h', bar.offsetHeight + 'px'); };
		setH();
		window.addEventListener('resize', setH);
		$$('[data-pdt-cookies]', bar).forEach(function (btn) {
			btn.addEventListener('click', function () {
				var choice = btn.getAttribute('data-pdt-cookies');
				try { localStorage.setItem(key, choice); } catch (e) {}
				document.documentElement.setAttribute('data-pdt-cookies', choice);
				bar.hidden = true;
				document.dispatchEvent(new CustomEvent('pdt:cookies', { detail: { choice: choice } }));
			});
		});
	}

	function initToTop() {
		var r = 21, len = 2 * Math.PI * r;
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'pdt-totop';
		btn.setAttribute('aria-label', t('backToTop', 'Back to top'));
		btn.title = btn.getAttribute('aria-label');
		btn.innerHTML = '<svg class="pdt-totop__ring" viewBox="0 0 48 48" aria-hidden="true">' +
			'<circle class="pdt-totop__track" cx="24" cy="24" r="' + r + '"/>' +
			'<circle class="pdt-totop__bar" cx="24" cy="24" r="' + r + '" stroke-dasharray="' + len + '" stroke-dashoffset="' + len + '"/></svg>' +
			'<svg class="pdt-totop__arrow" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 4l-7 7 1.4 1.4L11 7.8V20h2V7.8l4.6 4.6L19 11z"/></svg>';
		document.body.appendChild(btn);
		var bar = btn.querySelector('.pdt-totop__bar');
		var ticking = false;
		var update = function () {
			ticking = false;
			var max = document.documentElement.scrollHeight - window.innerHeight;
			var y = window.scrollY || window.pageYOffset;
			var p = max > 0 ? Math.min(1, y / max) : 0;
			bar.setAttribute('stroke-dashoffset', String(len * (1 - p)));
			btn.classList.toggle('is-visible', y > 400);
		};
		window.addEventListener('scroll', function () { if (!ticking) { ticking = true; window.requestAnimationFrame(update); } }, { passive: true });
		window.addEventListener('resize', update);
		btn.addEventListener('click', function (e) {
			var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
			// Con mouse/táctil sólo sube. Con teclado (detail 0) lleva el foco al
			// logo de la revista, nunca al enlace "Ir al contenido principal".
			if (e.detail === 0) {
				var home = document.querySelector('.pkp_site_name a');
				if (home) { home.focus({ preventScroll: true }); }
			} else {
				btn.blur();
			}
		});
		update();
	}

	// ---------------------------------------------------------------------
	// 14. Altura real de la cabecera fija (para TOC y panel lateral sticky)
	// ---------------------------------------------------------------------
	function initHeaderHeight() {
		var head = $('.pkp_structure_head');
		if (!head) { return; }
		var set = function () {
			var h = window.getComputedStyle(head).position === 'sticky' ? head.offsetHeight : 0;
			document.documentElement.style.setProperty('--pdt-sticky-top', h + 'px');
		};
		set();
		window.addEventListener('resize', set);
		if (window.ResizeObserver) { new ResizeObserver(set).observe(head); }
	}

	function init() {
		if (!document.body.classList.contains('pdt')) { return; }
		guardCredit();
		if (!document.body.classList.contains('pdt')) { return; }
		initDarkMode();
		initTabs();
		initCopy();
		initShare();
		initDialogs();
		initNavOverflow();
		initHeaderExtras();
		initCookies();
		$$('.pdt-indexing__logos a[href]').forEach(function (a) { a.target = '_blank'; a.rel = 'noopener'; });
		initToTop();
		initHeaderHeight();
		initToc();
		initReferences();
		initLightbox();
		initCitationTips();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
