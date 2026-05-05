<?php
$name  = ! empty( $a['name'] ) ? $a['name'] : '';
$now   = new DateTime();
$month = (int) $now->format( 'n' );
$year  = (int) $now->format( 'Y' );
$today = $now->format( 'Y-m-d' );
$days  = (int) $now->format( 't' );
$first = (int) ( new DateTime( "$year-$month-01" ) )->format( 'w' );
?>
<div
	data-wp-interactive="bsui/calendar"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		'selected' => '',
		'name'     => $name,
	) ) ); ?>'
	data-bsui-calendar
>
	<div data-bsui-calendar-header>
		<span><?php echo esc_html( $now->format( 'F Y' ) ); ?></span>
	</div>
	<div data-bsui-calendar-weekdays>
		<span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
	</div>
	<div data-bsui-calendar-grid data-wp-on--click="actions.selectDay">
		<?php for ( $i = 0; $i < $first; $i++ ) : ?>
		<span></span>
		<?php endfor; ?>
		<?php for ( $d = 1; $d <= $days; $d++ ) :
			$date     = sprintf( '%d-%02d-%02d', $year, $month, $d );
			$is_today = $date === $today ? ' data-today' : '';
		?>
		<button data-bsui-focus data-date="<?php echo esc_attr( $date ); ?>"<?php echo $is_today; ?>><?php echo $d; ?></button>
		<?php endfor; ?>
	</div>
	<?php if ( '' !== $name ) : ?>
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>" data-wp-bind--value="state.selectedValue" value="" />
	<?php endif; ?>
</div>
