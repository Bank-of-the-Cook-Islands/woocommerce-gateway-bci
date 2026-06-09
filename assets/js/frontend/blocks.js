( function () {
	'use strict';

	var wc = window.wc || {};
	var wp = window.wp || {};
	var registry = wc.wcBlocksRegistry || {};
	var settingsApi = wc.wcSettings || {};
	var element = wp.element || {};
	var htmlEntities = wp.htmlEntities || {};

	if (
		typeof registry.registerPaymentMethod !== 'function' ||
		typeof settingsApi.getSetting !== 'function' ||
		typeof element.createElement !== 'function'
	) {
		return;
	}

	var createElement = element.createElement;
	var decodeEntities = function ( value ) {
		value = value ? String( value ) : '';

		if ( typeof htmlEntities.decodeEntities === 'function' ) {
			return htmlEntities.decodeEntities( value );
		}

		return value;
	};

	var settings = settingsApi.getSetting( 'bci_takuecom_data', {} ) || {};
	var title = decodeEntities( settings.title || 'Card (BCI TakuEcom)' );
	var description = decodeEntities(
		settings.description || 'Pay securely by card using BCI TakuEcom.'
	);
	var logoUrl = typeof settings.logoUrl === 'string' ? settings.logoUrl : '';
	var supports = Array.isArray( settings.supports ) ? settings.supports : [];

	var Content = function () {
		if ( ! description ) {
			return null;
		}

		return createElement( 'span', null, description );
	};

	var Label = function () {
		if ( ! logoUrl ) {
			return createElement( 'span', null, title );
		}

		return createElement(
			'span',
			{ className: 'bci-woo-blocks-label' },
			createElement( 'span', { key: 'title' }, title ),
			createElement( 'img', {
				key: 'logo',
				src: logoUrl,
				alt: '',
				className: 'bci-woo-blocks-logo',
				style: {
					maxHeight: '24px',
					marginLeft: '8px',
					verticalAlign: 'middle',
				},
			} )
		);
	};

	registry.registerPaymentMethod( {
		name: 'bci_takuecom',
		label: createElement( Label, null ),
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: title,
		supports: {
			features: supports,
		},
	} );
} )();
