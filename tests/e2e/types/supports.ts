import { expect, Page } from '@playwright/test';
import { saveAndReload, testType, text } from '../utils/playwright-utils';

testType('classes', '"classes":"class-1 class-2"', () => {
  return [
    {
      description: 'blockstudio block',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-classes"]');
        await page.click('text=Advanced');
        await page.getByLabel('HTML Anchor').fill('anchor-test');
        await page.getByLabel('Additional CSS class(es)').fill('class-test');
        await text(page, '"anchor":"anchor-test","className":"class-test"');
        await saveAndReload(page);
      },
    },
    {
      description: 'check blockstudio block',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-classes"]');
        await text(page, '"anchor":"anchor-test","className":"class-test"');
        await saveAndReload(page);
      },
    },
    {
      description: 'core block',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-classes"]');
        await page.keyboard.press('Enter');
        await page.keyboard.type('/heading', {
          delay: 100,
        });
        await page.keyboard.press('Enter');
        await page.keyboard.type('Heading test', {
          delay: 100,
        });
        await page.click('[data-type="core/heading"]');
        await page.click('text=Advanced');
        await page.getByLabel('HTML Anchor').fill('anchor-test');
        await page.getByLabel('Additional CSS class(es)').fill('class-test');
        await saveAndReload(page);
      },
    },
    {
      description: 'check core block',
      testFunction: async (page: Page) => {
        await page.click('[data-type="core/heading"]');
        await page.click('text=Advanced');
        await expect(page.getByLabel('HTML Anchor')).toHaveValue('anchor-test');
        await expect(page.getByLabel('Additional CSS class(es)')).toHaveValue(
          'class-test'
        );
      },
    },
  ];
});
