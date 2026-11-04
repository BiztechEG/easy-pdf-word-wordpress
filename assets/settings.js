/* The settings page: the logo picker and the Gotenberg address field. */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $field = $( '#easy-pdf-word-logo' );
		var $preview = $( '.easy-pdf-word-logo-preview' );
		var $remove = $( '.easy-pdf-word-logo-remove' );

		$( '.easy-pdf-word-logo-select' ).on( 'click', function ( event ) {
			var $button = $( this );

			event.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: $button.data( 'title' ),
					button: { text: $button.data( 'button' ) },
					library: { type: [ 'image/png', 'image/jpeg' ] },
					multiple: false
				} );

				frame.on( 'select', function () {
					var image = frame.state().get( 'selection' ).first().toJSON();
					var url = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;

					$field.val( image.id );
					$preview.empty().append( $( '<img>', { src: url, alt: '' } ) );
					$remove.prop( 'hidden', false );
				} );
			}

			frame.open();
		} );

		$remove.on( 'click', function ( event ) {
			event.preventDefault();
			$field.val( '0' );
			$preview.empty();
			$remove.prop( 'hidden', true );
		} );

		function toggleGotenberg() {
			$( '.easy-pdf-word-gotenberg' ).toggle( 'gotenberg' === $( '#easy-pdf-word-engine' ).val() );
		}

		$( '#easy-pdf-word-engine' ).on( 'change', toggleGotenberg );
		toggleGotenberg();
	} );
}( jQuery ) );
