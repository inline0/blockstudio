import { Page } from '@playwright/test';
import { countText, delay, testType, text } from '../utils/playwright-utils';

testType('files', false, () => {
  return Array.from({ length: 6 }).map((_, index) => {
    return {
      description: `select files ${index}`,
      testFunction: async (page: Page) => {
        async function clickFirst() {
          await page
            .locator('.blockstudio-fields__field--files')
            .nth(index)
            .locator(
              '[data-rfd-draggable-context-id] .blockstudio-fields__field-toggle'
            )
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
          await page.keyboard.down('Meta');
          await page.click('li[data-id="1604"]:visible');
          await page.keyboard.up('Meta');
          await delay(1000);
          await page.click('.media-frame-toolbar button:visible');
          if (index === 0) {
            await page
              .locator(`[aria-label="Files Multiple"] + div select`)
              .selectOption({ index: 0 });
            await text(page, '"filesMultiple__size":"thumbnail"');
            await countText(page, '[{"ID":1604', 1);
          } else if (index === 1) {
            await countText(page, '[1604]', 1);
          } else {
            await countText(
              page,
              '["http:\\/\\/localhost:8888\\/wp-content\\/uploads\\/',
              1
            );
          }
          await page.locator(`text=Open Media Library`).nth(index).click();
          await page.locator('#menu-item-browse:visible').click();
          await page.keyboard.down('Meta');
          await page.click('li[data-id="1605"]:visible');
          await delay(1000);
          await page.click('.media-frame-toolbar button:visible');
          if (index === 0) {
            await countText(page, '{"ID":1604', 1);
            await clickFirst();
            await countText(page, '{"ID":1604', 0);
            await clickFirst();
            await countText(page, '{"ID":1604', 1);
          } else if (index === 1) {
            await page.focus(
              '[aria-label="Files - Multiple - ID"] + div [data-rfd-drag-handle-draggable-id="1604"]'
            );
            await page.keyboard.press('ArrowDown');
            await countText(page, '[1605,1604]', 1);
            await page.keyboard.press('ArrowUp');
            await countText(page, '[1604,1605]', 1);
            await clickFirst();
            await countText(page, '[1604,1605]', 0);
            await clickFirst();
            await countText(page, '[1604,1605]', 1);
          } else {
            await countText(page, 'blockstudioEDDRetina.png","http:', 1);
            await clickFirst();
            await countText(page, 'blockstudioEDDRetina.png","http:', 0);
            await clickFirst();
            await countText(page, 'blockstudioEDDRetina.png","http:', 1);
          }
          await page.locator(`text=Open Media Library`).nth(index).click();
          await page.locator('#menu-item-browse:visible').click();
          await page.keyboard.down('Meta');
          await page.click('li[data-id="8"]:visible');
          await delay(1000);
          await page.click('.media-frame-toolbar button:visible');
          if (index === 0) {
            await countText(page, '{"ID":8', 1);
          } else if (index === 1) {
            await countText(page, '8]', 1);
          } else {
            await countText(page, 'gutenbergEdit.mp4"', 1);
          }
          await delMedia(0);
          await page.click('.block-editor-block-card__title');
          await delMedia(0);
        }
        if (index === 3 || index === 4 || index === 5) {
          await page.keyboard.down('Meta');
          await page.click('li[data-id="1605"]:visible');
          await page.click('li[data-id="1604"]:visible');
          await page.keyboard.up('Meta');
          await delay(1000);
          await page.click('.media-frame-toolbar button:visible');
          if (index === 3) {
            await countText(page, '"filesSingle":{"ID":1604', 1);
            await clickFirst();
            await countText(page, '"filesSingle":false', 1);
            await clickFirst();
            await countText(page, '"filesSingle":{"ID":1604', 1);
          } else if (index === 4) {
            await countText(page, '"filesSingleId":1604', 1);
            await clickFirst();
            await countText(page, '"filesSingleId":false', 1);
            await clickFirst();
            await countText(page, '"filesSingleId":1604', 1);
          } else {
            await countText(page, '"filesSingleUrl":"http:', 1);
            await clickFirst();
            await countText(page, '"filesSingleUrl":false', 1);
            await clickFirst();
            await countText(page, '"filesSingleUrl":"http:', 1);
          }
          await delMedia(0);
        }
      },
    };
  });
});
