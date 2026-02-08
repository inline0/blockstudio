import { expect, Page, test } from '@playwright/test';
import {
  addBlock,
  count,
  delay,
  getEditorCanvas,
  getSharedPage,
  openBlockInserter,
  openSidebar,
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

test.describe('interactivity', () => {
  test('add block', async () => {
    await openBlockInserter(page);
    await addBlock(page, 'type-interactivity');
    const canvas = await getEditorCanvas(page);
    await count(canvas, '.is-root-container > .wp-block', 1);
  });

  test('block renders in editor', async () => {
    const canvas = await getEditorCanvas(page);
    await text(canvas, 'blockstudio-type-interactivity');
  });

  test('save and reload editor', async () => {
    await save(page);
    await page.goto('http://localhost:8888/wp-admin/post.php?post=1483&action=edit', { waitUntil: 'domcontentloaded' });
    const canvas = await getEditorCanvas(page);
    await canvas.locator('[data-wp-interactive="blockstudioTest"]').waitFor({ state: 'visible', timeout: 30000 });
  });

  test('editor: hidden content is initially hidden', async () => {
    const canvas = await getEditorCanvas(page);
    const content = canvas.locator('#content');
    await expect(content).toBeHidden({ timeout: 10000 });
  });

  test('editor: click toggle reveals hidden content', async () => {
    const canvas = await getEditorCanvas(page);
    await canvas.locator('[data-wp-on--click="actions.toggle"]').click();
    const content = canvas.locator('#content');
    await expect(content).toBeVisible();
    await expect(content).toHaveText('Hello from an interactive block!');
  });

  test('editor: click toggle again hides content', async () => {
    const canvas = await getEditorCanvas(page);
    await canvas.locator('[data-wp-on--click="actions.toggle"]').click();
    const content = canvas.locator('#content');
    await expect(content).toBeHidden();
  });

  test('editor: change attribute triggers re-render', async () => {
    const canvas = await getEditorCanvas(page);
    await canvas.locator('[data-wp-interactive="blockstudioTest"]').click();
    await openSidebar(page);
    const input = page.locator('.blockstudio-fields input[type="text"]').first();
    await input.fill('Changed value');
    await delay(2000);
    await canvas.locator('[data-wp-interactive="blockstudioTest"]').waitFor({ state: 'visible', timeout: 10000 });
  });

  test('editor: directives work after re-render', async () => {
    const canvas = await getEditorCanvas(page);
    const content = canvas.locator('#content');
    await expect(content).toBeHidden({ timeout: 10000 });
    await canvas.locator('[data-wp-on--click="actions.toggle"]').click();
    await expect(content).toBeVisible();
    await canvas.locator('[data-wp-on--click="actions.toggle"]').click();
    await expect(content).toBeHidden();
  });

  test('visit frontend', async () => {
    await page.goto('http://localhost:8888/?p=1483', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
  });

  test('frontend: block renders with data-wp-interactive', async () => {
    const interactive = page.locator('[data-wp-interactive="blockstudioTest"]');
    await expect(interactive).toBeVisible();
  });

  test('frontend: hidden content is initially hidden', async () => {
    const content = page.locator('#content');
    await expect(content).toBeHidden();
  });

  test('frontend: click toggle reveals hidden content', async () => {
    await page.click('[data-wp-on--click="actions.toggle"]');
    const content = page.locator('#content');
    await expect(content).toBeVisible();
    await expect(content).toHaveText('Hello from an interactive block!');
  });

  test('frontend: click toggle again hides content', async () => {
    await page.click('[data-wp-on--click="actions.toggle"]');
    const content = page.locator('#content');
    await expect(content).toBeHidden();
  });
});
