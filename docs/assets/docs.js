(function () {
	var root = document.documentElement, btn = document.getElementById('themeBtn');
	try { var saved = localStorage.getItem('pdt-docs-theme'); if (saved) { root.setAttribute('data-theme', saved); } } catch (e) {}
	btn.addEventListener('click', function () {
		var dark = root.getAttribute('data-theme') ? root.getAttribute('data-theme') === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
		var next = dark ? 'light' : 'dark';
		root.setAttribute('data-theme', next);
		try { localStorage.setItem('pdt-docs-theme', next); } catch (e) {}
	});
	// El correo se arma al hacer clic, para que no quede en el HTML.
	var mail = document.getElementById('mailBtn');
	mail.addEventListener('click', function () {
		location.href = 'mailto:' + mail.dataset.u + '@' + mail.dataset.d + '?subject=' + encodeURIComponent(mail.dataset.s || 'Paideia Theme');
	});
	var zoom = document.getElementById('zoom'), zimg = zoom.querySelector('img');
	document.querySelectorAll('.shot img').forEach(function (img) {
		img.addEventListener('click', function () {
			if (!zoom.showModal) { window.open(img.src); return; }
			zimg.src = img.src; zimg.alt = img.alt; zoom.showModal();
		});
	});
	zoom.addEventListener('click', function () { zoom.close(); });
})();
