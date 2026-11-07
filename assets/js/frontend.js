/**
 * Listivo Currency Converter - frontend.
 *
 * Listivo renders the ad form with Vue. Setting input.value alone does not
 * update the Vue model, so every programmatic change also dispatches a native
 * input event that the theme's @input handlers listen for.
 */
(function () {
	'use strict';

	var data = window.LCC_DATA;

	if (!data || !data.rate) {
		return;
	}

	var rate = parseFloat(data.rate.usdToCad);

	if (!isFinite(rate) || rate <= 0) {
		return;
	}

	var decimals = parseInt(data.decimals, 10);

	if (!isFinite(decimals) || decimals < 0) {
		decimals = 0;
	}

	if (decimals > 2) {
		decimals = 2;
	}

	var rounding = {
		CAD: parseFloat(data.rounding && data.rounding.CAD),
		USD: parseFloat(data.rounding && data.rounding.USD)
	};

	if (!isFinite(rounding.CAD)) {
		rounding.CAD = 0;
	}

	if (!isFinite(rounding.USD)) {
		rounding.USD = 0;
	}

	var i18n = data.i18n || {};
	var fallbackRate = data.rate.source === 'fallback' || data.rate.source === 'manual';
	var fields = null;
	var sourceCode = null;

	/**
	 * Read a price out of an input that may contain symbols, letters or
	 * thousands separators.
	 *
	 * @param {string} raw Raw input value.
	 * @return {number|null}
	 */
	function parseAmount(raw) {
		if (raw === null || typeof raw === 'undefined') {
			return null;
		}

		var value = String(raw).trim();

		if (!value) {
			return null;
		}

		value = value.replace(/[^0-9.,]/g, '');

		if (!value) {
			return null;
		}

		var lastComma = value.lastIndexOf(',');
		var lastDot = value.lastIndexOf('.');

		if (lastComma > -1 && lastDot > -1) {
			if (lastComma > lastDot) {
				value = value.replace(/\./g, '').replace(/,/g, '.');
			} else {
				value = value.replace(/,/g, '');
			}
		} else if (lastComma > -1) {
			var commaCount = (value.match(/,/g) || []).length;

			if (commaCount === 1 && value.length - lastComma - 1 === 2) {
				value = value.replace(',', '.');
			} else {
				value = value.replace(/,/g, '');
			}
		} else if (lastDot > -1 && (value.match(/\./g) || []).length > 1) {
			value = value.replace(/\./g, '');
		}

		var amount = parseFloat(value);

		return isFinite(amount) && amount >= 0 ? amount : null;
	}

	/**
	 * Round to a step, e.g. nearest 5.
	 *
	 * @param {number} value Amount.
	 * @param {number} step  Step, 0 disables.
	 * @return {number}
	 */
	function roundValue(value, step) {
		step = parseFloat(step);

		if (isFinite(step) && step > 0) {
			value = Math.round(value / step) * step;
		}

		return value;
	}

	/**
	 * Convert between the two currencies.
	 *
	 * @param {number} amount Amount in the source currency.
	 * @param {string} from   Source currency.
	 * @param {string} to     Target currency.
	 * @return {number}
	 */
	function convert(amount, from, to) {
		var value = from === 'USD' ? amount * rate : amount / rate;

		return roundValue(value, rounding[to]);
	}

	/**
	 * Format with thousands separators.
	 *
	 * @param {number} value  Amount.
	 * @param {number} places Decimal places.
	 * @return {string}
	 */
	function formatNumber(value, places) {
		var parts = Number(value).toFixed(places).split('.');

		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');

		return parts.join('.');
	}

	/**
	 * Compact rate for the hint.
	 *
	 * @param {number} value Rate.
	 * @return {string}
	 */
	function formatRate(value) {
		return parseFloat(Number(value).toFixed(4)).toString();
	}

	/**
	 * Safe querySelector.
	 *
	 * @param {string} selector CSS selector.
	 * @return {Element|null}
	 */
	function findBySelector(selector) {
		if (!selector) {
			return null;
		}

		try {
			return document.querySelector(selector);
		} catch (error) {
			return null;
		}
	}

	/**
	 * Locate a currency input by configured selector, then by label text.
	 *
	 * @param {string} code CAD or USD.
	 * @return {Element|null}
	 */
	function findField(code) {
		var configured = data.fields && data.fields[code];
		var input = findBySelector(configured);

		if (input) {
			return input;
		}

		var groups = document.querySelectorAll('.listivo-field-group');
		var matcher = new RegExp('\\b' + code + '\\b');

		for (var i = 0; i < groups.length; i++) {
			var label = groups[i].querySelector('.listivo-field-group__label');

			if (!label || !matcher.test(label.textContent.toUpperCase())) {
				continue;
			}

			var candidate = groups[i].querySelector('input');

			if (candidate) {
				return candidate;
			}
		}

		return null;
	}

	/**
	 * The Listivo price component uses its own custom element.
	 *
	 * @param {Element} input Input element.
	 * @return {boolean}
	 */
	function isPriceInput(input) {
		return !!(input.closest && input.closest('lst-price-model-field'));
	}

	/**
	 * Set a value and notify Vue.
	 *
	 * The write flag is cleared on the next task so any formatting work queued
	 * by AutoNumeric in the same event loop cannot bounce back into a sync.
	 *
	 * @param {Element} input Input element.
	 * @param {string}  value New value.
	 * @return {void}
	 */
	function setFieldValue(input, value) {
		if (!input) {
			return;
		}

		input._lccWriting = true;
		input.value = value;
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));

		window.setTimeout(function () {
			input._lccWriting = false;
		}, 0);
	}

	/**
	 * Add the rate hint under a field.
	 *
	 * @param {Element} input Input element.
	 * @param {string}  text  Hint text.
	 * @return {void}
	 */
	function showHint(input, text) {
		var group = input.closest ? input.closest('.listivo-field-group') : null;

		if (!group) {
			return;
		}

		var hint = group.querySelector('.lcc-hint');

		if (!hint) {
			hint = document.createElement('div');
			hint.className = 'lcc-hint';
			hint.setAttribute('role', 'status');
			hint.setAttribute('aria-live', 'polite');

			var anchor = group.querySelector('.listivo-input-v2');

			if (anchor && anchor.parentNode) {
				anchor.parentNode.insertBefore(hint, anchor.nextSibling);
			} else {
				group.appendChild(hint);
			}
		}

		hint.textContent = text;
	}

	/**
	 * Remove the hint from a field.
	 *
	 * @param {Element} input Input element.
	 * @return {void}
	 */
	function removeHint(input) {
		var group = input.closest ? input.closest('.listivo-field-group') : null;

		if (!group) {
			return;
		}

		var hint = group.querySelector('.lcc-hint');

		if (hint && hint.parentNode) {
			hint.parentNode.removeChild(hint);
		}
	}

	/**
	 * Lock the auto-filled field.
	 *
	 * @param {Element} input    Input element.
	 * @param {string}  fromCode Source currency.
	 * @return {void}
	 */
	function lockField(input, fromCode) {
		if (!input) {
			return;
		}

		input.readOnly = true;
		input.setAttribute('readonly', 'readonly');
		input.setAttribute('aria-readonly', 'true');
		input.setAttribute(
			'title',
			(i18n.locked || 'Auto-converted from %s. Clear that price to edit this field.').replace('%s', fromCode)
		);

		var group = input.closest ? input.closest('.listivo-field-group') : null;

		if (group) {
			group.classList.add('lcc-locked');
		}
	}

	/**
	 * Unlock a field.
	 *
	 * @param {Element} input Input element.
	 * @return {void}
	 */
	function unlockField(input) {
		if (!input) {
			return;
		}

		input.readOnly = false;
		input.removeAttribute('readonly');
		input.removeAttribute('aria-readonly');
		input.removeAttribute('title');

		var group = input.closest ? input.closest('.listivo-field-group') : null;

		if (group) {
			group.classList.remove('lcc-locked');
		}
	}

	/**
	 * Clear and unlock the auto-filled field.
	 *
	 * @param {Element} target Target input.
	 * @return {void}
	 */
	function clearTarget(target) {
		if (!target) {
			return;
		}

		removeHint(target);
		unlockField(target);

		if (target.value !== '') {
			setFieldValue(target, '');
		}
	}

	/**
	 * Convert the value of the field that was just edited.
	 *
	 * @param {Element} source Source input.
	 * @return {void}
	 */
	function sync(source) {
		if (!fields || source._lccWriting) {
			return;
		}

		var from = source === fields.CAD ? 'CAD' : 'USD';
		var to = from === 'CAD' ? 'USD' : 'CAD';
		var target = fields[to];
		var amount = parseAmount(source.value);

		if (amount === null) {
			sourceCode = null;
			clearTarget(target);
			return;
		}

		var converted = convert(amount, from, to);
		var display = formatNumber(converted, decimals);
		var written = isPriceInput(target) ? display.replace(/,/g, '') : display + ' ' + to;

		sourceCode = from;

		setFieldValue(target, written);
		lockField(target, from);

		var text = (i18n.converted || 'Converted from %1$s at 1 USD = %2$s CAD')
			.replace('%1$s', from)
			.replace('%2$s', formatRate(rate));

		if (fallbackRate) {
			text += ' (' + (i18n.fallback || 'fallback rate') + ')';
		}

		showHint(target, text);

		// Vue may re-render the target after the model update; keep the lock
		// and the hint if that re-render replaces the field markup.
		window.setTimeout(function () {
			if (sourceCode === from) {
				lockField(target, from);
				showHint(target, text);
			}
		}, 80);
	}

	/**
	 * Wire the two inputs.
	 *
	 * @param {Element} cad CAD input.
	 * @param {Element} usd USD input.
	 * @return {void}
	 */
	function setup(cad, usd) {
		fields = {
			CAD: cad,
			USD: usd
		};

		[cad, usd].forEach(function (input) {
			input.addEventListener('input', function () {
				if (input._lccWriting) {
					return;
				}

				sync(input);
			});
		});

		// The Listivo clear icon updates the value without firing an input event.
		document.addEventListener('click', function (event) {
			var target = event.target;

			if (!target || !target.closest) {
				return;
			}

			var button = target.closest('.listivo-input-v2__clear');

			if (!button) {
				return;
			}

			var group = button.closest('.listivo-field-group');
			var cadGroup = cad.closest('.listivo-field-group');
			var usdGroup = usd.closest('.listivo-field-group');

			if (!group || (group !== cadGroup && group !== usdGroup)) {
				return;
			}

			window.setTimeout(function () {
				if (!sourceCode) {
					return;
				}

				var source = fields[sourceCode];

				if (source && source.value.trim() === '') {
					var other = fields[sourceCode === 'CAD' ? 'USD' : 'CAD'];
					sourceCode = null;
					clearTarget(other);
				}
			}, 0);
		});
	}

	/**
	 * Wait for the form, then set up.
	 *
	 * @return {void}
	 */
	function boot() {
		var attempts = 0;

		function attempt() {
			var cad = findField('CAD');
			var usd = findField('USD');

			if (cad && usd && cad !== usd) {
				setup(cad, usd);
				return;
			}

			attempts += 1;

			if (attempts < 40) {
				window.setTimeout(attempt, 250);
			}
		}

		attempt();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
