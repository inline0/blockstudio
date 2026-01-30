import { Page } from '@playwright/test';
import {
	checkStyle,
	count,
	delay,
	openSidebar,
	testType,
} from '../../utils/playwright-utils';

testType('override', false, () => {
	return [
		{
			description: 'check placeholder',
			testFunction: async (page: Page) => {
				await page.click('[data-type="blockstudio-type/override"]');
				await count(page, '.blockstudio-element__placeholder', 1);
			},
		},
		{
			description: 'add images from media library',
			testFunction: async (page: Page) => {
				await openSidebar(page);
				await page.locator('text=Open Media Library').click();
				await page.locator('#menu-item-browse:visible').click();
				await page.keyboard.down('Meta');
				await page.click('[data-id="1604"]:visible');
				await page.click('[data-id="1605"]:visible');
				await page.keyboard.up('Meta');
				await delay(1000);
				await page.click('.media-frame-toolbar button:visible');
			},
		},
		{
			description: 'check gallery content',
			testFunction: async (page: Page) => {
				await count(page, '.blockstudio-element-gallery__content', 2);
				await count(page, '[data-test]', 2);
			},
		},
		{
			description: 'check gallery styles',
			testFunction: async (page: Page) => {
				await checkStyle(
					page,
					'.blockstudio-element-gallery',
					'gridAutoRows',
					'30px'
				);
				await checkStyle(page, '.blockstudio-element-gallery', 'margin', '12px');
			},
		},
	];
});
