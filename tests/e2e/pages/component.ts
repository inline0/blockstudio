import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
});

test.afterAll(async () => {
  await page.close();
});

const findPostId = async (): Promise<number> => {
  await page.goto('http://localhost:8888/blockstudio-component-test/');
  const id = await page.evaluate(() => {
    const match = document.body.className.match(/page-id-(\d+)/);
    return match ? Number(match[1]) : 0;
  });
  expect(id).toBeGreaterThan(0);
  return id;
};

test.describe('Component pages', () => {
  test('frontend renders the component around the slot', async () => {
    await page.goto('http://localhost:8888/blockstudio-component-test/');
    const section = page.locator('section.component-page');

    await expect(section).toHaveAttribute(
      'data-page',
      'blockstudio-component-test',
    );
    await expect(section).toHaveAttribute('data-context', 'frontend');
    await expect(
      section.getByRole('heading', { name: 'Component Page Heading' }),
    ).toBeVisible();
    await expect(section.locator('.component-page-slot p')).toHaveText(
      'Slot paragraph from the page source.',
    );
  });

  test('editor shows a preview and the locked slot without a missing block notice', async () => {
    const postId = await findPostId();
    await page.goto(
      `http://localhost:8888/wp-admin/post.php?post=${postId}&action=edit`,
    );
    const canvas = await getEditorCanvas(page);
    const block = canvas.locator('[data-type="blockstudio/page"]');

    await expect(block).toBeVisible({ timeout: 30000 });
    await expect(
      block.locator('.blockstudio-page__preview section.component-page'),
    ).toBeVisible({ timeout: 30000 });
    await expect(block.locator('.blockstudio-page__preview')).toHaveText(
      /Component Page Heading/,
    );
    await expect(
      block.locator('[data-type="core/paragraph"]', {
        hasText: 'Slot paragraph from the page source.',
      }),
    ).toBeVisible();
    await expect(
      canvas.getByText("Your site doesn't include support for"),
    ).toHaveCount(0);

    const locked = await page.evaluate(() => {
      const editor = (window as any).wp.data.select('core/block-editor');
      const [root] = editor.getBlocks();
      return {
        name: root?.name,
        lock: editor.getTemplateLock(root?.clientId),
      };
    });
    expect(locked.name).toBe('blockstudio/page');
    expect(locked.lock).toBe('all');
  });
});
