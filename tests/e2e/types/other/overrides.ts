import { Page, Frame } from '@playwright/test';
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
			testFunction: async (_page: Page, canvas: Frame) => {
				await canvas.click('[data-type="blockstudio-type/override"]');
				await count(canvas, '.blockstudio-element__placeholder', 1);
			},
		},
		{
			description: 'add images from media library',
			testFunction: async (page: Page, _canvas: Frame) => {
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
			testFunction: async (_page: Page, canvas: Frame) => {
				await count(canvas, '.blockstudio-element-gallery__content', 2);
				await count(canvas, '[data-test]', 2);
			},
		},
		{
			description: 'check gallery styles',
			testFunction: async (_page: Page, canvas: Frame) => {
				await checkStyle(
					canvas,
					'.blockstudio-element-gallery',
					'gridAutoRows',
					'30px'
				);
				await checkStyle(canvas, '.blockstudio-element-gallery', 'margin', '12px');
			},
		},
	];
});
