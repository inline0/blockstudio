import { store, getContext } from '@wordpress/interactivity';

store( 'app/kitchen-sink', {
	actions: {
		// Controlled components: parent owns state, toggles directly
		setCheckbox() {
			getContext().checkboxOn = ! getContext().checkboxOn;
		},
		setSwitch() {
			getContext().switchOn = ! getContext().switchOn;
		},
		setToggle() {
			getContext().toggleOn = ! getContext().toggleOn;
		},

		// onChange components: read event.detail.value
		setRadio( event ) {
			if ( event.detail?.value ) getContext().radioValue = event.detail.value;
		},
		setSelect( event ) {
			if ( event.detail?.value ) getContext().selectValue = event.detail.value;
		},
		setNumber( event ) {
			if ( event.detail?.value !== undefined ) getContext().numberValue = event.detail.value;
		},
		setSlider( event ) {
			if ( event.detail?.value !== undefined ) getContext().sliderValue = event.detail.value;
		},
		setRating( event ) {
			if ( event.detail?.value !== undefined ) getContext().ratingValue = event.detail.value;
		},
		setColor( event ) {
			if ( event.detail?.value ) getContext().colorValue = event.detail.value;
		},
		setTime( event ) {
			if ( event.detail?.value ) getContext().timeValue = event.detail.value;
		},

		// Input components: read event.target.value (native input events)
		setInput( event ) {
			getContext().inputValue = event.target.value;
		},
		setTextarea( event ) {
			getContext().textareaValue = event.target.value;
		},
		setPassword( event ) {
			if ( event.detail?.value !== undefined ) {
				getContext().passwordValue = event.detail.value;
			} else if ( event.target?.value !== undefined ) {
				getContext().passwordValue = event.target.value;
			}
		},

		// Placeholder handlers
		setPhone( event ) {
			if ( event.detail?.value !== undefined ) getContext().phoneValue = event.detail.value;
		},
		setDate( event ) {
			if ( event.detail?.value !== undefined ) getContext().dateValue = event.detail.value;
		},
		setOtp( event ) {
			if ( event.detail?.value !== undefined ) getContext().otpValue = event.detail.value;
		},
		setFile() {},
	},
} );
