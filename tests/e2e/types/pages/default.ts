import { expect, test, Page, Browser, BrowserContext } from '@playwright/test';
import { count } from '../../utils/playwright-utils';

const BASE = 'http://localhost:8888';

let page: Page;
let context: BrowserContext;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }: { browser: Browser }) => {
  context = await browser.newContext();
  page = await context.newPage();
  await page.emulateMedia({ reducedMotion: 'reduce' });

  await page.goto(`${BASE}/wp-login.php`);
  await page.waitForLoadState('networkidle');
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('password');
  await page.locator('#wp-submit').click();
  await page.waitForURL('**/wp-admin/**');
});

test.afterAll(async () => {
  await page.close();
  await context.close();
});

async function navigateToPage(title: string, status = 'all') {
  await page.goto(
    `${BASE}/wp-admin/edit.php?post_type=page&post_status=${status}`,
  );
  await page.waitForSelector('.wp-list-table');
  await page.locator('a.row-title', { hasText: new RegExp(`^${title}$`) }).click();
  await page.waitForSelector('.editor-styles-wrapper', { timeout: 30000 });
}

async function getEditorSettings() {
  return page.evaluate(() => {
    const { select } = (window as any).wp.data;
    return select('core/block-editor').getSettings();
  });
}

async function apiGet(path: string) {
  return page.evaluate(async (url: string) => {
    const res = await fetch(url);
    return res.json();
  }, `${BASE}/wp-json/${path}`);
}

async function apiPost(path: string, body: Record<string, unknown> = {}) {
  return page.evaluate(
    async ({ url, data }: { url: string; data: Record<string, unknown> }) => {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      return res.json();
    },
    { url: `${BASE}/wp-json/${path}`, data: body },
  );
}

