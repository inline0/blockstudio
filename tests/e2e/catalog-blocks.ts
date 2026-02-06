import { test } from '@playwright/test';
import { login, getEditorCanvas } from './utils/playwright-utils';
import * as fs from 'fs';
import * as path from 'path';

interface BlockExample {
	attrs?: Record<string, unknown>;
	innerBlocks?: Array<{
		name: string;
		attrs?: Record<string, unknown>;
		innerBlocks?: Array<{
			name: string;
			attrs?: Record<string, unknown>;
		}>;
	}>;
}

const examples: Record<string, BlockExample> = {
	'core/paragraph': {
		attrs: { content: 'Hello <strong>world</strong>' },
	},
	'core/heading': {
		attrs: { content: 'Test Heading', level: 2 },
	},
	'core/image': {
		attrs: {
			url: 'https://example.com/photo.jpg',
			alt: 'A photo',
			caption: 'My caption',
			sizeSlug: 'large',
		},
	},
	'core/gallery': {
		innerBlocks: [
			{
				name: 'core/image',
				attrs: { url: 'https://example.com/1.jpg', alt: 'Image 1' },
			},
			{
				name: 'core/image',
				attrs: { url: 'https://example.com/2.jpg', alt: 'Image 2' },
			},
		],
	},
	'core/audio': {
		attrs: { src: 'https://example.com/audio.mp3' },
	},
	'core/video': {
		attrs: { src: 'https://example.com/video.mp4', caption: 'A video' },
	},
	'core/cover': {
		attrs: {
			url: 'https://example.com/bg.jpg',
			dimRatio: 50,
			overlayColor: 'black',
		},
		innerBlocks: [
			{
				name: 'core/paragraph',
				attrs: { content: 'Cover text', align: 'center' },
			},
		],
	},
	'core/list': {
		innerBlocks: [
			{ name: 'core/list-item', attrs: { content: 'Item one' } },
			{ name: 'core/list-item', attrs: { content: 'Item two' } },
		],
	},
	'core/quote': {
		attrs: { citation: 'Author Name' },
		innerBlocks: [
			{ name: 'core/paragraph', attrs: { content: 'A wise quote.' } },
		],
	},
	'core/pullquote': {
		attrs: { citation: 'Citation Text', value: 'Pullquote content' },
	},
	'core/code': {
		attrs: { content: 'const x = 42;' },
	},
	'core/preformatted': {
		attrs: { content: 'Preformatted text here' },
	},
	'core/verse': {
		attrs: { content: 'Roses are red\nViolets are blue' },
	},
	'core/group': {
		innerBlocks: [
			{ name: 'core/paragraph', attrs: { content: 'Inside group' } },
		],
	},
	'core/columns': {
		innerBlocks: [
			{
				name: 'core/column',
				innerBlocks: [
					{
						name: 'core/paragraph',
						attrs: { content: 'Column 1' },
					},
				],
			},
			{
				name: 'core/column',
				innerBlocks: [
					{
						name: 'core/paragraph',
						attrs: { content: 'Column 2' },
					},
				],
			},
		],
	},
	'core/buttons': {
		innerBlocks: [
			{
				name: 'core/button',
				attrs: { text: 'Click me', url: 'https://example.com' },
			},
		],
	},
	'core/details': {
		attrs: { summary: 'Click to expand' },
		innerBlocks: [
			{
				name: 'core/paragraph',
				attrs: { content: 'Hidden content here' },
			},
		],
	},
	'core/table': {
		attrs: {
			hasFixedLayout: false,
			head: [{ cells: [{ content: 'Header 1' }, { content: 'Header 2' }] }],
			body: [{ cells: [{ content: 'Cell A' }, { content: 'Cell B' }] }],
		},
	},
	'core/separator': {},
	'core/spacer': {
		attrs: { height: '50px' },
	},
	'core/embed': {
		attrs: {
			url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			providerNameSlug: 'youtube',
			type: 'video',
			responsive: true,
		},
	},
	'core/social-links': {
		innerBlocks: [
			{
				name: 'core/social-link',
				attrs: { service: 'facebook', url: 'https://facebook.com' },
			},
			{
				name: 'core/social-link',
				attrs: { service: 'twitter', url: 'https://twitter.com' },
			},
			{
				name: 'core/social-link',
				attrs: {
					service: 'instagram',
					url: 'https://instagram.com',
				},
			},
		],
	},
	'core/media-text': {
		attrs: { mediaUrl: 'https://example.com/photo.jpg', mediaType: 'image' },
		innerBlocks: [
			{
				name: 'core/paragraph',
				attrs: { content: 'Text next to media' },
			},
		],
	},
	'core/file': {
		attrs: {
			href: 'https://example.com/doc.pdf',
			fileName: 'document.pdf',
		},
	},
	'core/html': {
		attrs: { content: '<div class="custom">Custom HTML</div>' },
	},
	'core/more': {},
	'core/nextpage': {},
	'core/freeform': {
		attrs: { content: '<p>Classic editor content</p>' },
	},
};

