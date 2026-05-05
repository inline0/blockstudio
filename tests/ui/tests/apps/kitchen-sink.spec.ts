import { test, expect } from '@playwright/test';
import { createContexts, TestContext } from '../helpers/dual-context';

const contexts = createContexts( 'kitchen-sink', '[data-app-kitchen-sink]' );

for ( const { name, setup } of contexts ) {
	test.describe( `app/kitchen-sink (${ name })`, () => {
		let ctx: TestContext;

		test.beforeEach( async ( { page } ) => {
			ctx = await setup( page );
		} );

		// SSR: initial values rendered correctly

		test( 'SSR: checkbox renders with correct default', async () => {
			const cb = ctx.locator( '[role="checkbox"][data-wp-on--click*="setCheckbox"]' );
			await expect( cb ).toHaveAttribute( 'aria-checked', 'true' );
		} );

		test( 'SSR: switch renders with correct default', async () => {
			const sw = ctx.locator( '[role="switch"][data-wp-on--click*="setSwitch"]' );
			await expect( sw ).toHaveAttribute( 'aria-checked', 'false' );
		} );

		test( 'SSR: toggle renders with correct default', async () => {
			const tg = ctx.locator( '[aria-pressed][data-wp-on--click*="setToggle"]' );
			await expect( tg ).toHaveAttribute( 'aria-pressed', 'false' );
		} );

		test( 'SSR: number field shows default value', async () => {
			await expect( ctx.locator( '#ks-number' ) ).toHaveText( '5' );
		} );

		test( 'SSR: slider shows default value', async () => {
			await expect( ctx.locator( '#ks-slider' ) ).toHaveText( '50' );
		} );

		test( 'SSR: rating shows default value', async () => {
			await expect( ctx.locator( '#ks-rating' ) ).toHaveText( '3' );
		} );

		test( 'SSR: time picker shows default value', async () => {
			await expect( ctx.locator( '#ks-time' ) ).toHaveText( '14:30' );
		} );

		test( 'SSR: color picker shows default value', async () => {
			await expect( ctx.locator( '#ks-color' ) ).toHaveText( '#6366f1' );
		} );

		test( 'SSR: radio group shows default value', async () => {
			await expect( ctx.locator( '#ks-radio' ) ).toHaveText( 'comfortable' );
		} );

		test( 'renders all component sections', async () => {
			const headings = ctx.locator( '[data-app-kitchen-sink] h2' );
			await expect( headings.first() ).toBeVisible();
			const count = await headings.count();
			expect( count ).toBeGreaterThanOrEqual( 40 );
		} );

		// Controlled: checkbox, switch, toggle

		test( 'checkbox updates parent state', async () => {
			await expect( ctx.locator( '#ks-checkbox' ) ).toHaveText( 'true' );
			await ctx.locator( '[role="checkbox"][data-wp-on--click*="setCheckbox"]' ).click();
			await ctx.waitForTimeout( 300 );
			await expect( ctx.locator( '#ks-checkbox' ) ).toHaveText( 'false' );
		} );

		test( 'switch updates parent state', async () => {
			await expect( ctx.locator( '#ks-switch' ) ).toHaveText( 'false' );
			await ctx.locator( '[role="switch"][data-wp-on--click*="setSwitch"]' ).click();
			await ctx.waitForTimeout( 300 );
			await expect( ctx.locator( '#ks-switch' ) ).toHaveText( 'true' );
		} );

		test( 'toggle updates parent state', async () => {
			await expect( ctx.locator( '#ks-toggle' ) ).toHaveText( 'false' );
			const toggle = ctx.locator( '[aria-pressed][data-wp-on--click*="setToggle"]' );
			await toggle.click();
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-toggle' ) ).toHaveText( 'true' );
		} );

		// Controlled: input, textarea

		test( 'input updates parent state', async () => {
			await ctx.locator( 'input[data-wp-on--input*="setInput"]' ).fill( 'test@example.com' );
			await expect( ctx.locator( '#ks-input' ) ).toHaveText( 'test@example.com' );
		} );

		test( 'textarea updates parent state', async () => {
			await ctx.locator( 'textarea[data-wp-on--input*="setTextarea"]' ).fill( 'Hello world' );
			await expect( ctx.locator( '#ks-textarea' ) ).toHaveText( 'Hello world' );
		} );

		// onChange via CustomEvent: number, slider, rating, color, time, radio, select, password

		test( 'number field updates parent state', async () => {
			await expect( ctx.locator( '#ks-number' ) ).toHaveText( '5' );
			await ctx.locator( '[data-wp-on--change*="setNumber"] button[aria-label="Increase"]' ).click();
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-number' ) ).toHaveText( '6' );
		} );

		test( 'slider updates parent state', async () => {
			await expect( ctx.locator( '#ks-slider' ) ).toHaveText( '50' );
			await ctx.locator( '[data-wp-on--change*="setSlider"] [role="slider"]' ).focus();
			await ctx.page.keyboard.press( 'ArrowRight' );
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-slider' ) ).toHaveText( '51' );
		} );

		test( 'rating updates parent state', async () => {
			await expect( ctx.locator( '#ks-rating' ) ).toHaveText( '3' );
			await ctx.locator( '[data-wp-on--change*="setRating"] button[data-star="5"]' ).click();
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-rating' ) ).toHaveText( '5' );
		} );

		test( 'color picker updates parent state', async () => {
			await expect( ctx.locator( '#ks-color' ) ).toHaveText( '#6366f1' );
			await ctx.locator( '[data-wp-on--change*="setColor"] input[type="text"]' ).last().fill( '#ff0000' );
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-color' ) ).toHaveText( '#ff0000' );
		} );

		test( 'password updates parent state', async () => {
			await ctx.locator( 'input[data-wp-on--input*="setPassword"]' ).fill( 'secret' );
			await expect( ctx.locator( '#ks-password' ) ).toContainText( 'secret' );
		} );

		test( 'time picker updates parent state', async () => {
			await expect( ctx.locator( '#ks-time' ) ).toHaveText( '14:30' );
			const section = ctx.locator( '#ks-time' ).locator( '..' );
			await section.locator( '[data-bsui-time-hour]' ).focus();
			await ctx.page.keyboard.press( 'ArrowUp' );
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-time' ) ).toHaveText( '15:30' );
		} );

		test( 'radio group updates parent state', async () => {
			await expect( ctx.locator( '#ks-radio' ) ).toHaveText( 'comfortable' );
			await ctx.locator( '[data-wp-on--change*="setRadio"] [role="radio"]' ).nth( 2 ).click();
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-radio' ) ).not.toHaveText( 'comfortable' );
		} );

		test( 'select updates parent state', async () => {
			await ctx.locator( '[data-wp-on--change*="setSelect"] button' ).first().click();
			await ctx.waitForTimeout( 300 );
			await ctx.locator( '[role="option"]' ).filter( { hasText: 'Cherry' } ).first().click();
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-select' ) ).toContainText( /cherry|Cherry/ );
		} );

		// Rendering tests

		test( 'accordion renders', async () => {
			await expect( ctx.locator( '[data-app-kitchen-sink] [data-bsui-accordion-trigger]' ).first() ).toBeVisible();
		} );

		test( 'tabs render', async () => {
			await expect( ctx.locator( '[data-app-kitchen-sink] [role="tab"]' ).first() ).toBeVisible();
		} );

		test( 'collapsible renders', async () => {
			await expect( ctx.locator( '[data-app-kitchen-sink] [data-bsui-collapsible-trigger]' ).first() ).toBeVisible();
		} );

		test( 'toggle group renders', async () => {
			await expect( ctx.locator( '[data-app-kitchen-sink] [data-bsui-toggle-group-item]' ).first() ).toBeVisible();
		} );

		test( 'dialog opens and closes', async () => {
			await ctx.locator( '[data-app-kitchen-sink] [data-bsui-dialog-trigger]' ).first().locator( 'button' ).click();
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '[data-wp-interactive="bsui/dialog"][role="dialog"]:not([hidden])' ) ).toBeVisible();
			await ctx.page.keyboard.press( 'Escape' );
		} );

		test( 'popover opens', async () => {
			await ctx.locator( '[data-app-kitchen-sink] [data-bsui-popover-trigger]' ).first().locator( 'button' ).click();
			await ctx.waitForTimeout( 300 );
			await expect( ctx.locator( '[data-wp-interactive="bsui/popover"][role="dialog"]' ) ).toBeVisible();
		} );

		test( 'tooltip shows on hover', async () => {
			await ctx.locator( '[data-app-kitchen-sink] [data-bsui-tooltip-trigger]' ).first().locator( 'button' ).hover();
			await ctx.waitForTimeout( 300 );
			await expect( ctx.locator( '[role="tooltip"]' ).first() ).toBeVisible();
		} );

		test( 'menu opens', async () => {
			await ctx.locator( '[data-app-kitchen-sink] [data-bsui-menu-trigger]' ).first().locator( 'button' ).click();
			await ctx.waitForTimeout( 300 );
			await expect( ctx.locator( '[role="menu"]' ).first() ).toBeVisible();
		} );

		test( 'OTP input updates parent state', async () => {
			await expect( ctx.locator( '#ks-otp' ) ).toHaveText( '' );
			const inputs = ctx.locator( '[data-wp-on--change*="setOtp"] input[data-index]' );
			await inputs.first().fill( '1' );
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-otp' ) ).not.toHaveText( '' );
		} );

		test( 'phone input updates parent state', async () => {
			await expect( ctx.locator( '#ks-phone' ) ).toHaveText( '' );
			const phoneInput = ctx.locator( '[data-wp-on--change*="setPhone"] input[type="tel"]' ).last();
			await phoneInput.fill( '5551234' );
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-phone' ) ).not.toHaveText( '' );
		} );

		test( 'date input updates parent state', async () => {
			await expect( ctx.locator( '#ks-date' ) ).toHaveText( '' );
			const dateInput = ctx.locator( '[data-wp-on--change*="setDate"] [data-bsui-date-input-trigger] input' ).last();
			await dateInput.click();
			await ctx.waitForTimeout( 300 );
			await ctx.locator( '[data-bsui-date-input-popup]:not([hidden]) button:not([disabled])' ).first().click();
			await ctx.waitForTimeout( 500 );
			await expect( ctx.locator( '#ks-date' ) ).not.toHaveText( '' );
		} );

		test( 'file upload renders', async () => {
			await expect( ctx.locator( '[data-app-kitchen-sink] [data-bsui-file-upload]' ).first() ).toBeVisible();
		} );

		test( 'table renders', async () => {
			await expect( ctx.locator( '[data-app-kitchen-sink] table' ).first() ).toBeVisible();
		} );

		test( 'badge renders', async () => {
			await expect( ctx.locator( '[data-app-kitchen-sink] [data-bsui-badge]' ).first() ).toBeVisible();
		} );

		test( 'card renders', async () => {
			await expect( ctx.locator( '[data-app-kitchen-sink] [data-bsui-card]' ).first() ).toBeVisible();
		} );

		test( 'progress renders', async () => {
			await expect( ctx.locator( '[data-app-kitchen-sink] [role="progressbar"]' ).first() ).toBeVisible();
		} );

		test( 'separator renders', async () => {
			await expect( ctx.locator( '[data-app-kitchen-sink] [role="separator"]' ).first() ).toBeVisible();
		} );
	} );
}
