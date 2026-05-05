<block name="bsui/table" sortable="true">
	<block name="bsui/table-header">
		<block name="bsui/table-header-cell" columnId="name" label="Name"></block>
		<block name="bsui/table-header-cell" columnId="email" label="Email"></block>
		<block name="bsui/table-header-cell" columnId="role" label="Role"></block>
		<block name="bsui/table-header-cell" columnId="actions" label="Actions" sortable="false"></block>
	</block>
	<block name="bsui/table-body">
		<block name="bsui/table-row">
			<block name="bsui/table-cell"><p>Alice Johnson</p></block>
			<block name="bsui/table-cell"><p>alice@example.com</p></block>
			<block name="bsui/table-cell"><p>Admin</p></block>
			<block name="bsui/table-cell">
				<block name="bsui/menu">
					<block name="bsui/menu-trigger" label="..."></block>
					<block name="bsui/menu-popup">
						<block name="bsui/menu-item" label="Edit"></block>
						<block name="bsui/menu-item" label="Delete"></block>
					</block>
				</block>
			</block>
		</block>
		<block name="bsui/table-row">
			<block name="bsui/table-cell"><p>Bob Smith</p></block>
			<block name="bsui/table-cell"><p>bob@example.com</p></block>
			<block name="bsui/table-cell"><p>Editor</p></block>
			<block name="bsui/table-cell">
				<block name="bsui/menu">
					<block name="bsui/menu-trigger" label="..."></block>
					<block name="bsui/menu-popup">
						<block name="bsui/menu-item" label="Edit"></block>
						<block name="bsui/menu-item" label="Delete"></block>
					</block>
				</block>
			</block>
		</block>
		<block name="bsui/table-row">
			<block name="bsui/table-cell"><p>Charlie Brown</p></block>
			<block name="bsui/table-cell"><p>charlie@example.com</p></block>
			<block name="bsui/table-cell"><p>Viewer</p></block>
			<block name="bsui/table-cell">
				<block name="bsui/menu">
					<block name="bsui/menu-trigger" label="..."></block>
					<block name="bsui/menu-popup">
						<block name="bsui/menu-item" label="Edit"></block>
						<block name="bsui/menu-item" label="Delete"></block>
					</block>
				</block>
			</block>
		</block>
	</block>
</block>