test('catalog all core block save markup', async ({ browser }) => {
	const context = await browser.newContext();
	const page = await context.newPage();
	await login(page);

	await page.goto('http://localhost:8888/wp-admin/post-new.php');
	await getEditorCanvas(page);

	const closeBtn = page.locator(
		'.components-modal__screen-overlay button[aria-label="Close"]',
	);
	if (await closeBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
		await closeBtn.click();
	}

	const catalog = await page.evaluate((examplesParam) => {
		const wp = (window as any).wp;
		const blockTypes = wp.blocks.getBlockTypes();
		const results: Record<string, any> = {};

		function buildInnerBlocks(
			defs: Array<{
				name: string;
				attrs?: Record<string, unknown>;
				innerBlocks?: Array<{
					name: string;
					attrs?: Record<string, unknown>;
				}>;
			}>,
		): any[] {
			return defs.map((def: any) => {
				const children = def.innerBlocks
					? buildInnerBlocks(def.innerBlocks)
					: [];
				return wp.blocks.createBlock(def.name, def.attrs || {}, children);
			});
		}

		function serializeSafe(block: any): string {
			try {
				return wp.blocks.serialize(block);
			} catch {
				return '';
			}
		}

		function getSaveContentSafe(
			name: string,
			attrs: any,
			innerBlocks: any[] = [],
		): string {
			try {
				return wp.blocks.getSaveContent(name, attrs, innerBlocks);
			} catch {
				return '';
			}
		}

		for (const type of blockTypes) {
			if (!type.name.startsWith('core/')) continue;

			const entry: any = {
				title: type.title,
				category: type.category || null,
				parent: type.parent || null,
				supports: type.supports || {},
				attributes: type.attributes || {},
			};

			try {
				const defaultBlock = wp.blocks.createBlock(type.name);
				const defaultSerialized = serializeSafe(defaultBlock);
				const defaultSaveContent = getSaveContentSafe(
					type.name,
					defaultBlock.attributes,
				);

				entry.default = {
					serialized: defaultSerialized,
					saveContent: defaultSaveContent,
					attributes: defaultBlock.attributes,
				};

				entry.hasSaveMarkup =
					defaultSaveContent !== '' && defaultSaveContent !== null;

				const example = examplesParam[type.name];
				if (example) {
					const innerBlocks = example.innerBlocks
						? buildInnerBlocks(example.innerBlocks)
						: [];
					const block = wp.blocks.createBlock(
						type.name,
						example.attrs || {},
						innerBlocks,
					);
					const serialized = serializeSafe(block);

					entry.example = {
						serialized,
						inputAttrs: example.attrs || {},
						resolvedAttributes: block.attributes,
					};
				}
			} catch (e: any) {
				entry.error = e.message;
			}

			results[type.name] = entry;
		}

		return results;
	}, examples);

	const blockNames = Object.keys(catalog).sort();
	const withSave = blockNames.filter((n) => catalog[n].hasSaveMarkup);
	const dynamic = blockNames.filter((n) => !catalog[n].hasSaveMarkup);
	const withErrors = blockNames.filter((n) => catalog[n].error);

	const outputPath = path.resolve(
		import.meta.dirname,
		'../../block-catalog.json',
	);
	fs.writeFileSync(outputPath, JSON.stringify(catalog, null, '\t'));

	const summary = [
		'',
		'=== CORE BLOCK CATALOG ===',
		'',
		`Total core blocks: ${blockNames.length}`,
		`With save markup (static): ${withSave.length}`,
		`Dynamic (server-rendered): ${dynamic.length}`,
		`Errors: ${withErrors.length}`,
		'',
		'--- STATIC BLOCKS (have save markup) ---',
		'',
		...withSave.map((name) => {
			const b = catalog[name];
			const serialized = b.example?.serialized || b.default?.serialized || '';
			const lines = serialized.split('\n');
			const preview =
				lines.length > 3
					? lines.slice(0, 3).join('\n') + '\n  ...'
					: serialized;
			return `  ${name} (${b.title})\n${preview}\n`;
		}),
		'--- DYNAMIC BLOCKS (no save markup) ---',
		'',
		...dynamic.map((name) => `  ${name} (${catalog[name].title})`),
	];

	if (withErrors.length > 0) {
		summary.push('', '--- ERRORS ---', '');
		for (const name of withErrors) {
			summary.push(`  ${name}: ${catalog[name].error}`);
		}
	}

	console.log(summary.join('\n'));
	console.log(`\nFull catalog written to: ${outputPath}`);

	await page.close();
});
