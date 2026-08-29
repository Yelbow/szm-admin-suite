/* SZM Admin Suite — White-label media picker.
   Runs after 'media-editor' is loaded, so window.wp.media is guaranteed. */
(function () {
	var buttons = document.querySelectorAll( '.szm-as-wl-pick' );
	if ( ! buttons.length ) {
		return;
	}

	buttons.forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var target = document.getElementById( btn.getAttribute( 'data-target' ) );
			var frame  = wp.media( {
				title: 'Choose image',
				button: { text: 'Use this image' },
				library: { type: 'image' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				target.value = att.url;
			} );
			frame.open();
		} );
	} );
})();
