import { expect, test, Page } from '@playwright/test';
import {
  getSharedPage,
  getEditorCanvas,
  resetPageState,
  addBlock,
  openSidebar,
  count,
} from '../../utils/playwright-utils';

let page: Page;

const row = (index: number) =>
  page.locator(`[data-rfd-draggable-id="items[${index}]"]`);
const rowInput = (index: number) =>
  row(index).locator('.blockstudio-fields__field--text input').first();
const notice = (index: number) =>
  row(index).locator('.blockstudio-fields__unsupported');

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await getSharedPage(browser);
  await resetPageState(page);
});

test.describe('repeater tabs unsupported', () => {
  test('insert block', async () => {
    await addBlock(page, 'type-repeater-tabs-unsupported');
    const canvas = await getEditorCanvas(page);
    await count(canvas, '.is-root-container > .wp-block', 1);
  });

  test('every row shows the notice instead of tabs', async () => {
    await openSidebar(page);
    await count(page, '[data-rfd-draggable-id^="items["]', 2);
    for (const index of [0, 1]) {
      await expect(notice(index)).toHaveText(
        'Field type tabs is not supported inside a repeater.',
      );
    }
    await count(page, '.blockstudio-fields__field--tabs', 0);
  });

  test('the sibling field of the row keeps working', async () => {
    await expect(rowInput(0)).toHaveValue('First');
    await rowInput(0).fill('Changed');
    await expect(rowInput(0)).toHaveValue('Changed');
    await expect(rowInput(1)).toHaveValue('Second');
  });

  test('an added row shows the notice and its sibling field', async () => {
    await page.click('text=Add item');
    await count(page, '[data-rfd-draggable-id^="items["]', 3);
    await expect(notice(2)).toBeVisible();
    await expect(rowInput(2)).toBeVisible();
  });

  test('a removed row leaves the other rows intact', async () => {
    await row(2).locator('.blockstudio-repeater__remove').first().click();
    const dialog = page.getByRole('dialog', {
      name: 'Remove row 3',
      exact: true,
    });
    await dialog.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(dialog).toHaveCount(0);
    await count(page, '[data-rfd-draggable-id^="items["]', 2);
    await expect(notice(0)).toBeVisible();
    await expect(notice(1)).toBeVisible();
    await expect(rowInput(0)).toHaveValue('Changed');
  });

  test('clean up', async () => {
    await resetPageState(page);
  });
});
