import { expect, Page, Frame } from '@playwright/test';
import { delay, testType } from '../utils/playwright-utils';

testType('innerblocks-nested', false, () => {
  return [
    {
      description: 'nested children render inside group',
      testFunction: async (page: Page, canvas: Frame) => {
        await delay(3000);
        await expect(canvas.locator('[data-type="core/heading"]')).toBeVisible({ timeout: 10000 });
        const group = canvas.locator('[data-type="core/group"]');
        await expect(group).toBeVisible({ timeout: 10000 });
        const nestedParagraph = group.locator('[data-type="core/paragraph"]');
        await expect(nestedParagraph).toBeVisible({ timeout: 10000 });
      },
    },
  ];
});
