/**
 * eBook Store — hero carousel (vanilla JS, no library).
 *
 * Arrows, dots, keyboard (Left/Right when focus is inside), swipe on touch
 * screens, autoplay that pauses on hover/focus and when the tab is hidden.
 * No autoplay for visitors who prefer reduced motion (the admin can also set
 * it to 0). Follows the WAI-ARIA carousel pattern.
 */
( function () {
	'use strict';

	var root = document.querySelector( '.ebook-carousel--multi' );
	if ( ! root ) {
		return;
	}

	var slides = Array.prototype.slice.call( root.querySelectorAll( '.ebook-carousel__slide' ) );
	var dots = Array.prototype.slice.call( root.querySelectorAll( '.ebook-carousel__dot' ) );
	var prev = root.querySelector( '.ebook-carousel__arrow--prev' );
	var next = root.querySelector( '.ebook-carousel__arrow--next' );
	var status = root.querySelector( '.ebook-carousel__status' );
	var viewport = root.querySelector( '.ebook-carousel__viewport' );

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var seconds = parseInt( root.getAttribute( 'data-autoplay' ), 10 ) || 0;
	var canAutoplay = seconds > 0 && ! reduceMotion && slides.length > 1;

	var current = 0;
	var timer = null;
	var hovering = false;
	var focusInside = false;

	root.classList.add( 'is-ready' );

	function goTo( index, announce ) {
		index = ( index + slides.length ) % slides.length;
		if ( index === current ) {
			return;
		}
		slides[ current ].classList.remove( 'is-active' );
		slides[ current ].setAttribute( 'aria-hidden', 'true' );
		slides[ current ].setAttribute( 'inert', '' );
		dots[ current ] && dots[ current ].removeAttribute( 'aria-current' );

		current = index;
		var slide = slides[ current ];
		// Lazy images in hidden slides: load before showing.
		var img = slide.querySelector( 'img[loading="lazy"]' );
		if ( img ) {
			img.loading = 'eager';
		}
		slide.classList.add( 'is-active' );
		slide.removeAttribute( 'aria-hidden' );
		slide.removeAttribute( 'inert' );
		dots[ current ] && dots[ current ].setAttribute( 'aria-current', 'true' );

		if ( announce && status ) {
			var title = slide.querySelector( '.ebook-carousel__title' );
			status.textContent = slide.getAttribute( 'aria-label' ) + ( title ? ': ' + title.textContent : '' );
		}
	}

	function stop() {
		if ( timer ) {
			window.clearInterval( timer );
			timer = null;
		}
	}

	function start() {
		stop();
		if ( canAutoplay && ! hovering && ! focusInside && ! document.hidden ) {
			timer = window.setInterval( function () {
				goTo( current + 1, false );
			}, seconds * 1000 );
		}
	}

	/* Controls */
	prev && prev.addEventListener( 'click', function () {
		goTo( current - 1, true );
		start();
	} );
	next && next.addEventListener( 'click', function () {
		goTo( current + 1, true );
		start();
	} );
	dots.forEach( function ( dot, i ) {
		dot.addEventListener( 'click', function () {
			goTo( i, true );
			start();
		} );
	} );

	/* Keyboard: Left / Right while focus is inside the carousel */
	root.addEventListener( 'keydown', function ( event ) {
		if ( 'ArrowLeft' === event.key ) {
			event.preventDefault();
			goTo( current - 1, true );
			start();
		} else if ( 'ArrowRight' === event.key ) {
			event.preventDefault();
			goTo( current + 1, true );
			start();
		}
	} );

	/* Pause while hovered or focused; resume afterwards */
	root.addEventListener( 'mouseenter', function () {
		hovering = true;
		stop();
	} );
	root.addEventListener( 'mouseleave', function () {
		hovering = false;
		start();
	} );
	root.addEventListener( 'focusin', function () {
		focusInside = true;
		stop();
	} );
	root.addEventListener( 'focusout', function ( event ) {
		if ( ! root.contains( event.relatedTarget ) ) {
			focusInside = false;
			start();
		}
	} );
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			stop();
		} else {
			start();
		}
	} );

	/* Swipe (touch / pen) */
	var startX = null;
	var startY = null;
	viewport.addEventListener( 'pointerdown', function ( event ) {
		if ( 'mouse' === event.pointerType ) {
			return;
		}
		startX = event.clientX;
		startY = event.clientY;
	} );
	viewport.addEventListener( 'pointerup', function ( event ) {
		if ( null === startX ) {
			return;
		}
		var dx = event.clientX - startX;
		var dy = event.clientY - startY;
		startX = startY = null;
		if ( Math.abs( dx ) > 45 && Math.abs( dx ) > Math.abs( dy ) ) {
			goTo( current + ( dx < 0 ? 1 : -1 ), true );
			start();
		}
	} );
	viewport.addEventListener( 'pointercancel', function () {
		startX = startY = null;
	} );

	start();
} )();
