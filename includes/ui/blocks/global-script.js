let lockCount = 0;

window.__bsui = window.__bsui || {};

window.__bsui.lockScroll = function () {
	lockCount++;
	if ( lockCount === 1 ) {
		const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
		document.documentElement.setAttribute( 'data-bs-ui-scroll-locked', '' );
		document.body.style.overflow = 'hidden';
		if ( scrollbarWidth > 0 ) {
			document.body.style.paddingRight = scrollbarWidth + 'px';
		}
	}
};

window.__bsui.unlockScroll = function () {
	lockCount = Math.max( 0, lockCount - 1 );
	if ( lockCount === 0 ) {
		document.documentElement.removeAttribute( 'data-bs-ui-scroll-locked' );
		document.body.style.overflow = '';
		document.body.style.paddingRight = '';
	}
};

window.__bsui.getAnchor = function ( el ) {
	if ( ! el ) return el;
	var btn = el.querySelector( '[data-bsui-button]' );
	return btn || el;
};

var FORWARD_ATTRS = [
	'aria-haspopup', 'aria-expanded', 'aria-controls',
	'aria-describedby', 'aria-labelledby', 'id',
];

window.__bsui.forwardTriggerProps = function ( wrapper ) {
	var child = wrapper.querySelector( '[data-bsui-button], button, a' );
	if ( ! child || child === wrapper ) return;

	FORWARD_ATTRS.forEach( function ( attr ) {
		var val = wrapper.getAttribute( attr );
		if ( val !== null && ! child.hasAttribute( attr ) ) {
			child.setAttribute( attr, val );
		}
	} );

	var observer = new MutationObserver( function ( mutations ) {
		mutations.forEach( function ( m ) {
			if ( m.type === 'attributes' && FORWARD_ATTRS.indexOf( m.attributeName ) !== -1 ) {
				var v = wrapper.getAttribute( m.attributeName );
				if ( v !== null ) {
					child.setAttribute( m.attributeName, v );
				} else {
					child.removeAttribute( m.attributeName );
				}
			}
		} );
	} );

	observer.observe( wrapper, { attributes: true, attributeFilter: FORWARD_ATTRS } );
};

window.__bsui.FOCUSABLE =
	'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

window.__bsui.focusItem = function ( items, index ) {
	if ( index >= 0 && index < items.length ) {
		items[ index ].focus();
		return index;
	}
	return -1;
};

window.__bsui.typeahead = function () {
	var buffer = '';
	var timeout = null;
	return {
		handle: function ( key, items, getText ) {
			if ( key.length !== 1 ) return -1;
			clearTimeout( timeout );
			buffer += key.toLowerCase();
			var match = -1;
			for ( var i = 0; i < items.length; i++ ) {
				var text = getText ? getText( items[ i ] ) : ( items[ i ].textContent || '' );
				if ( text.trim().toLowerCase().indexOf( buffer ) === 0 ) {
					match = i;
					break;
				}
			}
			timeout = setTimeout( function () { buffer = ''; }, 500 );
			return match;
		},
		reset: function () {
			buffer = '';
			clearTimeout( timeout );
		},
	};
};

document.querySelectorAll(
	'[data-bsui-dialog-trigger], [data-bsui-alert-dialog-trigger], [data-bsui-drawer-trigger], ' +
	'[data-bsui-menu-trigger], [data-bsui-popover-trigger], [data-bsui-collapsible-trigger], ' +
	'[data-bsui-tooltip-trigger]'
).forEach( window.__bsui.forwardTriggerProps );
