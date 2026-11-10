/**
 * Tip options for the WooCommerce Cart/Checkout blocks.
 *
 * Rendered through the order summary "discounts meta" Slot/Fill, so it works on both Cart and Checkout blocks.
 * On the Checkout block, if that slot is missing from the page, it falls back to the "order meta" slot
 * (next to the Place Order button).
 * Data comes from the Store API cart extension (namespace "wpcot"), and updates are sent with extensionCartUpdate().
 */
(function (wp, wc) {
	'use strict';

	if (!wc || !wc.blocksCheckout || !wp || !wp.plugins || !wp.element || !wp.data) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.data.useSelect;
	var registerPlugin = wp.plugins.registerPlugin;

	var DiscountsMeta = wc.blocksCheckout.ExperimentalDiscountsMeta || wc.blocksCheckout.DiscountsMeta;
	var OrderMeta = wc.blocksCheckout.ExperimentalOrderMeta || wc.blocksCheckout.OrderMeta;
	var extensionCartUpdate = wc.blocksCheckout.extensionCartUpdate;
	var NAMESPACE = 'wpcot';

	if (!DiscountsMeta || !extensionCartUpdate) {
		return;
	}

	// Tracks whether the primary (discounts meta) fill is mounted, so the fallback can be skipped
	var primaryMounted = false;
	var listeners = [];

	// Default values are applied only once per page load
	var defaultsApplied = false;

	// Requests are sent one by one, because every request rewrites the tips stored in the session
	var queue = Promise.resolve();

	function setPrimaryMounted(value) {
		primaryMounted = value;
		listeners.forEach(function (listener) {
			listener();
		});
	}

	function enqueue(data) {
		var task = queue.then(function () {
			return extensionCartUpdate({
				namespace: NAMESPACE,
				data: data
			});
		});

		queue = task.catch(function () {
			// Keep the queue alive after a failed request
		});

		return task;
	}

	function setCookie(name, value, days) {
		var date = new Date();

		date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
		document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + date.toUTCString() + '; path=/';
	}

	function getCookie(name) {
		var parts = document.cookie.split(';');

		for (var i = 0; i < parts.length; i++) {
			var part = parts[i].trim();

			if (part.indexOf(name + '=') === 0) {
				return decodeURIComponent(part.substring(name.length + 1));
			}
		}

		return '';
	}

	// Same cookie as the classic cart/checkout, so the "selected" state is shared between both
	function cookieName(data, tip) {
		return 'wpcot_tip_' + data.user_id + '_' + tip.key;
	}

	/**
	 * Tip value button (also used for the "No, Thanks!" and the custom amount buttons).
	 */
	function ValueButton(props) {
		var className = 'wpcot-tip-value' + (props.extraClass ? ' ' + props.extraClass : '') +
			(props.active ? ' active' : '') +
			(props.isDefault ? ' active-default' : '') +
			(props.loading ? ' loading' : '');

		var spanProps = props.html ? {dangerouslySetInnerHTML: {__html: props.html}} : null;

		return el('div', {
			className: className,
			role: 'button',
			tabIndex: 0,
			'aria-pressed': !!props.active,
			onClick: props.onClick,
			onKeyDown: function (event) {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					props.onClick();
				}
			}
		}, spanProps ? el('span', spanProps) : el('span', null, props.label));
	}

	/**
	 * Tips box.
	 */
	function TipsBox(props) {
		var data = useSelect(function (select) {
			var cart = select('wc/store/cart').getCartData();

			return (cart && cart.extensions && cart.extensions[NAMESPACE]) || null;
		}, []);

		var busyState = useState('');
		var busy = busyState[0];
		var setBusy = busyState[1];

		var messageState = useState('');
		var message = messageState[0];
		var setMessage = messageState[1];

		var openState = useState({});
		var open = openState[0];
		var setOpen = openState[1];

		var customState = useState({});
		var customValues = customState[0];
		var setCustomValues = customState[1];

		// Let the plugin know when the primary placement is rendered
		useEffect(function () {
			if (props.placement !== 'primary') {
				return undefined;
			}

			setPrimaryMounted(true);

			return function () {
				setPrimaryMounted(false);
			};
		}, []);

		// Select the default values, unless the customer already picked something
		useEffect(function () {
			if (!data || defaultsApplied) {
				return;
			}

			defaultsApplied = true;

			data.tips.forEach(function (tip) {
				var defaults = tip.values.filter(function (item) {
					return item.is_default;
				});

				if (!defaults.length || tip.selected || getCookie(cookieName(data, tip)) !== '') {
					return;
				}

				setCookie(cookieName(data, tip), defaults[0].value, 1);

				enqueue({action: 'apply', key: tip.key, value: defaults[0].value}).catch(function (error) {
					setMessage((error && error.message) || '');
				});
			});
		}, [data]);

		if (!data || !data.tips || !data.tips.length) {
			return null;
		}

		var send = function (id, payload) {
			setBusy(id);
			setMessage('');

			return enqueue(payload).catch(function (error) {
				setMessage((error && error.message) || '');
			}).finally(function () {
				setBusy('');
			});
		};

		var applyTip = function (tip, value, id) {
			setCookie(cookieName(data, tip), value, 1);
			send(id, {action: 'apply', key: tip.key, value: value});
		};

		var removeTip = function (tip, id) {
			setCookie(cookieName(data, tip), 'none', 1);
			send(id, {action: 'remove', key: tip.key});
		};

		var clickValue = function (tip, item) {
			if (busy) {
				return;
			}

			var id = tip.key + ':' + item.value;

			if (item.active) {
				if (data.click_again) {
					removeTip(tip, id);
				}

				return;
			}

			applyTip(tip, item.value, id);
		};

		var clickNone = function (tip) {
			if (busy || !tip.selected) {
				return;
			}

			removeTip(tip, tip.key + ':none');
		};

		var clickCustomActive = function (tip) {
			if (busy || !data.click_again) {
				return;
			}

			removeTip(tip, tip.key + ':custom-active');
		};

		var addCustom = function (tip) {
			var value = customValues[tip.key] !== undefined ? customValues[tip.key] : tip.custom_value;

			if (busy || value === '' || value === null) {
				return;
			}

			applyTip(tip, String(value), tip.key + ':custom');
		};

		var renderTip = function (tip) {
			var noButton = el(ValueButton, {
				key: 'none',
				extraClass: 'wpcot-tip-value-none',
				label: data.none_label,
				active: !tip.selected,
				loading: busy === tip.key + ':none',
				onClick: function () {
					clickNone(tip);
				}
			});

			var values = [];

			if (data.no_btn_position === 'first') {
				values.push(noButton);
			}

			tip.values.forEach(function (item) {
				values.push(el(ValueButton, {
					key: 'value-' + item.value,
					label: item.label,
					active: item.active,
					isDefault: item.is_default,
					loading: busy === tip.key + ':' + item.value,
					onClick: function () {
						clickValue(tip, item);
					}
				}));
			});

			if (tip.custom_active_html) {
				values.push(el(ValueButton, {
					key: 'custom-active',
					html: tip.custom_active_html,
					active: true,
					loading: busy === tip.key + ':custom-active',
					onClick: function () {
						clickCustomActive(tip);
					}
				}));
			}

			if (data.no_btn_position === 'before_other') {
				values.push(noButton);
			}

			if (tip.custom) {
				values.push(el('div', {
					key: 'other',
					className: 'wpcot-tip-value-custom' + (busy === tip.key + ':custom' ? ' loading' : ''),
					role: 'button',
					tabIndex: 0,
					'aria-expanded': !!open[tip.key],
					onClick: function () {
						var next = Object.assign({}, open);

						next[tip.key] = !open[tip.key];
						setOpen(next);
					},
					onKeyDown: function (event) {
						if (event.key === 'Enter' || event.key === ' ') {
							event.preventDefault();
							event.currentTarget.click();
						}
					}
				}, el('span', null, data.other_label)));
			}

			if (data.no_btn_position === 'last') {
				values.push(noButton);
			}

			var children = [
				el('div', {key: 'name', className: 'wpcot-tip-name'}, tip.name),
				el('div', {key: 'desc', className: 'wpcot-tip-desc'}, tip.desc),
				el('div', {key: 'values', className: 'wpcot-tip-values'}, values)
			];

			if (tip.custom) {
				children.push(el('div', {
						key: 'custom',
						className: 'wpcot-tip-custom' + (open[tip.key] ? ' is-open' : '')
					},
					el('div', {className: 'wpcot-tip-custom-form' + (busy === tip.key + ':custom' ? ' disabled' : '')},
						el('div', {className: 'wpcot-tip-custom-form-inner'},
							el('input', {
								type: 'number',
								className: 'wpcot-tip-custom-value',
								value: customValues[tip.key] !== undefined ? customValues[tip.key] : tip.custom_value,
								min: tip.custom_min,
								max: tip.custom_max === '' ? undefined : tip.custom_max,
								step: tip.custom_step,
								onChange: function (event) {
									var next = Object.assign({}, customValues);

									next[tip.key] = event.target.value;
									setCustomValues(next);
								},
								onKeyDown: function (event) {
									if (event.key === 'Enter') {
										event.preventDefault();
										addCustom(tip);
									}
								}
							}),
							el('input', {
								type: 'button',
								className: 'wpcot-tip-custom-add',
								value: data.add_label,
								onClick: function () {
									addCustom(tip);
								}
							})
						)
					)
				));
			}

			return el('div', {
				key: tip.key,
				className: 'wpcot-tip',
				'data-key': tip.key,
				'data-name': tip.name
			}, children);
		};

		var children = data.tips.map(renderTip);

		if (message) {
			children.push(el('div', {key: 'msg', className: 'wpcot-block-msg', role: 'alert'}, message));
		}

		return el('div', {className: 'wpcot-tips wpcot-tips-block wpcot-btn-' + data.btn_style}, children);
	}

	/**
	 * Plugin root: always fills the discounts meta slot, and falls back to the order meta slot on the Checkout block
	 * when the discounts meta slot is not available.
	 */
	function TipsPlugin() {
		var fallbackState = useState(false);
		var fallback = fallbackState[0];
		var setFallback = fallbackState[1];

		useEffect(function () {
			if (!OrderMeta || !document.querySelector('.wp-block-woocommerce-checkout')) {
				return undefined;
			}

			var timer = null;

			var update = function () {
				window.clearTimeout(timer);

				if (primaryMounted) {
					setFallback(false);
				} else {
					timer = window.setTimeout(function () {
						setFallback(!primaryMounted);
					}, 1500);
				}
			};

			listeners.push(update);
			update();

			return function () {
				window.clearTimeout(timer);
				listeners = listeners.filter(function (listener) {
					return listener !== update;
				});
			};
		}, []);

		return el(Fragment, null,
			el(DiscountsMeta, null, el(TipsBox, {placement: 'primary'})),
			fallback ? el(OrderMeta, null, el(TipsBox, {placement: 'fallback'})) : null
		);
	}

	registerPlugin('wpcot-tips', {
		scope: 'woocommerce-checkout',
		render: TipsPlugin
	});
})(window.wp, window.wc);

