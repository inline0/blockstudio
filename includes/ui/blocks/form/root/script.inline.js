import { store, getContext, getElement } from '@wordpress/interactivity';

function syncFields( ref, errors ) {
	ref.querySelectorAll( '[data-bsui-field-root]' ).forEach( function ( field ) {
		var input = field.querySelector( 'input, select, textarea' );
		if ( ! input || ! input.name ) return;

		var err = errors[ input.name ];
		var hasError = !! err;
		var message = hasError ? ( Array.isArray( err ) ? err[ 0 ] : err ) : '';

		field.classList.toggle( 'bs-ui-invalid', hasError );

		var alert = field.querySelector( '[role="alert"]' );
		if ( ! alert ) {
			alert = document.createElement( 'span' );
			alert.setAttribute( 'role', 'alert' );
			alert.hidden = true;
			var wrapper = field.querySelector( ':scope > div' );
			if ( wrapper ) wrapper.appendChild( alert );
		}
		alert.hidden = ! hasError;
		alert.textContent = message;
	} );
}

store( 'bsui/form', {
	state: {
		get isSubmitting() {
			return getContext().submitting;
		},
		get isSubmitted() {
			return getContext().submitted;
		},
		get hasFormError() {
			return getContext().formError !== '';
		},
		get hasFieldError() {
			const { errors, fieldName } = getContext();
			return !! fieldName && !! errors && !! errors[ fieldName ];
		},
		get fieldError() {
			const { errors, fieldName } = getContext();
			if ( ! fieldName || ! errors ) return '';
			const err = errors[ fieldName ];
			if ( ! err ) return '';
			return Array.isArray( err ) ? err[ 0 ] : err;
		},
		get isFieldInvalid() {
			const { errors, fieldName } = getContext();
			return !! fieldName && !! errors && !! errors[ fieldName ];
		},
	},
	actions: {
		*handleSubmit( event ) {
			event.preventDefault();
			const ctx = getContext();
			const { ref } = getElement();

			if ( ! ctx.block || ! window.bs?.db ) return;

			ctx.errors = {};
			ctx.formError = '';
			ctx.submitting = true;
			ctx.submitted = false;

			const formData = new FormData( ref );
			const data = Object.fromEntries( formData.entries() );
			const db = window.bs.db( ctx.block );
			const action = ctx.action || 'create';

			let response;
			try {
				if ( 'update' === action ) {
					const id = data.id;
					delete data.id;
					response = yield db.update( id, data );
				} else if ( 'delete' === action ) {
					response = yield db.delete( data.id );
				} else {
					response = yield db.create( data );
				}
			} catch {
				ctx.formError = 'Network error.';
				ctx.submitting = false;
				return;
			}

			ctx.submitting = false;

			if ( response?.code ) {
				const errEv = new CustomEvent( 'formerror', {
					detail: response,
					bubbles: true,
					cancelable: true,
				} );
				if ( ref.dispatchEvent( errEv ) ) {
					if ( response.data?.errors ) {
						ctx.errors = response.data.errors;
					}
					ctx.formError = response.message || 'An error occurred.';
				}
			} else {
				const ev = new CustomEvent( 'formsuccess', {
					detail: response,
					bubbles: true,
					cancelable: true,
				} );
				if ( ref.dispatchEvent( ev ) ) {
					ctx.submitted = true;
				}
				ref.reset();
			}
		},
		handleReset() {
			const ctx = getContext();
			ctx.errors = {};
			ctx.formError = '';
			ctx.submitting = false;
		},
		clearFieldError() {
			const ctx = getContext();
			if ( ctx.fieldName && ctx.errors && ctx.errors[ ctx.fieldName ] ) {
				const next = { ...ctx.errors };
				delete next[ ctx.fieldName ];
				ctx.errors = next;
			}
		},
	},
	callbacks: {
		initForm() {
			const ctx = getContext();
			const { ref } = getElement();

			ref.addEventListener( 'input', function ( e ) {
				var name = e.target.name;
				if ( name && ctx.errors && ctx.errors[ name ] ) {
					var next = Object.assign( {}, ctx.errors );
					delete next[ name ];
					ctx.errors = next;
				}
			} );
		},
		syncErrors() {
			const ctx = getContext();
			const { ref } = getElement();
			syncFields( ref, ctx.errors || {} );
		},
		initField() {
			const ctx = getContext();
			if ( ctx.fieldName ) return;
			const { ref } = getElement();
			const input = ref.querySelector( 'input, select, textarea' );
			if ( input && input.name ) {
				ctx.fieldName = input.name;
			}
		},
	},
} );
