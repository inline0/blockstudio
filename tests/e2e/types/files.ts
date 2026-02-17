import { Page, Frame } from '@playwright/test';
import { countText, delay, testType, text } from '../utils/playwright-utils';

const modKey = process.platform === 'darwin' ? 'Meta' : 'Control';

testType('files', false, () => {
  return Array.from({ length: 6 }).map((_, index) => {
    return {
      description: `select files ${index}`,
      testFunction: async (page: Page, canvas: Frame) => {
        async function clickFirst() {
          await page
            .locator('.blockstudio-fields__field--files')
            .nth(index)
            .locator('.blockstudio-fields__action')
            .first()
            .click({
              force: true,
            });
        }

        async function delMedia(indexInner: number) {
          await page
            .locator('.blockstudio-fields__field--files')
            .nth(index)
            .locator('.blockstudio-fields__field--files-toggle')
            .nth(indexInner)
            .click();
        }

        await page.locator(`text=Open Media Library`).nth(index).click();
        await page.locator('#menu-item-browse:visible').click();
        if (index === 0 || index === 1 || index === 2) {
          await page.keyboard.down(modKey);
          await page.click('li[data-id="1604"]:visible');
          await page.keyboard.up(modKey);
          await delay(1000);
          await page.click('.media-frame-toolbar button:visible');
          if (index === 0) {
            await page
              .locator('.blockstudio-fields__field--files')
              .filter({ hasText: 'Files Multiple' })
              .locator('select')
              .first()
              .selectOption({ index: 0 });
            await text(canvas, '"filesMultiple__size":"thumbnail"');
            await countText(canvas, '[{"ID":1604', 1);
          } else if (index === 1) {
            await countText(canvas, '[1604]', 1);
          } else {
            await countText(
              canvas,
              '["http:\\/\\/localhost:8888\\/wp-content\\/uploads\\/',
              1
            );
          }
          await page.locator(`text=Open Media Library`).nth(index).click();
          await page.locator('#menu-item-browse:visible').click();
          await page.keyboard.down(modKey);
          await page.click('li[data-id="1605"]:visible');
          await page.keyboard.up(modKey);
          await delay(1000);
          await page.click('.media-frame-toolbar button:visible');
          if (index === 0) {
            await countText(canvas, '{"ID":1604', 1);
            await clickFirst();
            await countText(canvas, '{"ID":1604', 0);
            await clickFirst();
            await countText(canvas, '{"ID":1604', 1);
          } else if (index === 1) {
            await page
              .locator('.blockstudio-fields__field--files')
              .filter({ hasText: 'Files - Multiple - ID' })
              .locator('[data-rfd-drag-handle-draggable-id="1604"]')
              .focus();
            await page.keyboard.press('ArrowDown');
            await countText(canvas, '[1605,1604]', 1);
            await page.keyboard.press('ArrowUp');
            await countText(canvas, '[1604,1605]', 1);
            await clickFirst();
            await countText(canvas, '[1604,1605]', 0);
            await clickFirst();
            await countText(canvas, '[1604,1605]', 1);
          } else {
            await countText(canvas, 'blockstudioEDDRetina.png","http:', 1);
            await clickFirst();
            await countText(canvas, 'blockstudioEDDRetina.png","http:', 0);
            await clickFirst();
            await countText(canvas, 'blockstudioEDDRetina.png","http:', 1);
          }
          await page.locator(`text=Open Media Library`).nth(index).click();
          await page.locator('#menu-item-browse:visible').click();
          await page.keyboard.down(modKey);
          await page.click('li[data-id="8"]:visible');
          await page.keyboard.up(modKey);
          await delay(1000);
          await page.click('.media-frame-toolbar button:visible');
          if (index === 0) {
            await countText(canvas, '{"ID":8', 1);
          } else if (index === 1) {
            await countText(canvas, '8]', 1);
          } else {
            await countText(canvas, 'gutenbergEdit.mp4"', 1);
          }
          await delMedia(0);
          await page.click('.block-editor-block-card__title');
          await delMedia(0);
        }
        if (index === 3 || index === 4 || index === 5) {
          await page.keyboard.down(modKey);
          await page.click('li[data-id="1605"]:visible');
          await page.click('li[data-id="1604"]:visible');
          await page.keyboard.up(modKey);
          await delay(1000);
          await page.click('.media-frame-toolbar button:visible');
          if (index === 3) {
            await countText(canvas, '"filesSingle":{"ID":1604', 1);
            await clickFirst();
            await countText(canvas, '"filesSingle":false', 1);
            await clickFirst();
            await countText(canvas, '"filesSingle":{"ID":1604', 1);
          } else if (index === 4) {
            await countText(canvas, '"filesSingleId":1604', 1);
            await clickFirst();
            await countText(canvas, '"filesSingleId":false', 1);
            await clickFirst();
            await countText(canvas, '"filesSingleId":1604', 1);
          } else {
            await countText(canvas, '"filesSingleUrl":"http:', 1);
            await clickFirst();
            await countText(canvas, '"filesSingleUrl":false', 1);
            await clickFirst();
            await countText(canvas, '"filesSingleUrl":"http:', 1);
          }
          await delMedia(0);
        }
      },
    };
  });
});
