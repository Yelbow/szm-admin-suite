// SZM Admin Suite, module Editor. Config in window.szmAsEditor (module.php).
wp.domReady( function () {
	var cfg = window.szmAsEditor || {};

	// Start-pattern popup stays on: the tick-box that turns it off is hidden in CSS, keep the preference on too.
	if ( cfg.lockPatternPopup ) {
		var prefs = wp.data.select( 'core/preferences' );
		if ( prefs && prefs.get( 'core', 'enableChoosePatternModal' ) === false ) {
			wp.data.dispatch( 'core/preferences' ).set( 'core', 'enableChoosePatternModal', true );
		}
	}

	// Sidebar + taxonomy panels open, once per browser session (afterwards the user decides).
	if ( cfg.sidebarOpen ) {
		var first = true;
		try { first = ! window.sessionStorage.getItem( 'szm-as-editor-sidebar' ); window.sessionStorage.setItem( 'szm-as-editor-sidebar', '1' ); } catch ( e ) {}
		if ( first ) {
			setTimeout( function () {
				var iface = wp.data.dispatch( 'core/interface' );
				var sel = wp.data.select( 'core/interface' );
				if ( iface && sel && ! sel.getActiveComplementaryArea( 'core' ) ) iface.enableComplementaryArea( 'core', 'edit-post/document' );
				var type = wp.data.select( 'core/editor' ).getCurrentPostType();
				wp.data.resolveSelect( 'core' ).getPostType( type ).then( function ( pt ) {
					( ( pt && pt.taxonomies ) || [] ).forEach( function ( tax ) {
						var panel = 'taxonomy-panel-' + tax;
						if ( ! wp.data.select( 'core/editor' ).isEditorPanelOpened( panel ) ) wp.data.dispatch( 'core/editor' ).toggleEditorPanelOpened( panel );
					} );
				} );
			}, 1500 );
		}
	}

	// Hide blocks from the inserter (supports.inserter = false), content untouched. Plugins may register after us: retry a few times.
	var rules = ( cfg.hiddenBlocks || [] ).map( function ( r ) {
		var neg = r.charAt( 0 ) === '!';
		var p = neg ? r.slice( 1 ) : r;
		var wild = p.slice( -1 ) === '*';
		return { neg: neg, p: wild ? p.slice( 0, -1 ) : p, wild: wild };
	} );
	function match( rule, name ) { return rule.wild ? name.indexOf( rule.p ) === 0 : name === rule.p; }
	function hidden( name ) {
		var hit = false;
		rules.forEach( function ( r ) { if ( ! r.neg && match( r, name ) ) hit = true; } );
		if ( ! hit ) return false;
		return ! rules.some( function ( r ) { return r.neg && match( r, name ); } );
	}
	function hide() {
		var changed = wp.data.select( 'core/blocks' ).getBlockTypes()
			.filter( function ( b ) { return hidden( b.name ) && ( b.supports || {} ).inserter !== false; } )
			.map( function ( b ) { return Object.assign( {}, b, { supports: Object.assign( {}, b.supports, { inserter: false } ) } ); } );
		if ( changed.length ) wp.data.dispatch( 'core/blocks' ).addBlockTypes( changed );
	}
	if ( rules.length ) [ 0, 1000, 3000, 6000 ].forEach( function ( ms ) { setTimeout( hide, ms ); } );
} );
