import { expect, Page, test } from '@playwright/test';
import {
  addBlock,
  count,
  getEditorCanvas,
  getSharedPage,
  removeBlocks,
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

test.describe('interactivity: directives only', () => {
  test('add block', async () => {
    await addBlock(page, 'type-interactivity-directives');
    const canvas = await getEditorCanvas(page);
    await count(canvas, '.is-root-container > .wp-block', 1);
  });

  test('save and reload editor', async () => {
    await save(page);
    await page.goto('http://localhost:8888/wp-admin/post.php?post=1483&action=edit', { waitUntil: 'domcontentloaded' });
    const canvas = await getEditorCanvas(page);
    await canvas.locator('[data-wp-interactive="blockstudioDirectives"]').waitFor({ state: 'visible', timeout: 30000 });
  });

  test('editor: visible content is visible', async () => {
    const canvas = await getEditorCanvas(page);
    await expect(canvas.locator('#visible-content')).toBeVisible({ timeout: 10000 });
  });

  test('editor: hidden content is hidden', async () => {
    const canvas = await getEditorCanvas(page);
    await expect(canvas.locator('#hidden-content')).toBeHidden({ timeout: 10000 });
  });

  test('editor: context label renders', async () => {
    const canvas = await getEditorCanvas(page);
    await expect(canvas.locator('#context-label')).toHaveText('Context value', { timeout: 10000 });
  });

  test('visit frontend', async () => {
    await page.goto('http://localhost:8888/?p=1483', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
  });

  test('frontend: visible content is visible', async () => {
    await expect(page.locator('#visible-content')).toBeVisible();
  });

  test('frontend: hidden content is hidden', async () => {
    await expect(page.locator('#hidden-content')).toBeHidden();
  });

  test('frontend: context label renders', async () => {
    await expect(page.locator('#context-label')).toHaveText('Context value');
  });
});
