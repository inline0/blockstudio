<?php
wp_interactivity_state( 'app/kitchen-sink', array() );
?>
<div
	data-wp-interactive="app/kitchen-sink"
	data-wp-context='<?php echo esc_attr( wp_json_encode( array(
		"checkboxOn"    => true,
		"switchOn"      => false,
		"toggleOn"      => false,
		"radioValue"    => "comfortable",
		"selectValue"   => "",
		"inputValue"    => "",
		"textareaValue" => "",
		"numberValue"   => 5,
		"sliderValue"   => 50,
		"ratingValue"   => 3,
		"colorValue"    => "#6366f1",
		"passwordValue" => "",
		"timeValue"     => "14:30",
		"phoneValue"    => "",
		"dateValue"     => "",
		"otpValue"      => "",
	) ) ); ?>'
	data-app-kitchen-sink
>
<bs:bsui-stack direction="column" gap="12">
<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Accordion" />
	<bs:bsui-accordion defaultValue="a1">
		<bs:bsui-accordion-item value="a1">
			<bs:bsui-accordion-trigger title="Is it accessible?" />
			<bs:bsui-accordion-panel><bs:bsui-text variant="body" content="Yes. It adheres to the WAI-ARIA design pattern." /></bs:bsui-accordion-panel>
		</bs:bsui-accordion-item>
		<bs:bsui-accordion-item value="a2">
			<bs:bsui-accordion-trigger title="Is it styled?" />
			<bs:bsui-accordion-panel><bs:bsui-text variant="body" content="Yes. It comes with default styles that match shadcn." /></bs:bsui-accordion-panel>
		</bs:bsui-accordion-item>
		<bs:bsui-accordion-item value="a3">
			<bs:bsui-accordion-trigger title="Is it animated?" />
			<bs:bsui-accordion-panel><bs:bsui-text variant="body" content="Yes. Animations are CSS-driven for best performance." /></bs:bsui-accordion-panel>
		</bs:bsui-accordion-item>
	</bs:bsui-accordion>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Alert Dialog" />
	<bs:bsui-alert-dialog>
		<bs:bsui-alert-dialog-trigger trigger_label="Delete Account" />
		<bs:bsui-alert-dialog-backdrop />
		<bs:bsui-alert-dialog-popup>
			<bs:bsui-alert-dialog-close x="true" />
			<bs:bsui-text variant="muted" content="Are you sure? This cannot be undone." />
			<bs:bsui-alert-dialog-close label="Cancel" />
		</bs:bsui-alert-dialog-popup>
	</bs:bsui-alert-dialog>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Aspect Ratio" />
	<bs:bsui-stack direction="column" align="stretch">
		<bs:bsui-aspect-ratio ratio="16/9" background="oklch(0.92 0 0)">
			<bs:bsui-text variant="body" content="16 : 9" />
		</bs:bsui-aspect-ratio>
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Breadcrumb" />
	<bs:bsui-breadcrumb>
		<bs:bsui-breadcrumb-item label="Home" href="/" />
		<bs:bsui-breadcrumb-ellipsis>
			<bs:bsui-menu>
				<bs:bsui-menu-trigger trigger_label="···" trigger_variant="ghost" trigger_size="sm" />
				<bs:bsui-menu-popup>
					<bs:bsui-menu-item label="Documentation" />
					<bs:bsui-menu-item label="Themes" />
				</bs:bsui-menu-popup>
			</bs:bsui-menu>
		</bs:bsui-breadcrumb-ellipsis>
		<bs:bsui-breadcrumb-item label="Components" href="/components" />
		<bs:bsui-breadcrumb-page label="Breadcrumb" />
	</bs:bsui-breadcrumb>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Badge" />
	<bs:bsui-stack>
		<bs:bsui-badge label="Default" />
		<bs:bsui-badge variant="secondary" label="Secondary" />
		<bs:bsui-badge variant="outline" label="Outline" />
		<bs:bsui-badge variant="destructive" label="Destructive" />
		<bs:bsui-badge variant="success" label="Success" />
		<bs:bsui-badge variant="warning" label="Warning" />
		<bs:bsui-badge variant="info" label="Info" />
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Button" />
	<bs:bsui-stack direction="column" align="flex-start">
		<bs:bsui-stack>
			<bs:bsui-button label="Default" />
			<bs:bsui-button variant="secondary" label="Secondary" />
			<bs:bsui-button variant="outline" label="Outline" />
			<bs:bsui-button variant="ghost" label="Ghost" />
			<bs:bsui-button variant="link" label="Link" />
			<bs:bsui-button variant="destructive" label="Destructive" />
		</bs:bsui-stack>
		<bs:bsui-stack>
			<bs:bsui-button size="sm" label="Small" />
			<bs:bsui-button label="Default" />
			<bs:bsui-button size="lg" label="Large" />
		</bs:bsui-stack>
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Button Group" />
	<bs:bsui-stack direction="column" align="flex-start" gap="4">
		<bs:bsui-button-group>
			<bs:bsui-button variant="outline" label="Archive" />
			<bs:bsui-button variant="outline" label="Report" />
			<bs:bsui-button variant="outline" label="Snooze" />
		</bs:bsui-button-group>
		<bs:bsui-button-group>
			<bs:bsui-button variant="outline" size="icon" label="←" />
			<bs:bsui-button variant="outline" label="Archive" />
			<bs:bsui-button variant="outline" label="Report" />
			<bs:bsui-button variant="outline" label="Snooze" />
			<bs:bsui-menu>
				<bs:bsui-menu-trigger trigger_label="···" trigger_variant="outline" trigger_size="icon" />
				<bs:bsui-menu-popup>
					<bs:bsui-menu-item label="Move to trash" />
					<bs:bsui-menu-item label="Mark as spam" />
					<bs:bsui-menu-item label="Forward" />
				</bs:bsui-menu-popup>
			</bs:bsui-menu>
		</bs:bsui-button-group>
		<bs:bsui-button-group>
			<bs:bsui-button label="Save" />
			<bs:bsui-button label="Save as draft" />
		</bs:bsui-button-group>
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Avatar" />
	<bs:bsui-stack>
		<bs:bsui-avatar src="https://i.pravatar.cc/150?u=1" alt="User" fallback="JD" />
		<bs:bsui-avatar src="https://i.pravatar.cc/150?u=2" alt="User 2" fallback="AB" />
		<bs:bsui-avatar fallback="CD" />
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Card" />
	<bs:bsui-stack direction="column" align="stretch">
		<bs:bsui-card>
			<bs:bsui-card-header>
				<bs:bsui-card-title label="Create project" />
				<bs:bsui-card-description label="Deploy your new project in one click." />
			</bs:bsui-card-header>
			<bs:bsui-card-content>
				<bs:bsui-input placeholder="Project name" type="text" />
				<bs:bsui-select placeholder="Framework">
					<bs:bsui-select-trigger />
					<bs:bsui-select-popup>
						<bs:bsui-select-option value="next" label="Next.js" />
						<bs:bsui-select-option value="remix" label="Remix" />
						<bs:bsui-select-option value="astro" label="Astro" />
					</bs:bsui-select-popup>
				</bs:bsui-select>
			</bs:bsui-card-content>
			<bs:bsui-card-footer>
				<bs:bsui-button variant="outline" label="Cancel" />
				<bs:bsui-button label="Deploy" />
			</bs:bsui-card-footer>
		</bs:bsui-card>
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Calendar" />
	<bs:bsui-calendar />
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Carousel" />
	<bs:bsui-carousel>
		<bs:bsui-carousel-item><bs:bsui-text variant="body" content="Slide 1" /></bs:bsui-carousel-item>
		<bs:bsui-carousel-item><bs:bsui-text variant="body" content="Slide 2" /></bs:bsui-carousel-item>
		<bs:bsui-carousel-item><bs:bsui-text variant="body" content="Slide 3" /></bs:bsui-carousel-item>
		<bs:bsui-carousel-item><bs:bsui-text variant="body" content="Slide 4" /></bs:bsui-carousel-item>
		<bs:bsui-carousel-item><bs:bsui-text variant="body" content="Slide 5" /></bs:bsui-carousel-item>
	</bs:bsui-carousel>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Combobox" />
	<bs:bsui-combobox placeholder="Search frameworks...">
		<bs:bsui-combobox-input />
		<bs:bsui-combobox-popup>
			<bs:bsui-combobox-option value="next" label="Next.js" />
			<bs:bsui-combobox-option value="remix" label="Remix" />
			<bs:bsui-combobox-option value="astro" label="Astro" />
			<bs:bsui-combobox-option value="nuxt" label="Nuxt" />
			<bs:bsui-combobox-option value="svelte" label="SvelteKit" />
		</bs:bsui-combobox-popup>
	</bs:bsui-combobox>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Checkbox" />
	<bs:bsui-stack direction="column" gap="3" align="flex-start">
		<bs:bsui-checkbox label="Accept terms and conditions" checked="context.checkboxOn" onChange="app/kitchen-sink::actions.setCheckbox" defaultChecked="true" />
		<bs:bsui-checkbox defaultChecked="true" label="Send me emails" />

	<div id="ks-checkbox" data-wp-text="context.checkboxOn" data-ks-value></div>
