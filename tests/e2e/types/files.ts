import { FrameLocator } from '@playwright/test';
import { click, countText, delay, testType, text } from '../utils/playwright-utils';

testType('files', false, () => {
  return Array.from({ length: 6 }).map((_, index) => {
    return {
      description: `select files ${index}`,
      testFunction: async (editor: FrameLocator) => {
        async function clickFirst() {
          await editor
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
          await editor
            .locator('.blockstudio-fields__field--files')
            .nth(index)
            .locator('.blockstudio-fields__field--files-toggle')
            .nth(indexInner)
            .click();
        }

        await editor.locator(`text=Open Media Library`).nth(index).click();
        await click(editor, '#menu-item-browse:visible');
        if (index === 0 || index === 1 || index === 2) {
          await editor.locator('body').press('Meta');
          await click(editor, 'li[data-id="1604"]:visible');
          await editor.locator('body').press('Meta');
          await delay(1000);
          await click(editor, '.media-frame-toolbar button:visible');
          if (index === 0) {
            await editor
              .locator(`[aria-label="Files Multiple"] + div select`)
              .selectOption({ index: 0 });
            await text(editor, '"filesMultiple__size":"thumbnail"');
            await countText(editor, '[{"ID":1604', 1);
          } else if (index === 1) {
            await countText(editor, '[1604]', 1);
          } else {
            await countText(
              editor,
              '["https:\\/\\/fabrikat.local\\/blockstudio\\/wp-content\\/uploads\\/sites\\/309\\/2022\\/12\\/blockstudioEDDRetina.png"]',
              1
            );
          }
          await editor.locator(`text=Open Media Library`).nth(index).click();
          await click(editor, '#menu-item-browse:visible');
          await editor.locator('body').press('Meta');
          await click(editor, 'li[data-id="1605"]:visible');
          await delay(1000);
          await click(editor, '.media-frame-toolbar button:visible');
          if (index === 0) {
            await countText(editor, '{"ID":1604', 1);
            await clickFirst();
            await countText(editor, '{"ID":1604', 0);
            await clickFirst();
            await countText(editor, '{"ID":1604', 1);
          } else if (index === 1) {
            await editor
              .locator('[aria-label="Files - Multiple - ID"] + div [data-rfd-drag-handle-draggable-id="1604"]')
              .focus();
            await editor.locator('body').press('ArrowDown');
            await countText(editor, '[1605,1604]', 1);
            await editor.locator('body').press('ArrowUp');
            await countText(editor, '[1604,1605]', 1);
            await clickFirst();
            await countText(editor, '[1604,1605]', 0);
            await clickFirst();
            await countText(editor, '[1604,1605]', 1);
          } else {
            await countText(
              editor,
              '["https:\\/\\/fabrikat.local\\/blockstudio\\/wp-content\\/uploads\\/sites\\/309\\/2022\\/12\\/blockstudioEDDRetina.png","https:\\/\\/fabrikat.local\\/blockstudio\\/wp-content\\/uploads\\/sites\\/309\\/2022\\/12\\/blockstudioSEO.png"]',
              1
            );
            await clickFirst();
            await countText(
              editor,
              '["https:\\/\\/fabrikat.local\\/blockstudio\\/wp-content\\/uploads\\/sites\\/309\\/2022\\/12\\/blockstudioEDDRetina.png","https:\\/\\/fabrikat.local\\/blockstudio\\/wp-content\\/uploads\\/sites\\/309\\/2022\\/12\\/blockstudioSEO.png"]',
              0
            );
            await clickFirst();
            await countText(
              editor,
              '["https:\\/\\/fabrikat.local\\/blockstudio\\/wp-content\\/uploads\\/sites\\/309\\/2022\\/12\\/blockstudioEDDRetina.png","https:\\/\\/fabrikat.local\\/blockstudio\\/wp-content\\/uploads\\/sites\\/309\\/2022\\/12\\/blockstudioSEO.png"]',
              1
            );
          }
          await editor.locator(`text=Open Media Library`).nth(index).click();
          await click(editor, '#menu-item-browse:visible');
          await editor.locator('body').press('Meta');
          await click(editor, 'li[data-id="8"]:visible');
          await delay(1000);
          await click(editor, '.media-frame-toolbar button:visible');
          if (index === 0) {
            await countText(editor, '{"ID":8', 1);
          } else if (index === 1) {
            await countText(editor, '8]', 1);
          } else {
            await countText(
              editor,
              '"https:\\/\\/fabrikat.local\\/blockstudio\\/wp-content\\/uploads\\/sites\\/309\\/2020\\/11\\/gutenbergEdit.mp4"',
              1
            );
          }
          await delMedia(0);
          await click(editor, '.block-editor-block-card__title');
          await delMedia(0);
        }
        if (index === 3 || index === 4 || index === 5) {
          await editor.locator('body').press('Meta');
          await click(editor, 'li[data-id="1605"]:visible');
          await click(editor, 'li[data-id="1604"]:visible');
          await editor.locator('body').press('Meta');
          await delay(1000);
          await click(editor, '.media-frame-toolbar button:visible');
          if (index === 3) {
            await countText(editor, '"filesSingle":{"ID":1604', 1);
            await clickFirst();
            await countText(editor, '"filesSingle":false', 1);
            await clickFirst();
            await countText(editor, '"filesSingle":{"ID":1604', 1);
          } else if (index === 4) {
            await countText(editor, '"filesSingleId":1604', 1);
            await clickFirst();
            await countText(editor, '"filesSingleId":false', 1);
            await clickFirst();
            await countText(editor, '"filesSingleId":1604', 1);
          } else {
            await countText(
              editor,
              '"filesSingleUrl":"https:\\/\\/fabrikat.local\\/blockstudio\\/wp-content\\/uploads\\/sites\\/309\\/2022\\/12\\/blockstudioEDDRetina.png"',
              1
            );
            await clickFirst();
            await countText(editor, '"filesSingleUrl":false', 1);
            await clickFirst();
            await countText(
              editor,
              '"filesSingleUrl":"https:\\/\\/fabrikat.local\\/blockstudio\\/wp-content\\/uploads\\/sites\\/309\\/2022\\/12\\/blockstudioEDDRetina.png"',
              1
            );
          }
          await delMedia(0);
        }
      },
    };
  });
});
