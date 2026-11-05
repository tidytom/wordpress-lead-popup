/* global leadPopupObj */
(function () {
	'use strict';

	function init() {

		var modal    = document.getElementById('lead-popup-overlay');
		var content   = document.getElementById('lead-popup-content');
		var closeBtn  = document.getElementById('lead-popup-close');
		var form      = document.getElementById('lead-popup-form');
		var submitBtn = document.getElementById('lead-popup-submit-btn');
		var messageEl = document.getElementById('lead-popup-message');

		// If markup is missing for any reason, bail safely.
		if (!modal || !content || !closeBtn || !form || !submitBtn || !messageEl) {
			return;
		}

		var config = (typeof leadPopupObj !== 'undefined') ? leadPopupObj : {};
		var i18n   = config.i18n || {};

		var STORAGE_KEY = 'lead_popup_cooldown';
		var EMAIL_RX    = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		var FETCH_TIMEOUT_MS = 15000;

		var lastFocused  = null;
		var inFlight     = false;
		var openedOnce   = false; // prevents double auto-open
		var originalText = submitBtn.textContent;

		// ----- helpers -----

		function nowMs() {
			return new Date().getTime();
		}

		function safeSet(key, value) {
			try { window.localStorage.setItem(key, value); } catch (e) { /* private mode */ }
		}

		function safeGet(key) {
			try { return window.localStorage.getItem(key); } catch (e) { return null; }
		}

		var DAY_MS = 24 * 60 * 60 * 1000;

		function cooldownDays() {
			var days = parseInt(config.cooldownDays, 10);
			return (isNaN(days) || days <= 0) ? 0 : days;
		}

		function setCooldown() {
			var days = cooldownDays();
			if (!days) { return; }
			safeSet(STORAGE_KEY, String(nowMs() + (days * DAY_MS)));
		}

		function inCooldown() {
			var days = cooldownDays();
			// Cooldown set to 0 in Settings: ignore any older stored value.
			if (!days) { return false; }
			var expiry = parseInt(safeGet(STORAGE_KEY), 10);
			if (isNaN(expiry)) { return false; }
			// If the admin shortened the cooldown, do not honour a longer old one.
			expiry = Math.min(expiry, nowMs() + (days * DAY_MS));
			return nowMs() < expiry;
		}

		function setMessage(text, kind) {
			messageEl.textContent = text || '';
			messageEl.classList.remove('is-success', 'is-error');
			if (kind === 'success') { messageEl.classList.add('is-success'); }
			else if (kind === 'error') { messageEl.classList.add('is-error'); }
		}

		function setBusy(busy) {
			inFlight = busy;
			submitBtn.disabled = busy;
			form.setAttribute('aria-busy', busy ? 'true' : 'false');
			submitBtn.textContent = busy ? (i18n.sending || 'Sending...') : originalText;
		}

		// Focus trap. Skips the honeypot (tabindex="-1", inside aria-hidden).
		function focusableInModal() {
			var all = modal.querySelectorAll(
				'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
			);
			return Array.prototype.filter.call(all, function (el) {
				if (el.getAttribute('tabindex') === '-1') { return false; }
				var hidden = el.parentNode;
				while (hidden && hidden !== modal) {
					if (hidden.getAttribute && hidden.getAttribute('aria-hidden') === 'true') { return false; }
					hidden = hidden.parentNode;
				}
				return true;
			});
		}

		function trapFocus(e) {
			if (e.key !== 'Tab') { return; }
			var nodes = focusableInModal();
			if (!nodes.length) { return; }
			var first = nodes[0];
			var last  = nodes[nodes.length - 1];
			if (!modal.contains(document.activeElement) || document.activeElement === content) {
				// Focus escaped (or is on the dialog box itself): bring it back in.
				e.preventDefault();
				(e.shiftKey ? last : first).focus();
			} else if (e.shiftKey && document.activeElement === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		}

		function onKeyDown(e) {
			if (e.key === 'Escape' || e.key === 'Esc') {
				// Escape = temporary close (no cooldown). Same intent as clicking off.
				closeModal({ cooldown: false });
				return;
			}
			trapFocus(e);
		}

		function showModal() {
			if (modal.classList.contains('lead-popup-visible')) { return; }
			openedOnce = true;
			lastFocused = document.activeElement;
			modal.classList.remove('lead-popup-hidden');
			modal.classList.add('lead-popup-visible');
			modal.setAttribute('aria-hidden', 'false');
			document.body.classList.add('lead-popup-no-scroll');
			document.addEventListener('keydown', onKeyDown);

			// Clear a message left over from an earlier visit to the popup.
			if (!inFlight) { setMessage('', null); }

			// Move focus into modal (first form field after the X button, or content)
			var nodes = focusableInModal();
			(nodes[1] || nodes[0] || content).focus();
		}

		function closeModal(opts) {
			opts = opts || {};
			if (!modal.classList.contains('lead-popup-visible')) { return; }
			modal.classList.remove('lead-popup-visible');
			modal.classList.add('lead-popup-hidden');
			modal.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('lead-popup-no-scroll');
			document.removeEventListener('keydown', onKeyDown);

			if (opts.cooldown) { setCooldown(); }

			if (lastFocused && typeof lastFocused.focus === 'function') {
				try { lastFocused.focus(); } catch (e) { /* noop */ }
			}
		}

		// ----- triggers -----

		// Auto-open after delay
		var delay = parseInt(config.delaySecs, 10);
		if (!isNaN(delay) && delay > 0 && !inCooldown()) {
			window.setTimeout(function () {
				if (!openedOnce) { showModal(); }
			}, delay * 1000);
		}

		// X button = "do not show again" (sets cooldown)
		closeBtn.addEventListener('click', function () {
			closeModal({ cooldown: true });
		});

		// Click on background = temporary close (NO cooldown - popup may auto-open again)
		modal.addEventListener('click', function (e) {
			if (e.target === modal) {
				closeModal({ cooldown: false });
			}
		});

		// Manual triggers (footer button, shortcode link, anything with the class).
		// Delegated, so triggers added to the page later also work.
		document.addEventListener('click', function (e) {
			var el = e.target;
			while (el && el !== document) {
				if (el.classList && el.classList.contains('lead-popup-manual-trigger')) {
					e.preventDefault();
					showModal();
					return;
				}
				el = el.parentNode;
			}
		});

		// Clear the "invalid" red border the moment the user starts typing again
		var emailField = document.getElementById('lead-popup-email');
		if (emailField) {
			emailField.addEventListener('input', function () {
				emailField.classList.remove('lead-popup-invalid');
			});
		}

		// ----- PDF retrieval (gated, light obscurity) -----

		function fetchPdfUrl() {
			if (!config.ajaxUrl || !config.ajaxAction || !config.nonce) {
				return Promise.reject(new Error('AJAX not configured'));
			}
			var body = new FormData();
			body.append('action', config.ajaxAction);
			body.append('nonce', config.nonce);
			return fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) {
					if (!r.ok) { throw new Error('HTTP ' + r.status); }
					return r.json();
				})
				.then(function (data) {
					if (data && data.success && data.data && data.data.url) {
						return data.data.url;
					}
					throw new Error('No PDF URL returned');
				});
		}

		function triggerDownload(url) {
			// Same-origin: <a download> works. Cross-origin: open in new tab as fallback.
			var parsed;
			try { parsed = new URL(url, window.location.href); } catch (e) { return false; }
			// Only follow plain web links (never javascript: or data: URLs).
			if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') { return false; }
			var sameOrigin = parsed.origin === window.location.origin;

			if (sameOrigin) {
				var link = document.createElement('a');
				link.href = parsed.href;
				link.download = ''; // keep the file's own name
				link.rel = 'noopener';
				document.body.appendChild(link);
				link.click();
				document.body.removeChild(link);
			} else {
				window.open(parsed.href, '_blank', 'noopener');
			}
			return true;
		}

		// ----- form submission -----

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			if (inFlight) { return; }

			// Honeypot - silently "succeed" so bots don't retry.
			var honeypot = form.querySelector('input[name="botcheck"]');
			if (honeypot && honeypot.value) {
				setMessage(i18n.success || 'Thank you!', 'success');
				form.reset();
				window.setTimeout(function () { closeModal({ cooldown: true }); }, 1500);
				return;
			}

			var emailEl = document.getElementById('lead-popup-email');
			var email   = emailEl ? (emailEl.value || '').trim() : '';
			if (!EMAIL_RX.test(email)) {
				setMessage(i18n.invalid_email || 'Please enter a valid email address.', 'error');
				if (emailEl) {
					emailEl.classList.add('lead-popup-invalid');
					emailEl.focus();
				}
				return;
			}
			if (emailEl) { emailEl.classList.remove('lead-popup-invalid'); }

			if (!config.web3Key) {
				setMessage(i18n.system_error || 'The form is unavailable right now.', 'error');
				return;
			}

			var wantsPdf = false;
			var pdfCheck = document.getElementById('lead-popup-pdf-check');
			if (pdfCheck && pdfCheck.checked) { wantsPdf = true; }

			setBusy(true);
			setMessage('', null);

			var formData = new FormData(form);
			formData.set('email', email);
			formData.append('access_key', config.web3Key);

			// Fetch with timeout
			var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
			var timeoutId  = controller
				? window.setTimeout(function () { controller.abort(); }, FETCH_TIMEOUT_MS)
				: null;

			var fetchOpts = { method: 'POST', body: formData };
			if (controller) { fetchOpts.signal = controller.signal; }

			fetch('https://api.web3forms.com/submit', fetchOpts)
				.then(function (response) {
					return response.json().then(function (data) {
						return { ok: response.ok, data: data };
					});
				})
				.then(function (result) {
					if (!(result.ok && result.data && result.data.success)) {
						// Don't echo raw API messages.
						setMessage(i18n.generic_error || 'Something went wrong.', 'error');
						if (window.console && result.data) {
							window.console.warn('[lead-popup]', result.data);
						}
						return;
					}

					form.reset();

					if (wantsPdf) {
						setMessage(i18n.success_pdf || 'Thank you! Your download will start shortly.', 'success');
						fetchPdfUrl()
							.then(function (url) {
								if (!triggerDownload(url)) { throw new Error('Invalid PDF URL'); }
							})
							.catch(function (err) {
								// Do not leave the visitor waiting for a download that will not come.
								setMessage(i18n.pdf_error || 'Thank you! Your information has been received, but the download could not start.', 'error');
								if (window.console) { window.console.warn('[lead-popup] PDF', err); }
							})
							.then(function () {
								window.setTimeout(function () { closeModal({ cooldown: true }); }, 2500);
							});
					} else {
						setMessage(i18n.success || 'Thank you!', 'success');
						window.setTimeout(function () { closeModal({ cooldown: true }); }, 2500);
					}
				})
				.catch(function (err) {
					// Covers offline, blocked requests and the 15-second timeout (AbortError).
					setMessage(i18n.network_error || 'Network error. Please try again.', 'error');
					if (window.console) { window.console.warn('[lead-popup]', err); }
				})
				.then(function () {
					if (timeoutId) { window.clearTimeout(timeoutId); }
					setBusy(false);
				});
		});
	}

	// Run now if the page has already loaded (e.g. the script was deferred
	// by an optimisation plugin), otherwise wait for DOMContentLoaded.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