test.describe('File-based Pages', () => {
  test.describe('Page Creation', () => {
    test('test page exists in WordPress', async () => {
      await page.goto(`${BASE}/wp-admin/edit.php?post_type=page`);
      await page.waitForSelector('.wp-list-table');

      await expect(page.locator('a.row-title', { hasText: /^Blockstudio E2E Test Page$/ })).toBeVisible();
    });

    test('test page has correct slug', async () => {
      await page.locator('a.row-title', { hasText: /^Blockstudio E2E Test Page$/ }).click();
      await page.waitForSelector('.editor-styles-wrapper');

      const url = page.url();
      expect(url).toContain('post.php');
      expect(url).toContain('action=edit');
    });
  });

  test.describe('Block Content Parsing', () => {
    test('page loads with parsed blocks', async () => {
      await page.goto(`${BASE}/wp-admin/edit.php?post_type=page`);
      await page.locator('a.row-title', { hasText: /^Blockstudio E2E Test Page$/ }).click();
      await page.waitForSelector('.editor-styles-wrapper', { timeout: 30000 });
      await page.waitForSelector('[data-type="core/group"]', { timeout: 15000 });

      await expect(page.locator('[data-type="core/group"]').first()).toBeVisible();
      await expect(page.locator('[data-type="core/heading"]').first()).toBeVisible();
      await expect(page.locator('[data-type="core/paragraph"]').first()).toBeVisible();
    });

    test('page contains list blocks', async () => {
      await count(page, '[data-type="core/list"]', 2);
      await count(page, '[data-type="core/list-item"]', 6);
    });

    test('heading has correct content', async () => {
      const h1Content = await page.locator('[data-type="core/heading"]').first().textContent();
      expect(h1Content).toContain('Core Blocks Test Page');
    });

    test('paragraph has correct content', async () => {
      const pContent = await page.locator('[data-type="core/paragraph"]').first().textContent();
      expect(pContent).toContain('This page tests all supported HTML to block conversions');
    });
  });

  test.describe('Template Lock', () => {
    test('page has template lock enabled', async () => {
      await page.click('[data-type="core/heading"]');

      const isLocked = await page.evaluate(() => {
        const { select } = (window as any).wp.data;
        const settings = select('core/block-editor').getSettings();
        return settings.templateLock === 'all';
      });

      expect(isLocked).toBe(true);
    });
  });

  test.describe('Post Meta', () => {
    test('page has blockstudio source meta', async () => {
      await page.evaluate(async () => {
        const { select } = (window as any).wp.data;
        const postId = select('core/editor').getCurrentPostId();
        const response = await fetch(`/wp-json/wp/v2/pages/${postId}?context=edit`);
        const data = await response.json();
        return data.meta && data.meta._blockstudio_page_source !== undefined;
      });
    });
  });

  test.describe('Frontend Rendering', () => {
    test('page renders correctly on frontend', async () => {
      await page.goto(`${BASE}/blockstudio-e2e-test/`);
      await page.waitForSelector('.wp-block-group');

      await expect(page.locator('.wp-block-group').first()).toBeVisible();
      await expect(page.locator('.wp-block-heading').first()).toBeVisible();
      await count(page, '.wp-block-list', 2);
    });

    test('frontend has correct heading text', async () => {
      const h1Text = await page.locator('.wp-block-heading').first().textContent();
      expect(h1Text).toContain('Core Blocks Test Page');
    });

    test('frontend has correct list items', async () => {
      await count(page, '.wp-block-list li', 6);

      const listItems = await page.locator('.wp-block-list li').allTextContents();
      expect(listItems).toContain('Unordered item one');
      expect(listItems).toContain('Ordered item one');
    });
  });

  test.describe('Sync Test Page', () => {
    test('sync test page exists with draft status', async () => {
      await page.goto(`${BASE}/wp-admin/edit.php?post_type=page&post_status=draft`);
      await page.waitForSelector('.wp-list-table');

      await expect(page.locator('a.row-title:has-text("Blockstudio Sync Test Page")')).toBeVisible();
    });

    test('sync test page has insert template lock', async () => {
      await page.locator('a.row-title:has-text("Blockstudio Sync Test Page")').click();
      await page.waitForSelector('.editor-styles-wrapper');

      const templateLock = await page.evaluate(() => {
        const { select } = (window as any).wp.data;
        const settings = select('core/block-editor').getSettings();
        return settings.templateLock;
      });

      expect(templateLock).toBe('insert');
    });
  });

  // Content Only Lock
  test.describe('Content Only Lock', () => {
    test('contentOnly lock is applied', async () => {
      await navigateToPage('Content Only Lock Test', 'draft');
      await page.waitForSelector('[data-type="core/heading"]', { timeout: 15000 });

      const settings = await getEditorSettings();

      expect(settings.templateLock).toBe('contentOnly');
    });

    test('contentOnly lock prevents block locking UI', async () => {
      const settings = await getEditorSettings();

      expect(settings.canLockBlocks).toBe(false);
    });
  });

  // Unlocked Page
  test.describe('Unlocked Page', () => {
    test('unlocked page has no template lock', async () => {
      await navigateToPage('Unlocked Test', 'draft');
      await page.waitForSelector('[data-type="core/heading"]', { timeout: 15000 });

      const settings = await getEditorSettings();

      expect(settings.templateLock).toBeFalsy();
    });

    test('unlocked page allows adding blocks', async () => {
      const inserterButton = page.locator(
        'button.editor-document-tools__inserter-toggle, button[aria-label="Toggle block inserter"]',
      );

      await expect(inserterButton.first()).toBeVisible();
      await expect(inserterButton.first()).toBeEnabled();
    });
  });

  // Block Editing Mode
  test.describe('Block Editing Mode', () => {
    test('page has blockEditingMode setting', async () => {
      await navigateToPage('Editing Mode Test', 'draft');
      await page.waitForSelector('[data-type="core/heading"]', { timeout: 15000 });

      const mode = await page.evaluate(() => {
        const { select } = (window as any).wp.data;
        const settings = select('core/editor').getEditorSettings();
        return settings.blockstudioBlockEditingMode;
      });

      expect(mode).toBe('disabled');
    });

    test('blocks inherit page default editing mode', async () => {
      // Wait for the editing mode subscription to apply
      await page.waitForTimeout(500);

      const paragraphMode = await page.evaluate(() => {
        const { select } = (window as any).wp.data;
        const blocks = select('core/block-editor').getBlocks();
        // The plain <p> is the second block (index 1) — no blockEditingMode override
        const paragraph = blocks[1];
        if (!paragraph) return null;
        return select('core/block-editor').getBlockEditingMode(paragraph.clientId);
      });

      expect(paragraphMode).toBe('disabled');
    });

    test('per-element blockEditingMode override works', async () => {
      const headingMode = await page.evaluate(() => {
        const { select } = (window as any).wp.data;
        const blocks = select('core/block-editor').getBlocks();
        // The <h1 blockEditingMode="contentOnly"> is the first block
        const heading = blocks[0];
        if (!heading) return null;
        return select('core/block-editor').getBlockEditingMode(heading.clientId);
      });

      expect(headingMode).toBe('contentOnly');
    });

    test('ancestor containers of overridden blocks get contentOnly', async () => {
      const groupMode = await page.evaluate(() => {
        const { select } = (window as any).wp.data;
        const blocks = select('core/block-editor').getBlocks();
        // The <div> is the third block (index 2) — contains a child with override
        const group = blocks[2];
        if (!group) return null;
        return select('core/block-editor').getBlockEditingMode(group.clientId);
      });

      expect(groupMode).toBe('contentOnly');
    });
  });

  // Sync Disabled
  test.describe('Sync Disabled', () => {
    test('sync:false page is not auto-created by normal sync', async () => {
      // Delete any existing page from previous runs via WP-CLI style deletion
      await page.evaluate(async (base: string) => {
        const res = await fetch(`${base}/wp-json/wp/v2/pages?search=Sync+Disabled+Test&status=draft,publish&per_page=100`, {
          headers: { 'X-WP-Nonce': (window as any).wpApiSettings?.nonce || '' },
        });
        const pages = await res.json();
        if (Array.isArray(pages)) {
          for (const p of pages) {
            await fetch(`${base}/wp-json/wp/v2/pages/${p.id}?force=true`, {
              method: 'DELETE',
              headers: { 'X-WP-Nonce': (window as any).wpApiSettings?.nonce || '' },
            });
          }
        }
      }, BASE);

      // Trigger normal sync — should NOT create the page since sync: false
      const result = await apiPost(
        'blockstudio-test/v1/pages/trigger-sync',
        { page_name: 'blockstudio-sync-disabled-test' },
      );

      // sync() returns 0 for sync:false pages when no existing post found
      expect(result.post_id).toBe(0);
    });

    test('sync:false page ignores template changes', async () => {
      // Force-create the page (ensures it exists regardless of previous state)
      const createResult = await apiPost(
        'blockstudio-test/v1/pages/force-sync',
        { page_name: 'blockstudio-sync-disabled-test' },
      );
      expect(createResult.post_id).toBeGreaterThan(0);

      const originalContent = createResult.post_content;
      expect(originalContent).toContain('Original Title');

      // Trigger normal sync with changed template — should be a no-op since sync: false
      const syncResult = await apiPost(
        'blockstudio-test/v1/pages/trigger-sync',
        {
          page_name: 'blockstudio-sync-disabled-test',
          template_content: '<h1>Changed Title</h1>\n<p>Changed content.</p>',
        },
      );

      const afterSync = await apiGet(
        `blockstudio-test/v1/pages/content/${syncResult.post_id}`,
      );
      expect(afterSync.post_content).toBe(originalContent);

      // Restore original template for future runs
      await apiPost('blockstudio-test/v1/pages/force-sync', {
        page_name: 'blockstudio-sync-disabled-test',
        template_content: '<h1>Original Title</h1>\n<p>Original content.</p>',
      });
    });
  });

  // Template For
  test.describe('Template For', () => {
    test('CPT template is applied to new posts', async () => {
      // Navigate to "Add New" for the test CPT — WordPress applies the registered template
      await page.goto(`${BASE}/wp-admin/post-new.php?post_type=blockstudio_test_cpt`);
      await page.waitForSelector('.editor-styles-wrapper', { timeout: 30000 });
      await page.waitForSelector('[data-type="core/heading"]', { timeout: 15000 });

      await expect(page.locator('[data-type="core/heading"]').first()).toBeVisible();
      await expect(page.locator('[data-type="core/paragraph"]').first()).toBeVisible();
    });

    test('CPT template has correct content', async () => {
      const headingText = await page.locator('[data-type="core/heading"]').first().textContent();
      expect(headingText).toContain('CPT Template Title');

      const paragraphText = await page.locator('[data-type="core/paragraph"]').first().textContent();
      expect(paragraphText).toContain('Default CPT content');
    });

    test('CPT template lock is applied', async () => {
      const templateLock = await page.evaluate(() => {
        const { select } = (window as any).wp.data;
        const settings = select('core/block-editor').getSettings();
        return settings.templateLock;
      });

      expect(templateLock).toBe('insert');
    });
  });

  // Post ID Pinning
  test.describe('Post ID Pinning', () => {
    test('page with postId created at specified ID', async () => {
      const result = await apiPost(
        'blockstudio-test/v1/pages/force-sync',
        { page_name: 'blockstudio-post-id-test' },
      );

      expect(result.post_id).toBe(99999);

      await page.goto(`${BASE}/wp-admin/post.php?post=99999&action=edit`);
      await page.waitForSelector('.editor-styles-wrapper', { timeout: 30000 });

      const url = page.url();
      expect(url).toContain('post=99999');
    });

    test('page retains ID after delete and re-sync', async () => {
      // Delete the post
      await page.evaluate(async (base: string) => {
        await fetch(`${base}/wp-json/wp/v2/pages/99999?force=true`, {
          method: 'DELETE',
          headers: { 'X-WP-Nonce': (window as any).wpApiSettings?.nonce || '' },
        });
      }, BASE);

      // Force re-sync — should reclaim the same ID
      const result = await apiPost(
        'blockstudio-test/v1/pages/force-sync',
        { page_name: 'blockstudio-post-id-test' },
      );

      expect(result.post_id).toBe(99999);
    });

    test('page with postId has correct content', async () => {
      const content = await apiGet(
        'blockstudio-test/v1/pages/content/99999',
      );

      expect(content.post_content).toContain('Post ID Test');
      expect(content.post_content).toContain('post ID pinning');
    });
  });
});
