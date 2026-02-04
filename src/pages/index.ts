import { addFilter } from '@wordpress/hooks';
import { subscribe, select, dispatch } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';

interface Block {
	clientId: string;
	name: string;
	attributes: Record<string, unknown>;
	innerBlocks: Block[];
}

interface BlockSettings {
	attributes: Record<string, { type: string }>;
}

interface EditorSettings {
	blockstudioBlockEditingMode?: string;
}

// Register blockEditingMode as an attribute on every block type via JS filter.
// This must happen before blocks are parsed so the attribute is preserved.
addFilter(
	'blocks.registerBlockType',
	'blockstudio/block-editing-mode-attribute',
	(settings: BlockSettings) => {
		settings.attributes = {
			...settings.attributes,
			blockEditingMode: { type: 'string' },
		};
		return settings;
	}
);

function findAncestorsOfOverrides(
	blocks: Block[],
	parentIds: string[]
): Set<string> {
	const ancestors = new Set<string>();

	for (const block of blocks) {
		const currentPath = [...parentIds, block.clientId];

		if (block.attributes.blockEditingMode) {
			// Mark all parents (not the block itself) as ancestors.
			for (const id of parentIds) {
				ancestors.add(id);
			}
		}

		if (block.innerBlocks && block.innerBlocks.length) {
			const childAncestors = findAncestorsOfOverrides(
				block.innerBlocks,
				currentPath
			);
			childAncestors.forEach((id) => ancestors.add(id));
		}
	}

	return ancestors;
}

function getAllBlocks(blocks: Block[]): Block[] {
	const result: Block[] = [];

	blocks.forEach((block) => {
		result.push(block);

		if (block.innerBlocks && block.innerBlocks.length) {
			result.push(...getAllBlocks(block.innerBlocks));
		}
	});

	return result;
}

domReady(() => {
	const applied: Record<string, string> = {};

	subscribe(() => {
		const settings = select('core/editor').getEditorSettings() as EditorSettings;
		const defaultMode = settings.blockstudioBlockEditingMode;

		if (!defaultMode) {
			return;
		}

		const topBlocks = (
			select('core/block-editor') as { getBlocks: () => Block[] }
		).getBlocks();
		const ancestorIds = findAncestorsOfOverrides(topBlocks, []);
		const blocks = getAllBlocks(topBlocks);

		blocks.forEach((block) => {
			let mode = (block.attributes.blockEditingMode as string) || defaultMode;

			// Only ancestor containers of overridden blocks get "contentOnly"
			// so their descendants remain interactive. All other containers
			// keep the page default.
			if (!block.attributes.blockEditingMode && ancestorIds.has(block.clientId)) {
				mode = 'contentOnly';
			}

			if (mode && applied[block.clientId] !== mode) {
				(
					dispatch('core/block-editor') as unknown as {
						setBlockEditingMode: (clientId: string, mode: string) => void;
					}
				).setBlockEditingMode(block.clientId, mode);
				applied[block.clientId] = mode;
			}
		});
	});
});
