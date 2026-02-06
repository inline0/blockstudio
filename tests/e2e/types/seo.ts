import { execSync } from 'child_process';
import { Page, expect, test } from '@playwright/test';
import {
  addBlock,
  delay,
  getEditorCanvas,
  getSharedPage,
  openBlockInserter,
  openSidebar,
  resetPageState,
} from '../utils/playwright-utils';

const wpCli = (command: string) =>
  execSync(`npx wp-env run cli -- ${command}`, { encoding: 'utf-8', timeout: 30000 });

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  // Ensure correct plugin state regardless of previous test runs
  wpCli('wp plugin activate wordpress-seo');
  wpCli('wp plugin deactivate seo-by-rank-math');

  page = await getSharedPage(browser);
  await resetPageState(page);
});

test.afterAll(async () => {
  // Always restore Yoast as the active SEO plugin
  try {
    wpCli('wp plugin deactivate seo-by-rank-math');
    wpCli('wp plugin activate wordpress-seo');
  } catch {
    // Best effort cleanup
  }
});

// Yoast SEO

test.describe('seo - yoast', () => {
  test('yoast plugin is registered', async () => {
    await openBlockInserter(page);
    await addBlock(page, 'type-text');
    const canvas = await getEditorCanvas(page);
    await canvas.click('[data-type="blockstudio/type-text"]');
    await openSidebar(page);
    await delay(2000);

    const plugins = await page.evaluate(() => {
      const app = (window as any).YoastSEO?.app;
      return app?.pluggable?.plugins
        ? Object.keys(app.pluggable.plugins)
        : [];
    });

    expect(plugins).toContain('blockstudioSeoIntegration');
  });

  test('yoast content modification includes blockstudio text', async () => {
    const uniqueText = 'blockstudioseotest' + Date.now();
    await page.fill('.blockstudio-fields__field--text input', uniqueText);

    // Wait for debounce (1s) + processing
    await delay(3000);

    const result = await page.evaluate((searchText) => {
      const app = (window as any).YoastSEO?.app;
      if (!app?.pluggable?.modifications?.content) return false;

      let content = '';
      for (const mod of app.pluggable.modifications.content) {
        content = mod.callable(content);
      }
      return content.includes(searchText);
    }, uniqueText);

    expect(result).toBe(true);
  });

  test('disabled fields are excluded from seo content', async () => {
    const seoContent = await page.evaluate(() => {
      const wp = (window as any).wp;
      const blocks = wp.data.select('core/block-editor').getBlocks();
      const bsBlock = blocks.find((b: any) => b.name.startsWith('blockstudio/'));
      if (!bsBlock) return null;
      return {
        disabled: bsBlock.attributes?.blockstudio?.disabled || [],
      };
    });

    expect(seoContent).not.toBeNull();
    expect(Array.isArray(seoContent!.disabled)).toBe(true);
  });
});

// Rank Math

test.describe('seo - rank math', () => {
  test('switch to rank math', async () => {
    wpCli('wp plugin deactivate wordpress-seo');
    wpCli('wp plugin activate seo-by-rank-math');

    await page.goto('http://localhost:8888/wp-admin/post.php?post=1483&action=edit');
    const canvas = await getEditorCanvas(page);
    await canvas.waitForSelector('.editor-styles-wrapper', { timeout: 30000 });
  });

  test('rank math filter is registered', async () => {
    await delay(2000);

    const hasFilter = await page.evaluate(() => {
      const win = window as any;
      return !!win.wp?.hooks?.hasFilter?.('rank_math_content', 'blockstudio/seo');
    });

    expect(hasFilter).toBe(true);
  });

  test('rank math content filter includes blockstudio text', async () => {
    await resetPageState(page);
    await openBlockInserter(page);
    await addBlock(page, 'type-text');
    const canvas = await getEditorCanvas(page);
    await canvas.click('[data-type="blockstudio/type-text"]');
    await openSidebar(page);

    const uniqueText = 'rankmathseotest' + Date.now();
    await page.fill('.blockstudio-fields__field--text input', uniqueText);

    // Wait for debounce (1s) + processing
    await delay(3000);

    const result = await page.evaluate((searchText) => {
      const wp = (window as any).wp;
      const filtered = wp?.hooks?.applyFilters?.('rank_math_content', '');
      return typeof filtered === 'string' && filtered.includes(searchText);
    }, uniqueText);

    expect(result).toBe(true);
  });

  test('restore yoast', async () => {
    wpCli('wp plugin deactivate seo-by-rank-math');
    wpCli('wp plugin activate wordpress-seo');
  });
});
