import { Page } from '@playwright/test';
import { count, testType, text } from '../../../../playwright-utils';

testType('repeater', false, () => {
  return [
    {
      description: 'correct minimized value',
      testFunction: async (page: Page) => {
        await page.evaluate(() => window.localStorage.clear());

        await page.click(
          '[aria-label="Repeater Minimized"] + div .blockstudio-repeater__minimize'
        );
        await count(page, 'text=Prefix: 20 - Suffix', 1);
        await page.click(
          '[aria-label="Repeater Minimized"] + div .blockstudio-repeater__minimize'
        );
      },
    },
    {
      description: 'correct min',
      testFunction: async (page: Page) => {
        await count(page, '.components-range-control', 2);
      },
    },
    {
      description: 'correct max',
      testFunction: async (page: Page) => {
        await page.click('text=Add repeater 3');
        await count(page, '.components-range-control', 3);
        await count(page, '.is-secondary[disabled]', 1);
      },
    },
    {
      description: 'correct min on add',
      testFunction: async (page: Page) => {
        await page.click('text=Add repeater 1');
        await count(page, '.components-range-control', 5);
      },
    },
    {
      description: 'remove',
      testFunction: async (page: Page) => {
        page.on('dialog', (dialog) => dialog.accept());
        await page.click(
          '[data-rfd-draggable-id="repeater[0].repeater[0].repeater[2]"] > div > .blockstudio-repeater__remove'
        );
        await count(
          page,
          '[data-rfd-draggable-id="repeater[0].repeater[0].repeater[0]"] > div > [role="button"]',
          2
        );
      },
    },
    {
      description: 'duplicate',
      testFunction: async (page: Page) => {
        await page.click(
          '[data-rfd-draggable-id="repeater[0]"] .blockstudio-repeater__duplicate'
        );
        await count(page, '[data-rfd-draggable-id="repeater[1]"]', 1);
        await page.click(
          '[data-rfd-draggable-id="repeater[0]"] .blockstudio-repeater__minimize'
        );
        await page.click(
          '[data-rfd-draggable-id="repeater[1]"] .blockstudio-repeater__minimize'
        );
        await count(page, 'text=Repeater element', 3);
      },
    },
    {
      description: 'reorder',
      testFunction: async (page: Page) => {
        await page.locator('[aria-label="blue"]:visible').click();
        await page.focus('[data-rfd-draggable-id="repeater[0]"]');
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('ArrowDown');
        await text(
          page,
          '"text":"Override test","repeater":[{"files":false,"defaultValueLabel":"Three","color":{"name":"red","value":"#f00","slug":"red"},"text":"test","wysiwyg":false,"repeater":[{"files":false,"defaultValueLabel":"Three","populateCustom":"three","number":20,"repeater":[{"range":50},{"range":50}]}]},{"files":false,"defaultValueLabel":"Three","color":{"value":"#00f","name":"blue","slug":"blue"},"text":"test","wysiwyg":false,"repeater":[{"files":false,"defaultValueLabel":"Three","populateCustom":"three","number":20,"repeater":[{"range":50},{"range":50}]}]},{"files":false,"defaultValueLabel":"Three","color":{"name":"red","value":"#f00","slug":"red"},"text":"test","wysiwyg":false,"repeater":[{"files":false,"defaultValueLabel":"Three","populateCustom":"three","number":20,"repeater":[{"range":50},{"range":50}]}]}]'
        );
      },
    },
  ];
});
