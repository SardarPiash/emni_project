/**
 * eBook Store — small, dependency-free UI enhancements.
 *
 * 1. Reveal: elements with .ebook-reveal fade/slide in once when scrolled into view.
 *    Content is visible by default; the hidden start state only applies when the
 *    <html> element has the "js" class (added in <head>) and motion is allowed.
 * 2. "Added to cart" feedback: the header cart badge pulses once.
 */
( function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/* ---------- 1. Reveal on scroll ---------- */
	var items = document.querySelectorAll( '.ebook-reveal' );

	function showAll() {
		for ( var i = 0; i < items.length; i++ ) {
			items[ i ].classList.add( 'is-visible' );
		}
	}

	if ( items.length ) {
		if ( reduceMotion || ! ( 'IntersectionObserver' in window ) ) {
			showAll();
		} else {
			var observer = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( entry.isIntersecting ) {
							entry.target.classList.add( 'is-visible' );
							observer.unobserve( entry.target );
						}
					} );
				},
				{ rootMargin: '0px 0px -10% 0px', threshold: 0.08 }
			);
			for ( var j = 0; j < items.length; j++ ) {
				observer.observe( items[ j ] );
			}
			// Safety net: never leave content hidden (e.g. printed pages, odd browsers).
			window.setTimeout( showAll, 2500 );
		}
	}

	/* ---------- Shop: keep the selected category chip visible on phones ---------- */
	var chips = document.querySelector( '.ebook-shop__cats' );
	var currentChip = chips && chips.querySelector( '.is-current' );
	if ( currentChip && chips.scrollWidth > chips.clientWidth ) {
		var item = currentChip.parentNode;
		chips.scrollLeft = Math.max( 0, item.offsetLeft - chips.offsetLeft - 16 );
	}

	/* ---------- 2. "Added to cart" feedback ---------- */
	function bumpCart() {
		if ( reduceMotion ) {
			return;
		}
		var badges = document.querySelectorAll( '.ebook-cart__count, .footer-cart-contents .count' );
		for ( var k = 0; k < badges.length; k++ ) {
			var badge = badges[ k ];
			badge.classList.remove( 'is-bumped' );
			void badge.offsetWidth; // Restart the animation.
			badge.classList.add( 'is-bumped' );
		}
	}

	// After a normal "Add to Cart" (page reload), WooCommerce shows a success notice.
	if ( document.querySelector( '.woocommerce-message .wc-forward, .woocommerce-message a.button' ) ) {
		window.setTimeout( bumpCart, 300 );
	}

	// After an AJAX add-to-cart (WooCommerce triggers a jQuery event).
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'added_to_cart wc_fragments_refreshed', function ( event ) {
			if ( 'added_to_cart' === event.type ) {
				window.setTimeout( bumpCart, 50 );
			}
		} );
	}
} )();
