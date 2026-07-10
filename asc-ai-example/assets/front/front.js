/*!
 * Example site front-end JavaScript
 */

(function ($) {
	'use strict';

	function initHeaderNav() {
		var header = document.querySelector('.example-header');
		var toggle = document.getElementById('example-header-menu-toggle');
		var drawer = document.getElementById('example-header-nav-drawer');
		var backdrop = document.getElementById('example-header-drawer-backdrop');
		var drawerClose = document.getElementById('example-header-drawer-close');
		var desktopTop = document.getElementById('example-header-desktop-top');
		var desktopMain = document.getElementById('example-header-desktop-main');

		if (!header || !toggle || !drawer) {
			return;
		}

		var mqMobile = window.matchMedia('(max-width: 900px)');
		var lastFocusedElementBeforeOpen = null;

		function getFocusableDrawerElements() {
			var selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
			var nodes = drawer.querySelectorAll(selector);
			return Array.prototype.filter.call(nodes, function (node) {
				return node && node.offsetParent !== null;
			});
		}

		function setDesktopNavAriaHidden(isMobile) {
			var v = isMobile ? 'true' : 'false';
			if (desktopTop) {
				desktopTop.setAttribute('aria-hidden', v);
			}
			if (desktopMain) {
				desktopMain.setAttribute('aria-hidden', v);
			}
		}

		function closeDrawer(shouldRestoreFocus) {
			if (typeof shouldRestoreFocus === 'undefined') {
				shouldRestoreFocus = true;
			}

			header.classList.remove('example-header--nav-open');
			toggle.setAttribute('aria-expanded', 'false');
			toggle.setAttribute('aria-label', 'Open menu');
			drawer.setAttribute('aria-hidden', 'true');
			if (backdrop) {
				backdrop.setAttribute('aria-hidden', 'true');
			}
			document.body.style.overflow = '';

			if (
				shouldRestoreFocus &&
				lastFocusedElementBeforeOpen &&
				typeof lastFocusedElementBeforeOpen.focus === 'function'
			) {
				lastFocusedElementBeforeOpen.focus();
			}
			lastFocusedElementBeforeOpen = null;
		}

		function openDrawer() {
			if (document.activeElement instanceof HTMLElement) {
				lastFocusedElementBeforeOpen = document.activeElement;
			} else {
				lastFocusedElementBeforeOpen = toggle;
			}

			header.classList.add('example-header--nav-open');
			toggle.setAttribute('aria-expanded', 'true');
			toggle.setAttribute('aria-label', 'Close menu');
			drawer.setAttribute('aria-hidden', 'false');
			if (backdrop) {
				backdrop.setAttribute('aria-hidden', 'false');
			}
			document.body.style.overflow = 'hidden';

			var focusable = getFocusableDrawerElements();
			if (focusable.length > 0) {
				focusable[0].focus();
			}
		}

		function onViewportChange() {
			if (!mqMobile.matches) {
				closeDrawer();
			}
			setDesktopNavAriaHidden(mqMobile.matches);
		}

		toggle.addEventListener('click', function () {
			if (!mqMobile.matches) {
				return;
			}
			if (header.classList.contains('example-header--nav-open')) {
				closeDrawer();
			} else {
				openDrawer();
			}
		});

		if (backdrop) {
			backdrop.addEventListener('click', function () {
				if (mqMobile.matches) {
					closeDrawer();
				}
			});
		}

		if (drawerClose) {
			drawerClose.addEventListener('click', function () {
				if (mqMobile.matches) {
					closeDrawer();
					toggle.focus();
				}
			});
		}

		drawer.addEventListener('click', function (e) {
			if (mqMobile.matches && e.target.closest('a')) {
				closeDrawer();
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Tab' && mqMobile.matches && header.classList.contains('example-header--nav-open')) {
				var focusable = getFocusableDrawerElements();
				if (focusable.length === 0) {
					e.preventDefault();
					return;
				}

				var first = focusable[0];
				var last = focusable[focusable.length - 1];
				if (e.shiftKey && document.activeElement === first) {
					e.preventDefault();
					last.focus();
					return;
				}
				if (!e.shiftKey && document.activeElement === last) {
					e.preventDefault();
					first.focus();
				}
			}

			if (
				e.key === 'Escape' &&
				mqMobile.matches &&
				header.classList.contains('example-header--nav-open')
			) {
				closeDrawer(false);
				toggle.focus();
			}
		});

		if (mqMobile.addEventListener) {
			mqMobile.addEventListener('change', onViewportChange);
		} else if (mqMobile.addListener) {
			mqMobile.addListener(onViewportChange);
		}
		window.addEventListener('resize', onViewportChange);

		onViewportChange();
	}

	function initSkipLinkFocus() {
		var skipLinks = document.querySelectorAll('.example-skip-link[href^="#"]');
		if (!skipLinks || skipLinks.length === 0) {
			return;
		}

		function resolveSkipTarget(hash) {
			if (hash && hash.length > 1) {
				var targetFromHash = document.getElementById(hash.slice(1));
				if (targetFromHash) {
					return targetFromHash;
				}
			}

			var fallbackTarget = document.querySelector('main, [role="main"], #main-content, #content');
			if (fallbackTarget) {
				return fallbackTarget;
			}

			return null;
		}

		function findFirstFocusableDescendant(container) {
			if (!container) {
				return null;
			}

			var selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
			var candidates = container.querySelectorAll(selector);
			if (!candidates || candidates.length === 0) {
				return null;
			}

			return Array.prototype.find.call(candidates, function (node) {
				return !!(node && node.offsetParent !== null);
			}) || null;
		}

		Array.prototype.forEach.call(skipLinks, function (skipLink) {
			skipLink.addEventListener('click', function (event) {
				event.preventDefault();

				var hash = skipLink.getAttribute('href');
				var target = resolveSkipTarget(hash);
				if (!target) {
					return;
				}

				target.scrollIntoView({ block: 'start' });
				var focusTarget = findFirstFocusableDescendant(target);
				if (!focusTarget) {
					focusTarget = target;
				}

				window.setTimeout(function () {
					var hadTabIndex = focusTarget.hasAttribute('tabindex');
					if (!hadTabIndex) {
						focusTarget.setAttribute('tabindex', '-1');
					}

					try {
						focusTarget.focus({ preventScroll: true });
					} catch (e) {
						focusTarget.focus();
					}

					if (!hadTabIndex) {
						focusTarget.addEventListener('blur', function removeTabIndexOnBlur() {
							focusTarget.removeAttribute('tabindex');
							focusTarget.removeEventListener('blur', removeTabIndexOnBlur);
						});
					}
				}, 0);
			});
		});
	}

	function initThemeToggle() {
		var COOKIE_NAME = 'asc_cookie';
		var LIGHT = 'asc-light';
		var DARK = 'asc-dark';

		var lightBtns = document.querySelectorAll('.example-theme-toggle-btn--light');
		var darkBtns = document.querySelectorAll('.example-theme-toggle-btn--dark');
		if (!lightBtns.length || !darkBtns.length) {
			return;
		}

		function getCookie(name) {
			var match = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
			return match ? decodeURIComponent(match[1]) : null;
		}

		function setCookie(name, value) {
			var expires = new Date();
			expires.setFullYear(expires.getFullYear() + 1);
			document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; expires=' + expires.toUTCString() + '; SameSite=Lax';
		}

		function applyTheme(theme) {
			var isLight = theme === LIGHT;
			document.body.classList.toggle('asc-site-light', isLight);
			document.body.classList.toggle('asc-site-dark', !isLight);
			Array.prototype.forEach.call(lightBtns, function (btn) {
				btn.setAttribute('aria-pressed', isLight ? 'true' : 'false');
			});
			Array.prototype.forEach.call(darkBtns, function (btn) {
				btn.setAttribute('aria-pressed', isLight ? 'false' : 'true');
			});
		}

		var current = getCookie(COOKIE_NAME);
		if (current !== LIGHT && current !== DARK) {
			setCookie(COOKIE_NAME, LIGHT); // Default to light mode for this website
			current = LIGHT;
		}
		applyTheme(current);

		Array.prototype.forEach.call(lightBtns, function (btn) {
			btn.addEventListener('click', function () {
				setCookie(COOKIE_NAME, LIGHT);
				applyTheme(LIGHT);
			});
		});

		Array.prototype.forEach.call(darkBtns, function (btn) {
			btn.addEventListener('click', function () {
				setCookie(COOKIE_NAME, DARK);
				applyTheme(DARK);
			});
		});
	}

	function initScrollTop() {
		var btn = document.querySelector('.asc-scroll-top');
		if (!btn) {
			return;
		}

		var ticking = false;

		function updateVisibility() {
			var scrollable = document.documentElement.scrollHeight - window.innerHeight;
			var visible = scrollable > 0 && window.scrollY / scrollable > 0.25;
			btn.classList.toggle('asc-scroll-top--visible', visible);
			ticking = false;
		}

		window.addEventListener('scroll', function () {
			if (!ticking) {
				window.requestAnimationFrame(updateVisibility);
				ticking = true;
			}
		}, { passive: true });

		btn.addEventListener('click', function () {
			window.scrollTo({ top: 0, behavior: 'smooth' });
		});

		updateVisibility();
	}

	$(document).ready(function () {
		initHeaderNav();
		initSkipLinkFocus();
		initThemeToggle();
		initScrollTop();
	});
})(jQuery);
