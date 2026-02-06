import { Page, Frame } from '@playwright/test';
import { checkStyle, testType } from '../../utils/playwright-utils';

testType(['assets-admin', 'text'], false, () => {
	return [
		{
			description: 'check block-editor styles',
			testFunction: async (page: Page, _canvas: Frame) => {
				await checkStyle(
					page,
					'.editor-post-publish-button',
					'backgroundColor',
					'rgb(0, 0, 0)'
				);
			},
		},
	];
});
