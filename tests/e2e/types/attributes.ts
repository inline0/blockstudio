import { Page, Frame } from '@playwright/test';
import {
  count,
  delay,
  getEditorCanvas,
  save,
  testType,
} from '../utils/playwright-utils';

testType('attributes', false, () => {
  return [
    {
      description: 'add attributes',
      testFunction: async (page: Page, canvas: Frame) => {
        const clickVisibleMenuItem = async (label: string) => {
          const item = page
            .locator('.components-popover:visible [role="menuitem"]')
            .filter({ hasText: label })
            .last();
          await item.waitFor({ state: 'visible', timeout: 10000 });
          await item.click();
        };

        await page.click('text=Add Attribute');
        await page
          .locator('[placeholder="Attribute"]')
          .nth(0)
          .fill('data-test');
        await page.locator('.cm-activeLine.cm-line').nth(0).click();
        await page.keyboard.press('ControlOrMeta+A');
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
        await clickVisibleMenuItem('Insert Link');
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
        await clickVisibleMenuItem('Insert Media');
        await page.locator('.media-modal:visible').waitFor({
          state: 'visible',
          timeout: 30000,
        });
        const browseTab = page.locator('#menu-item-browse');
        if (
          await browseTab
            .isVisible({ timeout: 5000 })
            .catch(() => false)
        ) {
          await browseTab.click();
        }
        await page.locator('.attachments-browser').waitFor({
          state: 'visible',
          timeout: 30000,
        });
        const preferredAttachment = page.locator('[data-id="3081"]:visible').first();
        if (await preferredAttachment.count()) {
          await preferredAttachment.click();
        } else {
          await page.locator('.attachments .attachment:visible').first().click();
        }
        await page.click('.media-button-select:visible');
        await count(canvas, '[data-image]', 1);

        await count(canvas, '[data-test="test"]', 1);
        await count(canvas, '[data-link="https://google.com"]', 1);
      },
    },
    {
      description: 'check frontend',
      testFunction: async (page: Page, _canvas: Frame) => {
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
        await getEditorCanvas(page);
      },
    },
  ];
});
