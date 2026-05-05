<?php
$name        = ! empty( $a['name'] ?: $a['nameAlt'] ) ? $a['name'] ?: $a['nameAlt'] : '';
$placeholder = ! empty( $a['placeholder'] ) ? $a['placeholder'] : 'Phone number';
$default     = ! empty( $a['defaultCountry'] ) ? $a['defaultCountry'] : 'US';
$disabled    = ! empty( $a['disabled'] );
$on_change   = ! empty( $a['onChange'] ) ? $a['onChange'] : '';

$countries = array(
	array( 'US', '+1', 'United States' ),
	array( 'GB', '+44', 'United Kingdom' ),
	array( 'DE', '+49', 'Germany' ),
	array( 'FR', '+33', 'France' ),
	array( 'JP', '+81', 'Japan' ),
	array( 'CN', '+86', 'China' ),
	array( 'IN', '+91', 'India' ),
	array( 'AU', '+61', 'Australia' ),
	array( 'BR', '+55', 'Brazil' ),
	array( 'RU', '+7', 'Russia' ),
	array( 'ES', '+34', 'Spain' ),
	array( 'IT', '+39', 'Italy' ),
	array( 'KR', '+82', 'South Korea' ),
	array( 'NL', '+31', 'Netherlands' ),
	array( 'SE', '+46', 'Sweden' ),
	array( 'NO', '+47', 'Norway' ),
	array( 'DK', '+45', 'Denmark' ),
	array( 'FI', '+358', 'Finland' ),
	array( 'PL', '+48', 'Poland' ),
	array( 'AT', '+43', 'Austria' ),
	array( 'CH', '+41', 'Switzerland' ),
	array( 'CA', '+1', 'Canada' ),
	array( 'MX', '+52', 'Mexico' ),
	array( 'PT', '+351', 'Portugal' ),
	array( 'IE', '+353', 'Ireland' ),
	array( 'BE', '+32', 'Belgium' ),
	array( 'NZ', '+64', 'New Zealand' ),
	array( 'SG', '+65', 'Singapore' ),
	array( 'HK', '+852', 'Hong Kong' ),
	array( 'IL', '+972', 'Israel' ),
	array( 'AE', '+971', 'UAE' ),
	array( 'SA', '+966', 'Saudi Arabia' ),
	array( 'ZA', '+27', 'South Africa' ),
	array( 'AR', '+54', 'Argentina' ),
	array( 'CL', '+56', 'Chile' ),
	array( 'CO', '+57', 'Colombia' ),
	array( 'TH', '+66', 'Thailand' ),
	array( 'MY', '+60', 'Malaysia' ),
	array( 'PH', '+63', 'Philippines' ),
	array( 'ID', '+62', 'Indonesia' ),
	array( 'VN', '+84', 'Vietnam' ),
	array( 'TW', '+886', 'Taiwan' ),
	array( 'TR', '+90', 'Turkey' ),
	array( 'GR', '+30', 'Greece' ),
	array( 'CZ', '+420', 'Czech Republic' ),
	array( 'RO', '+40', 'Romania' ),
	array( 'HU', '+36', 'Hungary' ),
	array( 'UA', '+380', 'Ukraine' ),
	array( 'NG', '+234', 'Nigeria' ),
	array( 'EG', '+20', 'Egypt' ),
	array( 'KE', '+254', 'Kenya' ),
);

$default_code  = '+1';
$default_label = 'US';
foreach ( $countries as $c ) {
	if ( $c[0] === $default || $c[1] === $default ) {
		$default_code  = $c[1];
		$default_label = $c[0];
		break;
	}
}
?>
<div
	data-wp-interactive="bsui/phone-input"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'code'        => $default_code,
		'countryCode' => $default_label,
		'number'      => '',
		'open'        => false,
		'query'       => '',
		'activeIndex' => -1,
	) ) ); ?>'
	data-bsui-phone-input
	data-wp-on-document--click="actions.handleOutsideClick"
	<?php if ( $on_change ) echo 'data-wp-on--change="' . esc_attr( $on_change ) . '"'; ?>
>
	<button
		data-bsui-focus
		data-bsui-phone-trigger
		data-wp-on--click="actions.toggle"
		data-wp-on--keydown="actions.handleTriggerKeydown"
		type="button"
		aria-haspopup="listbox"
		aria-expanded="false"
		data-wp-bind--aria-expanded="context.open"
		<?php if ( $disabled ) echo 'disabled'; ?>
	>
		<span data-bsui-phone-code data-wp-text="state.triggerLabel"></span>
		<span data-bsui-phone-chevron></span>
	</button>
	<div
		data-bsui-phone-popup
		role="listbox"
		aria-label="Select country"
		hidden
	>
		<div data-bsui-phone-search>
			<input
				data-bsui-phone-search-input
				data-wp-on--input="actions.handleSearch"
				data-wp-on--keydown="actions.handleSearchKeydown"
				type="text"
				placeholder="Search countries..."
				autocomplete="off"
			/>
		</div>
		<div data-bsui-phone-list>
			<?php foreach ( $countries as $c ) : ?>
			<div
				data-bsui-phone-option
				data-country="<?php echo esc_attr( $c[0] ); ?>"
				data-dial="<?php echo esc_attr( $c[1] ); ?>"
				data-label="<?php echo esc_attr( $c[2] ); ?>"
				role="option"
				tabindex="-1"
				data-wp-on--click="actions.selectCountry"
			><?php echo esc_html( $c[2] ); ?> <span data-bsui-phone-option-code><?php echo esc_html( $c[1] ); ?></span></div>
			<?php endforeach; ?>
		</div>
	</div>
	<input
		data-bsui-focus
		data-wp-on--input="actions.setNumber"
		type="tel"
		placeholder="<?php echo esc_attr( $placeholder ); ?>"
		<?php if ( $disabled ) echo 'disabled'; ?>
	/>
	<?php if ( '' !== $name ) : ?>
	<input
		type="hidden"
		name="<?php echo esc_attr( $name ); ?>"
		data-wp-bind--value="state.formValue"
	/>
	<?php endif; ?>
</div>
