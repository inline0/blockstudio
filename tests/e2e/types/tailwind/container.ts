import { Page } from '@playwright/test';
import {
  addBlock,
  addTailwindClass,
  checkStyle,
  count,
  delay,
  removeBlocks,
  save,
  testType,
} from '../../utils/playwright-utils';

testType('tailwind', false, () => {
  return [
    {
      description: 'add container',
      testFunction: async (page: Page) => {
        await removeBlocks(page);
        await addBlock(page, 'container');
        await page.click(
          '[data-type="blockstudio/container"] .block-editor-inserter__toggle'
        );
        await count(page, '.components-popover__content', 2);
        await page.fill('[placeholder="Search"]', 'heading');
        await page.locator('[role="option"]').filter({ hasText: /^Heading$/ }).click();
      },
    },
    {
      description: 'add attributes',
      testFunction: async (page: Page) => {
        await addTailwindClass(page, 'Container', 'text-blue-950');
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
          .locator('.blockstudio-builder__controls [aria-label="More"]')
          .nth(2)
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
          .locator('.blockstudio-builder__controls [aria-label="More"]')
          .nth(3)
          .click();
        await page.click('text=Insert Media');
        await page.click('text=Media Library');
        await page.click('[data-id="3081"]');
        await page.click('.media-button-select');
        await count(page, '.blockstudio-fields__field--files-toggle', 1);

        await count(page, '[data-test="test"]', 1);
        await count(page, '[data-link="https://google.com"]', 1);
      },
    },
    {
      description: 'test classes',
      testFunction: async (page: Page) => {
        await addTailwindClass(page, 'Container', 'bg-red-50');
        await page.click('.blockstudio-builder__controls [role="combobox"]');
        await page.fill(
          '.blockstudio-builder__controls [role="combobox"]',
          'bg-red'
        );
        // await page.keyboard.press('ArrowDown');
        // await count(page, '.\\!bg-red-100', 1);
        // await page.keyboard.press('Escape');
        // await count(page, '.\\!bg-red-100', 0);
      },
    },
    {
      description: 'add classes',
      testFunction: async (page: Page) => {
        await addTailwindClass(page, 'Container', 'p-4');
        await addTailwindClass(page, 'Container', 'bg-red-500');
        await count(page, '.p-4', 1);
        await count(page, '.bg-red-500', 1);
        await addTailwindClass(page, 'Container', 'bg-red-600');
        await count(page, '.bg-red-600', 1);
        await count(page, '.bg-red-500', 0);
      },
    },
    {
      description: 'check frontend',
      testFunction: async (page: Page) => {
        await save(page);
        await delay(5000);
        await page.goto('http://localhost:8888/native-single');
        await count(page, '.bg-red-600', 1);
        await checkStyle(
          page,
          '.bg-red-600',
          'background',
          'rgb(220, 38, 38) none repeat scroll 0% 0% / auto padding-box border-box'
        );
        await count(page, '.p-4', 1);
        await checkStyle(page, '.p-4', 'padding', '16px');
        await count(page, '[data-test="test"]', 1);
        await count(page, '[data-link="https://google.com"]', 1);
        await count(page, '[data-image*="http"]', 1);
        await page.goto(
          `http://localhost:8888/wp-admin/post.php?post=1483&action=edit`
        );
        await page.reload();
        await count(page, '.editor-styles-wrapper', 1);
      },
    },
  ];
});
