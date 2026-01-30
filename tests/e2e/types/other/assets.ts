import { Page, expect } from '@playwright/test';
import { count, testType } from '../../utils/playwright-utils';

const atLeast = async (page: Page, selector: string, min: number) => {
	const cnt = await page.locator(selector).count();
	expect(cnt).toBeGreaterThanOrEqual(min);
};

const assetIds = [
	'blockstudio-blocks-js-extra',
	'blockstudio-blocks-js',
	'blockstudio-blockstudio-native-script-inline-js',
	'blockstudio-blockstudio-native-script-js',
	'blockstudio-blockstudio-native-style-inline-css',
	'blockstudio-blockstudio-native-style-scoped-css',
	'blockstudio-blockstudio-native-style-css',
	'blockstudio-blockstudio-native-nested-script-inline-js',
	'blockstudio-blockstudio-native-nested-script-js',
	'blockstudio-blockstudio-native-nested-style-inline-css',
	'blockstudio-blockstudio-native-nested-style-scoped-css',
	'blockstudio-blockstudio-native-nested-style-css',
	'blockstudio-blockstudio-element-inline-2-script-inline-js',
	'blockstudio-blockstudio-element-inline-2-style-css',
	'blockstudio-blockstudio-element-script-script-inline-js',
	'blockstudio-blockstudio-element-script-script-js',
	'blockstudio-blockstudio-element-script-style-scss',
	'blockstudio-blockstudio-element-script-variables-scss',
	'blockstudio-blockstudio-component-useblockprops-default-style-css',
	'blockstudio-blockstudio-assets-dist-test-inline-css',
	'blockstudio-blockstudio-assets-dist-test-inline-js',
	'blockstudio-blockstudio-assets-dist-test-scoped-css',
	'blockstudio-blockstudio-assets-dist-test-css',
	'blockstudio-blockstudio-assets-dist-test-js',
	'blockstudio-blockstudio-assets-dist-test2-inline-css',
	'blockstudio-blockstudio-assets-dist-test2-inline-js',
	'blockstudio-blockstudio-assets-dist-test2-scoped-css',
	'blockstudio-blockstudio-assets-dist-test2-css',
	'blockstudio-blockstudio-assets-dist-test2-js',
	'blockstudio-blockstudio-assets-none-test-inline-css',
	'blockstudio-blockstudio-assets-none-test-inline-js',
	'blockstudio-blockstudio-assets-none-test-scoped-css',
	'blockstudio-blockstudio-assets-none-test-css',
	'blockstudio-blockstudio-assets-none-test-js',
	'blockstudio-blockstudio-assets-none-test2-inline-css',
	'blockstudio-blockstudio-assets-none-test2-inline-js',
	'blockstudio-blockstudio-assets-none-test2-scoped-css',
	'blockstudio-blockstudio-assets-none-test2-css',
	'blockstudio-blockstudio-assets-none-test2-js',
	'blockstudio-blockstudio-assets-test-inline-css',
	'blockstudio-blockstudio-assets-test-inline-js',
	'blockstudio-blockstudio-assets-test-scoped-css',
	'blockstudio-blockstudio-assets-test-css',
	'blockstudio-blockstudio-assets-test-js',
	'blockstudio-blockstudio-assets-test2-inline-css',
	'blockstudio-blockstudio-assets-test2-inline-js',
	'blockstudio-blockstudio-assets-test2-scoped-css',
	'blockstudio-blockstudio-assets-test2-css',
	'blockstudio-blockstudio-assets-test2-js',
	'blockstudio-blockstudio-assets-none-twig-test-inline-css',
	'blockstudio-blockstudio-assets-none-twig-test-inline-js',
	'blockstudio-blockstudio-assets-none-twig-test-scoped-css',
	'blockstudio-blockstudio-assets-none-twig-test-css',
	'blockstudio-blockstudio-assets-none-twig-test-js',
	'blockstudio-blockstudio-assets-none-twig-test2-inline-css',
	'blockstudio-blockstudio-assets-none-twig-test2-inline-js',
	'blockstudio-blockstudio-assets-none-twig-test2-scoped-css',
	'blockstudio-blockstudio-assets-none-twig-test2-css',
	'blockstudio-blockstudio-assets-none-twig-test2-js',
	'blockstudio-blockstudio-native-script-editor-js',
	'blockstudio-blockstudio-native-style-editor-css',
	'blockstudio-blockstudio-native-nested-script-editor-js',
	'blockstudio-blockstudio-native-nested-style-editor-css',
	'blockstudio-blockstudio-assets-dist-test-editor-css',
	'blockstudio-blockstudio-assets-dist-test-editor-js',
	'blockstudio-blockstudio-assets-dist-test2-editor-css',
	'blockstudio-blockstudio-assets-dist-test2-editor-js',
	'blockstudio-blockstudio-assets-none-test-editor-css',
	'blockstudio-blockstudio-assets-none-test-editor-js',
	'blockstudio-blockstudio-assets-none-test2-editor-css',
	'blockstudio-blockstudio-assets-none-test2-editor-js',
	'blockstudio-blockstudio-events-script-editor-js',
	'blockstudio-blockstudio-assets-test-editor-css',
	'blockstudio-blockstudio-assets-test-editor-js',
	'blockstudio-blockstudio-assets-test2-editor-css',
	'blockstudio-blockstudio-assets-test2-editor-js',
	'blockstudio-blockstudio-assets-none-twig-test-editor-css',
	'blockstudio-blockstudio-assets-none-twig-test-editor-js',
	'blockstudio-blockstudio-assets-none-twig-test2-editor-css',
	'blockstudio-blockstudio-assets-none-twig-test2-editor-js',
];

testType(['assets', 'text'], false, () => {
	return [
		...assetIds.map((id) => ({
			description: `asset ${id} exists`,
			testFunction: async (page: Page) => {
				await atLeast(page, `#${id}`, 1);
			},
		})),
		{
			description: 'global scripts exist',
			testFunction: async (page: Page) => {
				await atLeast(page, '[id*="global-script-js"]', 3);
			},
		},
		{
			description: 'global styles exist',
			testFunction: async (page: Page) => {
				await atLeast(page, '[id*="global-style-css"]', 2);
			},
		},
	];
});
