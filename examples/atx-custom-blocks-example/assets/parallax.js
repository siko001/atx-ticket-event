/**
 * Tiny vanilla parallax: shifts each hero's background layer as it scrolls
 * through the viewport. No dependencies; honours prefers-reduced-motion.
 */
(function () {
	'use strict';

	if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		return;
	}

	var STRENGTH = 0.18; // 0 = none, higher = more movement.
	var items = [];
	var ticking = false;

	function collect() {
		items = Array.prototype.slice.call(document.querySelectorAll('[data-atx-parallax]'));
	}

	function update() {
		ticking = false;
		var vh = window.innerHeight || document.documentElement.clientHeight;

		items.forEach(function (el) {
			var layer = el.querySelector('[data-atx-parallax-layer]');
			if (!layer) {
				return;
			}
			var rect = el.getBoundingClientRect();
			if (rect.bottom < 0 || rect.top > vh) {
				return; // Off-screen — skip.
			}
			// Distance of the element's centre from the viewport centre.
			var offset = (rect.top + rect.height / 2) - vh / 2;
			layer.style.transform = 'translate3d(0,' + (offset * STRENGTH).toFixed(1) + 'px,0)';
		});
	}

	function onScroll() {
		if (!ticking) {
			ticking = true;
			window.requestAnimationFrame(update);
		}
	}

	function init() {
		collect();
		if (!items.length) {
			return;
		}
		update();
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