</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Collapsible" />
	<bs:bsui-collapsible>
		<bs:bsui-collapsible-trigger trigger_label="Toggle Content" />
		<bs:bsui-collapsible-panel><bs:bsui-text variant="body" content="Collapsible panel content." /></bs:bsui-collapsible-panel>
	</bs:bsui-collapsible>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Dialog" />
	<bs:bsui-dialog>
		<bs:bsui-dialog-trigger trigger_label="Open Dialog" />
		<bs:bsui-dialog-backdrop />
		<bs:bsui-dialog-popup>
			<bs:bsui-dialog-close x="true" />
			<bs:bsui-text variant="title" tag="h3" content="Are you sure?" />
			<bs:bsui-text variant="muted" content="This action cannot be undone." />
			<bs:bsui-dialog dismissable="true">
				<bs:bsui-dialog-trigger trigger_label="Open Nested" />
				<bs:bsui-dialog-backdrop />
				<bs:bsui-dialog-popup>
					<bs:bsui-text variant="title" tag="h3" content="Nested Dialog" />
					<bs:bsui-text variant="muted" content="This is a second level dialog." />
					<bs:bsui-dialog dismissable="true">
						<bs:bsui-dialog-trigger trigger_label="Go Deeper" />
						<bs:bsui-dialog-backdrop />
						<bs:bsui-dialog-popup>
							<bs:bsui-text variant="title" tag="h3" content="Third Level" />
							<bs:bsui-text variant="muted" content="Three dialogs deep." />
							<bs:bsui-dialog-close label="Close" />
						</bs:bsui-dialog-popup>
					</bs:bsui-dialog>
					<bs:bsui-dialog-close label="Close" />
				</bs:bsui-dialog-popup>
			</bs:bsui-dialog>
			<bs:bsui-dialog-close label="Close" />
		</bs:bsui-dialog-popup>
	</bs:bsui-dialog>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Drawer" />
	<bs:bsui-drawer position="left">
		<bs:bsui-drawer-trigger trigger_label="Open Drawer" />
		<bs:bsui-drawer-backdrop />
		<bs:bsui-drawer-popup>
			<bs:bsui-drawer-close x="true" />
			<bs:bsui-text variant="muted" content="Drawer content here." />
			<bs:bsui-drawer-close label="Close" />
		</bs:bsui-drawer-popup>
	</bs:bsui-drawer>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Field Group" />
	<bs:bsui-field-group>
		<bs:bsui-field-group-title text="Billing Details" />
		<bs:bsui-field-group-description text="Enter your billing information below." />
		<bs:bsui-field>
			<bs:bsui-field-label text="Company" />
			<bs:bsui-input type="text" placeholder="Enter company name" />
		</bs:bsui-field>
		<bs:bsui-field>
			<bs:bsui-field-label text="Tax ID" />
			<bs:bsui-input type="text" placeholder="Enter fiscal number" />
		</bs:bsui-field>
	</bs:bsui-field-group>
	<bs:bsui-field-group variant="card">
		<bs:bsui-field-group-title text="Account Settings" />
		<bs:bsui-field-group-description text="Manage your account preferences." />
		<bs:bsui-field>
			<bs:bsui-field-label text="Display Name" />
			<bs:bsui-input type="text" placeholder="Enter display name" />
		</bs:bsui-field>
		<bs:bsui-field>
			<bs:bsui-field-label text="Email" />
			<bs:bsui-input type="email" placeholder="Enter email" />
		</bs:bsui-field>
	</bs:bsui-field-group>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Form" />
	<bs:bsui-stack direction="column" align="stretch">
		<bs:bsui-form block="test/form" successMessage="Thank you!">
			<bs:bsui-stack direction="column" gap="4">
				<bs:bsui-field>
					<bs:bsui-field-label text="Username" />
					<bs:bsui-input nameAlt="username" type="text" placeholder="Enter username" />
					<bs:bsui-field-error />
				</bs:bsui-field>
				<bs:bsui-field>
					<bs:bsui-field-label text="Email" />
					<bs:bsui-input nameAlt="email" type="email" placeholder="Enter email" />
					<bs:bsui-field-error />
				</bs:bsui-field>
				<bs:bsui-field>
					<bs:bsui-field-label text="Message" />
					<bs:bsui-textarea nameAlt="message" placeholder="Type your message..." rows="3" />
					<bs:bsui-field-error />
				</bs:bsui-field>
				<bs:bsui-button label="Submit" type="submit" />
			</bs:bsui-stack>
		</bs:bsui-form>
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Field (with errors)" />
	<bs:bsui-stack direction="column" align="stretch">
		<bs:bsui-stack direction="column" gap="4">
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Email" />
				<bs:bsui-input type="email" placeholder="Enter email" defaultValue="not-an-email" />
				<bs:bsui-field-error text="Must be a valid email address." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Password" />
				<bs:bsui-input type="password" placeholder="Enter password" />
				<bs:bsui-field-error text="Must be at least 8 characters." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Textarea" />
				<bs:bsui-textarea placeholder="Write something..." rows="3" />
				<bs:bsui-field-error text="Message is required." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Select" />
				<bs:bsui-select placeholder="Select a fruit">
					<bs:bsui-select-trigger />
					<bs:bsui-select-popup>
						<bs:bsui-select-option value="apple" label="Apple" />
						<bs:bsui-select-option value="banana" label="Banana" />
					</bs:bsui-select-popup>
				</bs:bsui-select>
				<bs:bsui-field-error text="Please select an option." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-checkbox label="Accept terms and conditions" />
				<bs:bsui-field-error text="You must accept the terms." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-switch label="Enable notifications" />
				<bs:bsui-field-error text="Notifications are required." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Plan" />
				<bs:bsui-radio-group defaultValue="free">
					<bs:bsui-radio value="free" label="Free" />
					<bs:bsui-radio value="pro" label="Pro" />
					<bs:bsui-radio value="enterprise" label="Enterprise" />
				</bs:bsui-radio-group>
				<bs:bsui-field-error text="Please select a plan." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Quantity" />
				<bs:bsui-number-field defaultValue="0" min="1" max="100" />
				<bs:bsui-field-error text="Must be at least 1." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Color" />
				<bs:bsui-color-picker nameAlt="color" defaultValue="#6366f1" value="context.colorValue" onChange="app/kitchen-sink::actions.setColor" />
				<bs:bsui-field-error text="Please select a valid color." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Date" />
				<bs:bsui-date-input nameAlt="date" placeholder="Pick a date" />
				<bs:bsui-field-error text="Date is required." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="File" />
				<bs:bsui-file-upload nameAlt="file" accept="image/*" />
				<bs:bsui-field-error text="Please upload a file." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Verification Code" />
				<bs:bsui-otp-input nameAlt="otp" length="6" />
				<bs:bsui-field-error text="Enter a valid verification code." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Password" />
				<bs:bsui-password-input nameAlt="pw" placeholder="Enter password" />
				<bs:bsui-field-error text="Password must be at least 8 characters." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Phone" />
				<bs:bsui-phone-input nameAlt="phone" defaultCountry="US" onChange="app/kitchen-sink::actions.setPhone" />
				<bs:bsui-field-error text="Enter a valid phone number." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Rating" />
				<bs:bsui-rating nameAlt="rating" max="5" defaultValue="0" />
				<bs:bsui-field-error text="Please provide a rating." />
			</bs:bsui-field>
			<bs:bsui-field invalid="true">
				<bs:bsui-field-label text="Time" />
				<bs:bsui-time-picker nameAlt="time" defaultValue="14:30" step="15" value="context.timeValue" onChange="app/kitchen-sink::actions.setTime" />
				<bs:bsui-field-error text="Please select a time." />
			</bs:bsui-field>
			<bs:bsui-field disabled="true">
				<bs:bsui-field-label text="Disabled field" />
				<bs:bsui-input type="text" placeholder="Cannot edit" disabled="true" />
			</bs:bsui-field>
		</bs:bsui-stack>
	</bs:bsui-stack>
