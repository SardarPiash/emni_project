/**
 * Hero Slides edit screen: choose / remove images from the Media Library.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		$( '.ebookstore-image-field' ).each( function () {
			var $field = $( this );
			var $input = $field.find( 'input[type="hidden"]' );
			var $preview = $field.find( '.ebookstore-image-field__preview' );
			var $remove = $field.find( '.ebookstore-image-field__remove' );
			var frame;

			$field.on( 'click', '.ebookstore-image-field__choose', function ( event ) {
				event.preventDefault();
				if ( ! frame ) {
					frame = wp.media( {
						title: ebookstoreHeroAdmin.title,
						button: { text: ebookstoreHeroAdmin.button },
						library: { type: 'image' },
						multiple: false
					} );
					frame.on( 'select', function () {
						var image = frame.state().get( 'selection' ).first().toJSON();
						var url = ( image.sizes && image.sizes.medium ) ? image.sizes.medium.url : image.url;
						$input.val( image.id );
						$preview.empty().append( $( '<img>', { src: url, alt: '' } ) );
						$remove.prop( 'hidden', false );
					} );
				}
				frame.open();
			} );

			$remove.on( 'click', function ( event ) {
				event.preventDefault();
				$input.val( '' );
				$preview.empty().append( $( '<span>', { 'class': 'ebookstore-image-field__empty', text: ebookstoreHeroAdmin.empty } ) );
				$remove.prop( 'hidden', true );
			} );
		} );
	} );
} )( jQuery );
