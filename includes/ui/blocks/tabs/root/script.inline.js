import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'bsui/tabs', {
	state: {
		get isActive() {
			const { activeValue, value } = getContext();
			return String( activeValue ) === String( value );
		},
		get ariaSelected() {
			const { activeValue, value } = getContext();
			return String( activeValue ) === String( value ) ? 'true' : 'false';
		},
		get tabIndex() {
			const { activeValue, value } = getContext();
			return String( activeValue ) === String( value ) ? 0 : -1;
		},
		get panelHidden() {
			const { activeValue, value } = getContext();
			return String( activeValue ) !== String( value );
		},
		get panelTabIndex() {
			const { activeValue, value } = getContext();
			return String( activeValue ) === String( value ) ? 0 : -1;
		},
	},
	actions: {
		selectTab() {
			const ctx = getContext();
			if ( ctx.disabled ) return;
			ctx.activeValue = ctx.value;
		},
		handleKeyDown( event ) {
			const { ref } = getElement();
			const tablist = ref.closest( '[role="tablist"]' );
			if ( ! tablist ) return;

			const tabs = [
				...tablist.querySelectorAll( '[role="tab"]:not([disabled])' ),
			];
			const current = tabs.indexOf( ref );
			if ( current === -1 ) return;

			const ctx = getContext();
			const vertical = ctx.orientation === 'vertical';
			const nextKey = vertical ? 'ArrowDown' : 'ArrowRight';
			const prevKey = vertical ? 'ArrowUp' : 'ArrowLeft';
			const loop = ctx.loop !== false;

			let next = -1;
			switch ( event.key ) {
				case nextKey:
					event.preventDefault();
					if ( current < tabs.length - 1 ) {
						next = current + 1;
					} else if ( loop ) {
						next = 0;
					}
					break;
				case prevKey:
					event.preventDefault();
					if ( current > 0 ) {
						next = current - 1;
					} else if ( loop ) {
						next = tabs.length - 1;
					}
					break;
				case 'Home':
					event.preventDefault();
					next = 0;
					break;
				case 'End':
					event.preventDefault();
					next = tabs.length - 1;
					break;
			}

			if ( next >= 0 ) {
				tabs[ next ].focus();
				// activateOnFocus: activate tab when focused via keyboard (default true, matching WAI-ARIA recommendation)
				if ( ctx.activateOnFocus !== false ) {
					const nextValue = tabs[ next ].getAttribute(
						'data-tab-value'
					);
					if ( nextValue !== null ) {
						ctx.activeValue = nextValue;
					}
				}
			}
		},
	},
	callbacks: {
		init() {
			const ctx = getContext();

			// If active value is set and the tab exists and is not disabled, keep it
			if ( ctx.activeValue ) {
				const { ref } = getElement();
				const activeTab = ref.querySelector(
					`[role="tab"][data-tab-value="${ ctx.activeValue }"]:not([disabled])`
				);
				if ( activeTab ) return;
			}

			// Fallback: select first non-disabled tab
			const { ref } = getElement();
			const firstEnabled = ref.querySelector(
				'[role="tab"]:not([disabled])'
			);
			if ( firstEnabled ) {
				ctx.activeValue =
					firstEnabled.getAttribute( 'data-tab-value' ) || '';
			}
		},
	},
} );