</bs:bsui-stack>


<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Color Picker" />
	<bs:bsui-color-picker nameAlt="color" defaultValue="#6366f1" onChange="app/kitchen-sink::actions.setColor" />

	<div id="ks-color" data-wp-text="context.colorValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Date Input" />
	<bs:bsui-date-input nameAlt="date" placeholder="Pick a date" onChange="app/kitchen-sink::actions.setDate" />

	<div id="ks-date" data-wp-text="context.dateValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="File Upload" />
	<bs:bsui-file-upload nameAlt="file" accept="image/*" />
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Input" />
	<bs:bsui-input placeholder="Enter your email" type="email" value="context.inputValue" onChange="app/kitchen-sink::actions.setInput" />

	<div id="ks-input" data-wp-text="context.inputValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Kbd" />
	<bs:bsui-stack gap="1">
		<bs:bsui-text variant="muted" tag="span" content="Press" />
		<bs:bsui-kbd label="⌘" />
		<bs:bsui-kbd label="K" />
		<bs:bsui-text variant="muted" tag="span" content="to search" />
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Menu" />
	<bs:bsui-menu>
		<bs:bsui-menu-trigger trigger_label="Open Menu" />
		<bs:bsui-menu-popup>
			<bs:bsui-menu-item label="Profile" />
			<bs:bsui-menu-item label="Settings" />
			<bs:bsui-menu-item label="Logout" />
		</bs:bsui-menu-popup>
	</bs:bsui-menu>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Navigation Menu" />
	<bs:bsui-navigation-menu>
		<bs:bsui-navigation-menu-item>
			<bs:bsui-navigation-menu-trigger label="Getting Started" />
			<bs:bsui-navigation-menu-content>
				<bs:bsui-text variant="muted" content="Build modern web apps with composable UI blocks." />
			</bs:bsui-navigation-menu-content>
		</bs:bsui-navigation-menu-item>
		<bs:bsui-navigation-menu-item>
			<bs:bsui-navigation-menu-trigger label="Components" />
			<bs:bsui-navigation-menu-content>
				<bs:bsui-text variant="muted" content="Browse all available UI components." />
			</bs:bsui-navigation-menu-content>
		</bs:bsui-navigation-menu-item>
		<bs:bsui-navigation-menu-link label="Documentation" href="#" />
	</bs:bsui-navigation-menu>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Menubar" />
	<bs:bsui-menubar>
		<bs:bsui-menubar-menu>
			<bs:bsui-menubar-trigger label="File" />
			<bs:bsui-menubar-popup>
				<bs:bsui-menubar-item label="New Tab" />
				<bs:bsui-menubar-item label="New Window" />
				<bs:bsui-menubar-item label="Save" />
				<bs:bsui-menubar-submenu>
					<bs:bsui-menubar-submenu-trigger label="Export" />
					<bs:bsui-menubar-submenu-popup>
						<bs:bsui-menubar-item label="PDF" />
						<bs:bsui-menubar-item label="PNG" />
						<bs:bsui-menubar-item label="SVG" />
					</bs:bsui-menubar-submenu-popup>
				</bs:bsui-menubar-submenu>
				<bs:bsui-menubar-item label="Print" />
			</bs:bsui-menubar-popup>
		</bs:bsui-menubar-menu>
		<bs:bsui-menubar-menu>
			<bs:bsui-menubar-trigger label="Edit" />
			<bs:bsui-menubar-popup>
				<bs:bsui-menubar-item label="Undo" />
				<bs:bsui-menubar-item label="Redo" />
				<bs:bsui-menubar-item label="Cut" />
			</bs:bsui-menubar-popup>
		</bs:bsui-menubar-menu>
		<bs:bsui-menubar-menu>
			<bs:bsui-menubar-trigger label="View" />
			<bs:bsui-menubar-popup>
				<bs:bsui-menubar-item label="Zoom In" />
				<bs:bsui-menubar-item label="Zoom Out" />
				<bs:bsui-menubar-item label="Full Screen" />
			</bs:bsui-menubar-popup>
		</bs:bsui-menubar-menu>
	</bs:bsui-menubar>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Number Field" />
	<bs:bsui-number-field defaultValue="5" min="0" max="10" step="1" value="context.numberValue" onChange="app/kitchen-sink::actions.setNumber" />

	<div id="ks-number" data-wp-text="context.numberValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="OTP Input" />
	<bs:bsui-otp-input nameAlt="otp" length="6" onChange="app/kitchen-sink::actions.setOtp" />

	<div id="ks-otp" data-wp-text="context.otpValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Password Input" />
	<bs:bsui-password-input nameAlt="password" placeholder="Enter password" value="context.passwordValue" onChange="app/kitchen-sink::actions.setPassword" />

	<div id="ks-password" data-wp-text="context.passwordValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Phone Input" />
	<bs:bsui-phone-input nameAlt="phone" defaultCountry="US" onChange="app/kitchen-sink::actions.setPhone" />

	<div id="ks-phone" data-wp-text="context.phoneValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Popover" />
	<bs:bsui-popover>
		<bs:bsui-popover-trigger trigger_label="Open Popover" />
		<bs:bsui-popover-popup>
			<bs:bsui-text variant="muted" content="Popover content here." />
			<bs:bsui-popover-close label="Close" />
		</bs:bsui-popover-popup>
	</bs:bsui-popover>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Pagination" />
	<bs:bsui-pagination>
		<bs:bsui-pagination-previous href="#" />
		<bs:bsui-pagination-item label="1" href="#" />
		<bs:bsui-pagination-item label="2" href="#" current="true" />
		<bs:bsui-pagination-item label="3" href="#" />
		<bs:bsui-pagination-ellipsis />
		<bs:bsui-pagination-item label="10" href="#" />
		<bs:bsui-pagination-next href="#" />
	</bs:bsui-pagination>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Progress" />
	<bs:bsui-progress value="65" max="100" label="Upload progress" />
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Rating" />
	<bs:bsui-rating nameAlt="rating" max="5" defaultValue="3" value="context.ratingValue" onChange="app/kitchen-sink::actions.setRating" />

	<div id="ks-rating" data-wp-text="context.ratingValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Radio" />
	<bs:bsui-radio-group defaultValue="comfortable" value="context.radioValue" onChange="app/kitchen-sink::actions.setRadio">
		<bs:bsui-radio value="default" label="Default" />
		<bs:bsui-radio value="comfortable" label="Comfortable" />
		<bs:bsui-radio value="compact" label="Compact" />
	</bs:bsui-radio-group>
	<div id="ks-radio" data-wp-text="context.radioValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Select" />
	<bs:bsui-select placeholder="Select a fruit" onChange="app/kitchen-sink::actions.setSelect">
		<bs:bsui-select-trigger />
		<bs:bsui-select-popup>
			<bs:bsui-select-option value="apple" label="Apple" />
			<bs:bsui-select-option value="banana" label="Banana" />
			<bs:bsui-select-option value="cherry" label="Cherry" />
			<bs:bsui-select-option value="grape" label="Grape" />
		</bs:bsui-select-popup>
	</bs:bsui-select>
	<div id="ks-select" data-wp-text="context.selectValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Separator" />
	<bs:bsui-separator />
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Skeleton" />
	<bs:bsui-stack gap="3">
		<bs:bsui-skeleton width="2.5rem" height="2.5rem" rounded="true" />
		<bs:bsui-stack direction="column" gap="1.5" align="flex-start">
			<bs:bsui-skeleton width="12rem" height="0.875rem" />
			<bs:bsui-skeleton width="8rem" height="0.875rem" />
		</bs:bsui-stack>
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Slider" />
	<bs:bsui-slider defaultValue="50" min="0" max="100" step="1" value="context.sliderValue" onChange="app/kitchen-sink::actions.setSlider" />

	<div id="ks-slider" data-wp-text="context.sliderValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Spinner" />
	<bs:bsui-stack gap="4">
		<bs:bsui-spinner size="sm" />
		<bs:bsui-spinner />
		<bs:bsui-spinner size="lg" />
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Switch" />
	<bs:bsui-stack direction="column" gap="3" align="flex-start">
		<bs:bsui-switch label="Airplane Mode" checked="context.switchOn" onChange="app/kitchen-sink::actions.setSwitch" />
		<bs:bsui-switch defaultChecked="true" label="Notifications" />

	<div id="ks-switch" data-wp-text="context.switchOn" data-ks-value></div>
