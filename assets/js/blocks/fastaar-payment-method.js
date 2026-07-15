/**
 * Registers the Fastaar gateway with the WooCommerce Cart & Checkout blocks.
 * Plain JS on purpose — this plugin has no build step, so no JSX/bundler is used.
 */
( function ( registerPaymentMethod, getSetting, createElement, decodeEntities, __ ) {
	'use strict';

	var settings = getSetting( 'fastaar_data', {} );
	var label = decodeEntities( settings.title || '' ) || __( 'Fastaar', 'fastaar-pay' );

	var Content = function () {
		return createElement(
			'div',
			null,
			decodeEntities( settings.description || '' )
		);
	};

	var Label = function () {
		if ( ! settings.icon ) {
			return label;
		}

		return createElement(
			'span',
			{ style: { display: 'inline-flex', alignItems: 'center', gap: '6px' } },
			createElement( 'img', {
				src: settings.icon,
				alt: '',
				style: { height: '20px', width: '20px' },
			} ),
			createElement( 'span', null, label )
		);
	};

	registerPaymentMethod( {
		name: 'fastaar',
		label: createElement( Label ),
		ariaLabel: label,
		content: createElement( Content ),
		edit: createElement( Content ),
		canMakePayment: function () {
			return true;
		},
		supports: {
			features: settings.supports || [],
		},
	} );
} )(
	window.wc.wcBlocksRegistry.registerPaymentMethod,
	window.wc.wcSettings.getSetting,
	window.wp.element.createElement,
	window.wp.htmlEntities.decodeEntities,
	window.wp.i18n.__
);
