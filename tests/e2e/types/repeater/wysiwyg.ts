import { expect, Frame, Page, test } from '@playwright/test';
import {
  addBlock,
  count,
  getEditorCanvas,
  getSharedPage,
  openSidebar,
  resetPageState,
} from '../../utils/playwright-utils';

let page: Page;
let canvas: Frame;

const repeaterWysiwygValues = async () =>
  page.evaluate(() => {
    return (window as any).wp.data
      .select('core/block-editor')
      .getBlocks()[0]
      ?.attributes?.blockstudio?.attributes?.repeater?.map(
        (row: { wysiwyg?: string | false }) => row.wysiwyg,
      );
  });

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await getSharedPage(browser);
  await resetPageState(page);
  canvas = await getEditorCanvas(page);
});

test.describe('repeater-wysiwyg', () => {
  test('wysiwyg editor values follow rows after reorder', async () => {
    await page.evaluate(() => {
      localStorage.removeItem('blockstudioRepeater');
    });
    await addBlock(page, 'type-repeater');
    canvas = await getEditorCanvas(page);
    await count(canvas, '.is-root-container > .wp-block', 1);
    await openSidebar(page);

    const firstEditor = page
      .locator(
        '[data-rfd-draggable-id="repeater[0]"] .blockstudio-fields__field--wysiwyg .ProseMirror',
      )
      .first();
    await firstEditor.click();
    await page.keyboard.type('First row');

    await page
      .locator(
        '[data-rfd-draggable-id="repeater[0]"] .blockstudio-repeater__duplicate',
      )
      .first()
      .click();
    await expect(
      page.locator('[data-rfd-draggable-id="repeater[1]"]'),
    ).toHaveCount(1);

    const secondEditor = page
      .locator(
        '[data-rfd-draggable-id="repeater[1]"] .blockstudio-fields__field--wysiwyg .ProseMirror',
      )
      .first();
    await secondEditor.click();
    await page.keyboard.press('ControlOrMeta+A');
    await page.keyboard.type('Second row');

    await expect
      .poll(repeaterWysiwygValues)
      .toEqual(['<p>First row</p>', '<p>Second row</p>']);

    await page.focus('[data-rfd-draggable-id="repeater[0]"]');
    await page.keyboard.press('ArrowDown');

    await expect
      .poll(repeaterWysiwygValues)
      .toEqual(['<p>Second row</p>', '<p>First row</p>']);

    const editors = page.locator(
      '[data-rfd-draggable-id^="repeater["] .blockstudio-fields__field--wysiwyg .ProseMirror',
    );
    await expect(editors.nth(0)).toContainText('Second row');
    await expect(editors.nth(1)).toContainText('First row');
  });
});
