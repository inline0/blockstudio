import { Page } from '@playwright/test';
import { count, delay, save, testType } from '../utils/playwright-utils';

testType('attributes', false, () => {
  return [
    {
      description: 'add attributes',
      testFunction: async (page: Page) => {
        await page.click('text=Add Attribute');
        await page
          .locator('[placeholder="Attribute"]')
          .nth(0)
          .fill('data-test');
        await page.locator('.cm-activeLine.cm-line').nth(0).click();
        await page.keyboard.press('Meta+A');
        await page.keyboard.press('Backspace');
        await page.keyboard.type('test');

        await page.click('text=Add Attribute');
        await page
          .locator('[placeholder="Attribute"]')
          .nth(1)
          .fill('data-link');
        await page.locator('.cm-activeLine.cm-line').nth(1).click();
        await page
          .locator('.blockstudio-fields [aria-label="More"]')
          .nth(1)
          .click();
        await page.click('text=Insert Link');
        await page.fill(
          '[placeholder="Search or type URL"]',
          'https://google.com'
        );
        await page.keyboard.press('Enter');
        await page.click('.components-modal__header [aria-label="Close"]');

        await page.click('text=Add Attribute');
        await page
          .locator('[placeholder="Attribute"]')
          .nth(2)
          .fill('data-image');
        await page.locator('.cm-activeLine.cm-line').nth(1).click();
        await page
          .locator('.blockstudio-fields [aria-label="More"]')
          .nth(2)
          .click();
        await delay(1000);
        await page.click('text=Insert Media');
        await delay(1000);
        await page.click('text=Media Library');
        await page.click('[data-id="3081"]');
        await page.click('.media-button-select');
        await count(page, '.blockstudio-fields__field--files-toggle', 1);

        await count(page, '[data-test="test"]', 1);
        await count(page, '[data-link="https://google.com"]', 1);
      },
    },
    {
      description: 'check frontend',
      testFunction: async (page: Page) => {
        await save(page);
        await delay(5000);
        await page.goto('http://localhost:8888/native-single');
        await count(page, '[data-test="test"]', 1);
        await count(page, '[data-link="https://google.com"]', 1);
        await count(page, '[data-image*="http://"]', 1);
        await page.goto(
          `http://localhost:8888/wp-admin/post.php?post=1483&action=edit`
        );
        await page.reload();
        await count(page, '.editor-styles-wrapper', 1);
      },
    },
  ];
});
