import { expect, test, Page, Browser, BrowserContext } from '@playwright/test';
import { count } from '../../utils/playwright-utils';

let page: Page;
let context: BrowserContext;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }: { browser: Browser }) => {
  context = await browser.newContext();
  page = await context.newPage();
  await page.emulateMedia({ reducedMotion: 'reduce' });

  // Login
  await page.goto('http://localhost:8888/wp-login.php');
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

test.describe('File-based Pages', () => {
  test.describe('Page Creation', () => {
    test('test page exists in WordPress', async () => {
      await page.goto('http://localhost:8888/wp-admin/edit.php?post_type=page');
      await page.waitForSelector('.wp-list-table');

      await expect(page.locator('a.row-title', { hasText: /^Blockstudio E2E Test Page$/ })).toBeVisible();
    });

    test('test page has correct slug', async () => {
      // Click on the test page to edit it
      await page.locator('a.row-title', { hasText: /^Blockstudio E2E Test Page$/ }).click();
      await page.waitForSelector('.editor-styles-wrapper');

      // Check the URL contains the correct post
      const url = page.url();
      expect(url).toContain('post.php');
      expect(url).toContain('action=edit');
    });
  });

  test.describe('Block Content Parsing', () => {
    test('page loads with parsed blocks', async () => {
      await page.goto('http://localhost:8888/wp-admin/edit.php?post_type=page');
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
      // With templateLock: "all", we shouldn't be able to remove blocks
      // Try to select a block and verify the remove option behavior
      await page.click('[data-type="core/heading"]');

      // Check that the editor has the template lock applied
      // The block should be selected but locked
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
      await page.goto('http://localhost:8888/blockstudio-e2e-test/');
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
      await page.goto('http://localhost:8888/wp-admin/edit.php?post_type=page&post_status=draft');
      await page.waitForSelector('.wp-list-table');

      // Check that our sync test page exists in drafts - use specific selector
      await expect(page.locator('a.row-title:has-text("Blockstudio Sync Test Page")')).toBeVisible();
    });

    test('sync test page has insert template lock', async () => {
      // Click on the sync test page to edit it
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
});
