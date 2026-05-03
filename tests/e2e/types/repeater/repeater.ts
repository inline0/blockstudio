import { expect, Page, Frame } from '@playwright/test';
import { count, testType, text } from '../../utils/playwright-utils';

testType('repeater', false, () => {
  return [
    {
      description: 'opens files media library after editing repeater text',
      testFunction: async (page: Page, _canvas: Frame) => {
        const firstRepeater = page.locator(
          '[data-rfd-draggable-id="repeater[0]"]',
        );
        const textInput = firstRepeater
          .locator('.blockstudio-fields__field--text input')
          .first();
        const mediaButton = firstRepeater
          .locator('.blockstudio-fields__field--files button', {
            hasText: 'Open Media Library',
          })
          .first();

        await textInput.fill('Issue 42');
        await mediaButton.click();

        await expect(page.locator('.media-frame')).toBeVisible({
          timeout: 10000,
        });
        await page.keyboard.press('Escape');
        await expect(page.locator('.media-frame')).toHaveCount(0);
        await textInput.fill('test');
      },
    },
    {
      description: 'correct minimized value',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.evaluate(() => window.localStorage.clear());

        await page
          .locator('.blockstudio-fields__field--repeater')
          .filter({ hasText: 'Repeater Minimized' })
          .locator('.blockstudio-repeater__minimize')
          .first()
          .click();
        await count(page, 'text=Prefix: 20 - Suffix', 1);
        await page
          .locator('.blockstudio-fields__field--repeater')
          .filter({ hasText: 'Repeater Minimized' })
          .locator('.blockstudio-repeater__minimize')
          .first()
          .click();
      },
    },
    {
      description: 'correct min',
      testFunction: async (page: Page, _canvas: Frame) => {
        await count(page, '.components-range-control', 2);
      },
    },
    {
      description: 'correct max',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.click('text=Add repeater 3');
        await count(page, '.components-range-control', 3);
        await count(page, '.is-secondary[disabled]', 1);
      },
    },
    {
      description: 'correct min on add',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.click('text=Add repeater 1');
        await count(page, '.components-range-control', 5);
      },
    },
    {
      description: 'remove',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.click(
          '[data-rfd-draggable-id="repeater[0].repeater[0].repeater[2]"] > div > .blockstudio-repeater__remove',
        );
        await count(
          page,
          '[data-rfd-draggable-id="repeater[0].repeater[0].repeater[0]"] > div > [role="button"]',
          2,
        );
      },
    },
    {
      description: 'duplicate',
      testFunction: async (page: Page, _canvas: Frame) => {
        await page.click(
          '[data-rfd-draggable-id="repeater[0]"] .blockstudio-repeater__duplicate',
        );
        await count(page, '[data-rfd-draggable-id="repeater[1]"]', 1);
        await page.click(
          '[data-rfd-draggable-id="repeater[0]"] .blockstudio-repeater__minimize',
        );
        await page.click(
          '[data-rfd-draggable-id="repeater[1]"] .blockstudio-repeater__minimize',
        );
        await count(page, 'text=Repeater element', 3);
      },
    },
    {
      description: 'reorder',
      testFunction: async (page: Page, canvas: Frame) => {
        await page.locator('[aria-label="blue"]:visible').click();
        await page.focus('[data-rfd-draggable-id="repeater[0]"]');
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('ArrowDown');
        await text(
          canvas,
          '"text":"Override test","repeater":[{"files":false,"defaultValueLabel":"Three","color":{"name":"red","value":"#f00","slug":"red"},"text":"test","wysiwyg":false,"repeater":[{"files":false,"defaultValueLabel":"Three","populateCustom":"three","number":20,"repeater":[{"range":50},{"range":50}]}]},{"files":false,"defaultValueLabel":"Three","color":{"value":"#00f","name":"blue","slug":"blue"},"text":"test","wysiwyg":false,"repeater":[{"files":false,"defaultValueLabel":"Three","populateCustom":"three","number":20,"repeater":[{"range":50},{"range":50}]}]},{"files":false,"defaultValueLabel":"Three","color":{"name":"red","value":"#f00","slug":"red"},"text":"test","wysiwyg":false,"repeater":[{"files":false,"defaultValueLabel":"Three","populateCustom":"three","number":20,"repeater":[{"range":50},{"range":50}]}]}]',
        );
      },
    },
  ];
});
