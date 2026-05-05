import { store } from '@wordpress/interactivity';

const { state } = store( 'interaction/multi-step-workflow', {
	state: {
		get isStep1() {
			return state.currentStep === 1 && ! state.submitted;
		},
		get isStep2() {
			return state.currentStep === 2 && ! state.submitted;
		},
		get isStep3() {
			return state.currentStep === 3;
		},
		get stepLabel() {
			return 'Step ' + state.currentStep + ' of 3';
		},
	},
	actions: {
		setEmail( event ) {
			state.email = event.target.value;
			state.error = '';
		},
		setName( event ) {
			state.name = event.target.value;
			state.error = '';
		},
		continueStep() {
			if ( state.currentStep === 1 ) {
				if ( state.email.trim() === '' ) {
					state.error = 'Email is required.';
					return;
				}
				state.error = '';
				state.currentStep = 2;
			} else if ( state.currentStep === 2 ) {
				if ( state.name.trim() === '' ) {
					state.error = 'Name is required.';
					return;
				}
				state.error = '';
				state.currentStep = 3;
			}
		},
		back() {
			state.error = '';
			if ( state.currentStep > 1 ) {
				state.currentStep = state.currentStep - 1;
			}
		},
		submit() {
			state.submitted = true;
		},
	},
} );
