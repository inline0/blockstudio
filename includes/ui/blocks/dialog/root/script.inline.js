import { store, getContext, getElement } from '@wordpress/interactivity';

let openDialogStack = [];

function updateNestedStyles() {
	const total = openDialogStack.length;
	openDialogStack.forEach( ( entry, i ) => {
		const nestedCount = total - 1 - i;
		entry.popup.style.setProperty( '--bs-ui-nested-dialogs', nestedCount );

		if ( nestedCount > 0 ) {
			entry.popup.setAttribute( 'data-nested-dialog-open', '' );
		} else {
			entry.popup.removeAttribute( 'data-nested-dialog-open' );
		}
	} );
}

function portalToBody( el ) {
	if ( ! el || el.parentNode === document.body ) return null;
	const placeholder = document.createComment( 'bs-ui-dialog' );
	el.parentNode.insertBefore( placeholder, el );
	document.body.appendChild( el );
	return placeholder;
}

function unportal( el, placeholder ) {
	if ( ! el || ! placeholder?.parentNode ) return;
	placeholder.parentNode.insertBefore( el, placeholder );
	placeholder.remove();
}

function closeTopDialog() {
	if ( openDialogStack.length === 0 ) return;
	const entry = openDialogStack.pop();

	entry.popup.removeEventListener( 'keydown', entry._onKeyDown );
	entry._closeButtons.forEach( ( { el, handler } ) =>
		el.removeEventListener( 'click', handler )
	);
	if ( entry._backdropHandler ) {
		entry.backdrop.removeEventListener( 'click', entry._backdropHandler );
	}

	// Animate out
	entry.popup.classList.add( 'bs-ui-entering' );
	if ( entry.backdrop?.parentNode === document.body ) {
		entry.backdrop.classList.add( 'bs-ui-entering' );
	}

	if ( entry.ctx ) entry.ctx.open = false;

	// Restore hidden roots immediately so parent content is visible during exit
	if ( entry._hiddenRoots ) {
		entry._hiddenRoots.forEach( ( el ) => {
			el.style.removeProperty( 'display' );
		} );
	}

	if ( openDialogStack.length > 0 ) {
		const nowTop = openDialogStack[ openDialogStack.length - 1 ];
		nowTop.popup.style.removeProperty( 'height' );
		nowTop.popup.style.removeProperty( 'overflow' );
	}

	updateNestedStyles();

	const trigger = entry.root?.querySelector( '[data-bsui-dialog-trigger]' );

	setTimeout( () => {
		entry.popup.setAttribute( 'hidden', '' );
		entry.popup.classList.remove( 'bs-ui-entering' );
		if ( entry.backdrop ) {
			entry.backdrop.setAttribute( 'hidden', '' );
			entry.backdrop.classList.remove( 'bs-ui-entering' );
		}

		unportal( entry.popup, entry.popupPlaceholder );
		if ( entry.backdrop ) unportal( entry.backdrop, entry.backdropPlaceholder );

		entry.popup.style.removeProperty( '--bs-ui-nested-dialogs' );
		entry.popup.removeAttribute( 'data-nested-dialog-open' );

		window.__bsui.unlockScroll();

		window.__bsui.getAnchor( trigger )?.focus();
	}, 150 );
}

function openDialog( root, ctx ) {
	const popup = root.querySelector( ':scope [role="dialog"]' );
	const backdrop = root.querySelector( ':scope [data-bsui-dialog-backdrop]' );
	if ( ! popup ) return;

	window.__bsui.lockScroll();

	const isNested = openDialogStack.length > 0;

	// Only show backdrop for the outermost dialog
	let backdropPlaceholder = null;
	if ( backdrop && ! isNested ) {
		backdropPlaceholder = portalToBody( backdrop );
		backdrop.removeAttribute( 'hidden' );
	}

	const popupPlaceholder = portalToBody( popup );
	popup.removeAttribute( 'hidden' );

	// Trigger entry animation via class
	popup.classList.add( 'bs-ui-entering' );
	if ( backdrop && ! isNested ) {
		backdrop.classList.add( 'bs-ui-entering' );
	}
	requestAnimationFrame( () => {
		popup.classList.remove( 'bs-ui-entering' );
		if ( backdrop && ! isNested ) {
			backdrop.classList.remove( 'bs-ui-entering' );
		}
	} );

	// Hide nested dialog root blocks inside the parent popup
	// and match parent height to this popup for consistent peeking
	const hiddenRoots = [];
	if ( isNested ) {
		const parentEntry = openDialogStack[ openDialogStack.length - 1 ];
		parentEntry.popup
			.querySelectorAll( '[data-bsui-dialog-root]' )
			.forEach( ( el ) => {
				el.style.display = 'none';
				hiddenRoots.push( el );
			} );

		// Match parent height to child so parent peeks evenly
		requestAnimationFrame( () => {
			const childHeight = popup.offsetHeight;
			parentEntry.popup.style.height = childHeight + 'px';
			parentEntry.popup.style.overflow = 'hidden';
		} );
	}

	const entry = {
		root,
		popup,
		backdrop,
		popupPlaceholder,
		backdropPlaceholder,
		ctx,
		_closeButtons: [],
		_hiddenRoots: hiddenRoots,
	};

	const closeButtons = popup.querySelectorAll( '[data-bsui-dialog-close]' );
	closeButtons.forEach( ( btn ) => {
		const handler = () => closeTopDialog();
		btn.addEventListener( 'click', handler );
		entry._closeButtons.push( { el: btn, handler } );
	} );

	if ( backdrop && ! isNested && ctx?.dismissable !== false ) {
		entry._backdropHandler = () => closeTopDialog();
		backdrop.addEventListener( 'click', entry._backdropHandler );
	}

	entry._onKeyDown = ( e ) => {
		if ( e.key === 'Tab' ) {
			const focusable = [ ...popup.querySelectorAll( window.__bsui.FOCUSABLE ) ];
			if ( ! focusable.length ) return;
			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	};
	popup.addEventListener( 'keydown', entry._onKeyDown );

	openDialogStack.push( entry );
	updateNestedStyles();

	requestAnimationFrame( () => {
		const focusable = popup.querySelector( window.__bsui.FOCUSABLE );
		( focusable || popup ).focus();
	} );
}

document.addEventListener( 'keydown', ( e ) => {
	if ( e.key !== 'Escape' || openDialogStack.length === 0 ) return;
	const top = openDialogStack[ openDialogStack.length - 1 ];
	if ( top.ctx && top.ctx.dismissable === false ) return;
	e.preventDefault();
	closeTopDialog();
} );

store( 'bsui/dialog', {
	state: {
		get popupHidden() {
			return ! getContext().open;
		},
		get backdropHidden() {
			return ! getContext().open;
		},
	},
	actions: {
		open() {
			const ctx = getContext();
			const { ref } = getElement();
			ctx.open = true;
			const root = ref.closest( '[data-bsui-dialog-root]' );
			openDialog( root, ctx );
		},
		close() {
			closeTopDialog();
		},
		handleBackdropClick() {
			closeTopDialog();
		},
		handleEscape() {},
		handleFocusTrap() {},
	},
} );
