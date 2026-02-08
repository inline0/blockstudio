import { expect, Page, test } from '@playwright/test';
import {
  addBlock,
  count,
  getEditorCanvas,
  getSharedPage,
  resetPageState,
  save,
  text,
} from '../../utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await getSharedPage(browser);
  await resetPageState(page);
});

// Server state is only available on the frontend. WordPress serializes it
// into the page output, not the REST API response used by the editor.
test.describe('interactivity: server state', () => {
  test('add block', async () => {
    await addBlock(page, 'type-interactivity-server-state');
    const canvas = await getEditorCanvas(page);
    await count(canvas, '.is-root-container > .wp-block', 1);
  });

  test('block renders in editor', async () => {
    const canvas = await getEditorCanvas(page);
    await text(canvas, 'blockstudio-type-interactivity-server-state');
  });

  test('save and visit frontend', async () => {
    await save(page);
    await page.goto('http://localhost:8888/?p=1483', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
  });

  test('frontend: server message renders', async () => {
    await expect(page.locator('#server-message')).toHaveText('Hello from server state');
  });

  test('frontend: server count renders', async () => {
    await expect(page.locator('#server-count')).toHaveText('42');
  });
});
