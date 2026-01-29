import { Page } from '@playwright/test';
import { click, countText, delay, testType, text } from '../utils/playwright-utils';

testType('files', false, () => {
  return Array.from({ length: 6 }).map((_, index) => {
    return {
      description: `select files ${index}`,
      testFunction: async (editor: Page) => {
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
          // Use modifiers option for multi-select instead of separate press/release
          await editor.locator('li[data-id="1604"]:visible').click({ modifiers: ['Meta'] });
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
            // Check for URL array containing the filename (wp-env uses localhost:8888)
            await countText(
              editor,
              'blockstudioEDDRetina.png"]',
              1
            );
          }
          await editor.locator(`text=Open Media Library`).nth(index).click();
          await click(editor, '#menu-item-browse:visible');
          // Use modifiers option for adding to selection
          await editor.locator('li[data-id="1605"]:visible').click({ modifiers: ['Meta'] });
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
            // Check for both filenames in the URL array
            await countText(
              editor,
              'blockstudioEDDRetina.png","http',
              1
            );
            await countText(
              editor,
              'blockstudioSEO.png"]',
              1
            );
            await clickFirst();
            await countText(
              editor,
              'blockstudioEDDRetina.png","http',
              0
            );
            await clickFirst();
            await countText(
              editor,
              'blockstudioEDDRetina.png","http',
              1
            );
          }
          await editor.locator(`text=Open Media Library`).nth(index).click();
          await click(editor, '#menu-item-browse:visible');
          // Use modifiers option for adding to selection
          await editor.locator('li[data-id="8"]:visible').click({ modifiers: ['Meta'] });
          await delay(1000);
          await click(editor, '.media-frame-toolbar button:visible');
          if (index === 0) {
            await countText(editor, '{"ID":8', 1);
          } else if (index === 1) {
            await countText(editor, '8]', 1);
          } else {
            // Check for video filename
            await countText(
              editor,
              'gutenbergEdit.mp4"',
              1
            );
          }
          await delMedia(0);
          await click(editor, '.block-editor-block-card__title');
          await delMedia(0);
        }
        if (index === 3 || index === 4 || index === 5) {
          // For single-select fields, multi-select should only keep the last clicked
          await editor.locator('li[data-id="1605"]:visible').click({ modifiers: ['Meta'] });
          await editor.locator('li[data-id="1604"]:visible').click({ modifiers: ['Meta'] });
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
            // Check for URL containing filename - use specific field pattern to avoid multiple matches
            await countText(
              editor,
              '"filesSingleUrl":"http',
              1
            );
            // The filename appears many times in the JSON (all size URLs), so just verify the field exists with a URL value
            await clickFirst();
            await countText(editor, '"filesSingleUrl":false', 1);
            await clickFirst();
            // Verify filesSingleUrl has a URL value again (not checking specific filename due to multiple occurrences)
            await countText(
              editor,
              '"filesSingleUrl":"http',
              1
            );
          }
          await delMedia(0);
        }
      },
    };
  });
});