</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Table" />
	<bs:bsui-table sortable="true">
		<bs:bsui-table-header>
			<bs:bsui-table-header-cell columnId="name" label="Name" />
			<bs:bsui-table-header-cell columnId="email" label="Email" />
			<bs:bsui-table-header-cell columnId="role" label="Role" />
		</bs:bsui-table-header>
		<bs:bsui-table-body>
			<bs:bsui-table-row>
				<bs:bsui-table-cell><bs:bsui-text variant="body" content="Alice" /></bs:bsui-table-cell>
				<bs:bsui-table-cell><bs:bsui-text variant="body" content="alice@example.com" /></bs:bsui-table-cell>
				<bs:bsui-table-cell><bs:bsui-text variant="body" content="Admin" /></bs:bsui-table-cell>
			</bs:bsui-table-row>
			<bs:bsui-table-row>
				<bs:bsui-table-cell><bs:bsui-text variant="body" content="Bob" /></bs:bsui-table-cell>
				<bs:bsui-table-cell><bs:bsui-text variant="body" content="bob@example.com" /></bs:bsui-table-cell>
				<bs:bsui-table-cell><bs:bsui-text variant="body" content="Editor" /></bs:bsui-table-cell>
			</bs:bsui-table-row>
		</bs:bsui-table-body>
	</bs:bsui-table>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Text" />
	<bs:bsui-stack direction="column" gap="3">
		<bs:bsui-text variant="heading" tag="h3" content="Heading" />
		<bs:bsui-text variant="title" tag="h4" content="Title" />
		<bs:bsui-text variant="lead" content="Lead text for introductions and summaries." />
		<bs:bsui-text variant="body" content="Body text for general content and descriptions." />
		<bs:bsui-text variant="label" tag="label" content="Label" />
		<bs:bsui-text variant="muted" content="Muted text for secondary information." />
		<bs:bsui-text variant="caption" content="Caption text for annotations." />
		<bs:bsui-text variant="code" tag="span" content="code --inline" />
	</bs:bsui-stack>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Textarea" />
	<bs:bsui-textarea placeholder="Type your message here." rows="4" value="context.textareaValue" onChange="app/kitchen-sink::actions.setTextarea" />

	<div id="ks-textarea" data-wp-text="context.textareaValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Time Picker" />
	<bs:bsui-time-picker nameAlt="time" defaultValue="14:30" step="15" value="context.timeValue" onChange="app/kitchen-sink::actions.setTime" />

	<div id="ks-time" data-wp-text="context.timeValue" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Tabs" />
	<bs:bsui-tabs defaultValue="tab1">
		<bs:bsui-tabs-list>
			<bs:bsui-tabs-trigger value="tab1" title="Account" />
			<bs:bsui-tabs-trigger value="tab2" title="Password" />
			<bs:bsui-tabs-trigger value="tab3" title="Settings" />
		</bs:bsui-tabs-list>
		<bs:bsui-tabs-panel value="tab1"><bs:bsui-text variant="body" content="Make changes to your account here." /></bs:bsui-tabs-panel>
		<bs:bsui-tabs-panel value="tab2"><bs:bsui-text variant="body" content="Change your password here." /></bs:bsui-tabs-panel>
		<bs:bsui-tabs-panel value="tab3"><bs:bsui-text variant="body" content="Manage your settings here." /></bs:bsui-tabs-panel>
	</bs:bsui-tabs>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Toggle" />
	<bs:bsui-stack gap="4">
		<bs:bsui-toggle label="Bold" checked="context.toggleOn" onChange="app/kitchen-sink::actions.setToggle" />
		<bs:bsui-toggle defaultPressed="true" label="Italic" />
	</bs:bsui-stack>
	<div id="ks-toggle" data-wp-text="context.toggleOn" data-ks-value></div>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Toggle Group" />
	<bs:bsui-toggle-group defaultValue="center">
		<bs:bsui-toggle-group-item value="left" label="Left" />
		<bs:bsui-toggle-group-item value="center" label="Center" />
		<bs:bsui-toggle-group-item value="right" label="Right" />
	</bs:bsui-toggle-group>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Toolbar" />
	<bs:bsui-toolbar>
		<bs:bsui-toggle-group defaultValue="left">
			<bs:bsui-toggle-group-item value="left" label="Align Left" />
			<bs:bsui-toggle-group-item value="right" label="Align Right" />
		</bs:bsui-toggle-group>
		<bs:bsui-toolbar-separator />
		<bs:bsui-toolbar-button label="$" />
		<bs:bsui-toolbar-button label="%" />
		<bs:bsui-toolbar-separator />
		<bs:bsui-select placeholder="Font" defaultValue="helvetica">
			<bs:bsui-select-trigger />
			<bs:bsui-select-popup>
				<bs:bsui-select-option value="helvetica" label="Helvetica" />
				<bs:bsui-select-option value="arial" label="Arial" />
				<bs:bsui-select-option value="georgia" label="Georgia" />
			</bs:bsui-select-popup>
		</bs:bsui-select>
		<bs:bsui-toolbar-separator />
		<bs:bsui-toolbar-button label="B" />
		<bs:bsui-toolbar-button label="I" />
		<bs:bsui-toolbar-button label="U" />
	</bs:bsui-toolbar>
</bs:bsui-stack>

<bs:bsui-stack direction="column" gap="3">
	<bs:bsui-text variant="heading" tag="h2" content="Tooltip" />
	<bs:bsui-tooltip openDelay="0">
		<bs:bsui-tooltip-trigger trigger_label="Hover me" />
		<bs:bsui-tooltip-popup content="This is a tooltip" />
	</bs:bsui-tooltip>
	<bs:bsui-tooltip openDelay="0">
		<bs:bsui-tooltip-trigger trigger_block="bsui/badge" trigger_label="Badge trigger" />
		<bs:bsui-tooltip-popup content="Tooltip on a badge!" />
	</bs:bsui-tooltip>
</bs:bsui-stack>
</bs:bsui-stack>
</div>
