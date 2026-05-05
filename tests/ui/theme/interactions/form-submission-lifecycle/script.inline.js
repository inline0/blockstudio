import { store, getContext } from '@wordpress/interactivity';

store( 'interaction/form-submission-lifecycle', {
	actions: {
		setName( event ) {
			getContext().nameValue = event.target.value;
		},
		setEmail( event ) {
			getContext().emailValue = event.target.value;
		},
		*handleSubmit( event ) {
			event.preventDefault();
			const ctx = getContext();
			ctx.submitting = true;
			ctx.submitted = false;
			ctx.statusMessage = '';

			yield new Promise( ( resolve ) => setTimeout( resolve, 500 ) );

			ctx.submitting = false;
			ctx.submitted = true;
			ctx.statusMessage = 'Form submitted successfully';
			ctx.nameValue = '';
			ctx.emailValue = '';
		},
	},
} );
