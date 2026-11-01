/*
	Scales a legacy <frameset> so it stays proportionate to the page zoom.

	The site scales itself to PAGE_ZOOM with CSS `zoom` on `body` (see
	`htdocs/style/client.css` and `views/layouts/page.clarity.html`). That fixes
	the content pages, but a frameset's track sizes are frameset *units*, not
	CSS pixels: a `cols="*,794,*"` shell keeps its 794 px middle column while the
	pages inside it grow by PAGE_ZOOM, so the content overflows its frame by
	exactly that factor and the frame grows a horizontal scrollbar.

	Scaling every fixed track by the same factor restores the proportions. `*`
	and `%` tracks are already relative and are left alone.

	Keep PAGE_ZOOM in step with the two CSS declarations; `ZoomFactorTest`
	asserts all three agree.

	Only documents that are still framesets need this. The chat terminal was
	converted to a CSS grid precisely so it would not.
*/
( function() {
	var PAGE_ZOOM = 1.333;

	function scaleTrack( track ) {
		track = track.trim();
		if ( track === '' ) {
			return track;
		}
		// Relative tracks: bare `*`, `2*`, or a percentage.
		if ( track === '*' || track.indexOf( '%' ) !== -1 || /\d\*$/.test( track ) ) {
			return track;
		}
		var value = parseFloat( track );
		if ( isNaN( value ) ) {
			return track;
		}
		// Round to 1/100 px: sub-pixel tracks are honoured but stay readable.
		return String( Math.round( value * PAGE_ZOOM * 100 ) / 100 );
	}

	function scaleList( list ) {
		return list.split( ',' ).map( scaleTrack ).join( ',' );
	}

	function apply() {
		var sets = document.getElementsByTagName( 'frameset' );
		for ( var i = 0; i < sets.length; i++ ) {
			var cols = sets[ i ].getAttribute( 'cols' );
			var rows = sets[ i ].getAttribute( 'rows' );
			if ( cols ) {
				sets[ i ].setAttribute( 'cols', scaleList( cols ) );
			}
			if ( rows ) {
				sets[ i ].setAttribute( 'rows', scaleList( rows ) );
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', apply );
	} else {
		apply();
	}
} )();
