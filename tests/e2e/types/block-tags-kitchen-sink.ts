import { test, expect, Page } from '@playwright/test';
import { login } from '../utils/playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
  await page.goto('http://localhost:8888/kitchen-sink-test/', {
    waitUntil: 'domcontentloaded',
  });
});

test.afterAll(async () => {
  await page.close();
});

async function assertSameInnerHTML(
  page: Page,
  bsSelector: string,
  blockSelector: string
) {
  const bsHtml = await page.locator(bsSelector).innerHTML();
  const blockHtml = await page.locator(blockSelector).innerHTML();
  expect(bsHtml.trim()).toBe(blockHtml.trim());
}

test.describe('Block Tags Kitchen Sink', () => {
  test('host block renders', async () => {
    const host = page.locator('.bs-kitchen-sink');
    await expect(host).toBeVisible({ timeout: 10000 });
    await expect(host.locator('.ks-label')).toHaveText('Kitchen Sink');
  });

  // Static blocks: both syntaxes produce identical output
  test('core/paragraph: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-paragraph-bs', '.ks-paragraph-block');
  });

  test('core/heading: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-heading-bs', '.ks-heading-block');
  });

  test('core/list: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-list-bs', '.ks-list-block');
  });

  test('core/quote: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-quote-bs', '.ks-quote-block');
  });

  test('core/pullquote: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-pullquote-bs', '.ks-pullquote-block');
  });

  test('core/code: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-code-bs', '.ks-code-block');
  });

  test('core/preformatted: bs and block identical', async () => {
    await assertSameInnerHTML(
      page,
      '.ks-preformatted-bs',
      '.ks-preformatted-block'
    );
  });

  test('core/verse: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-verse-bs', '.ks-verse-block');
  });

  test('core/image: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-image-bs', '.ks-image-block');
  });

  test('core/separator: bs and block identical', async () => {
    await assertSameInnerHTML(
      page,
      '.ks-separator-bs',
      '.ks-separator-block'
    );
  });

  test('core/spacer: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-spacer-bs', '.ks-spacer-block');
  });

  test('core/audio: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-audio-bs', '.ks-audio-block');
  });

  test('core/video: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-video-bs', '.ks-video-block');
  });

  test('core/embed: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-embed-bs', '.ks-embed-block');
  });

  test('core/table: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-table-bs', '.ks-table-block');
  });

  test('core/group: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-group-bs', '.ks-group-block');
  });

  test('core/columns: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-columns-bs', '.ks-columns-block');
  });

  test('core/buttons: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-buttons-bs', '.ks-buttons-block');
  });

  test('core/details: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-details-bs', '.ks-details-block');
  });

  test('core/social-links: bs and block identical', async () => {
    await assertSameInnerHTML(
      page,
      '.ks-social-links-bs',
      '.ks-social-links-block'
    );
  });

  test('core/cover: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-cover-bs', '.ks-cover-block');
  });

  test('core/more: bs and block identical', async () => {
    await assertSameInnerHTML(page, '.ks-more-bs', '.ks-more-block');
  });

  // Dynamic block renders via WordPress callback
  test('core/search: dynamic block renders', async () => {
    const bsSearch = page.locator('.ks-dynamic-bs');
    const blockSearch = page.locator('.ks-dynamic-block');
    await expect(bsSearch).not.toBeEmpty();
    await expect(blockSearch).not.toBeEmpty();
  });

  // Blockstudio block
  test('Blockstudio block renders in template', async () => {
    const custom = page.locator('.ks-custom-block .bs-block-tags');
    await expect(custom).toBeVisible();
    await expect(custom.locator('.bt-title')).toHaveText('BS Block');
    await expect(custom.locator('.bt-count')).toHaveText('7');
  });

  // Mixed nesting
  test('mixed nesting: core and bs blocks together', async () => {
    const group = page.locator('.ks-mixed .wp-block-group');
    await expect(group).toBeVisible();
    await expect(group.locator('p')).toHaveText('Mixed content');
    const bs = group.locator('.bs-block-tags');
    await expect(bs).toBeVisible();
    await expect(bs.locator('.bt-title')).toHaveText('Mixed BS');
  });

  // Passthrough
  test('passthrough: both syntaxes apply attributes', async () => {
    const bsP = page.locator('.ks-passthrough-bs p');
    const blockP = page.locator('.ks-passthrough-block p');
    await expect(bsP).toHaveClass(/pt-class/);
    await expect(blockP).toHaveClass(/pt-class/);
    await expect(bsP).toHaveAttribute('data-testid', 'pt-bs');
    await expect(blockP).toHaveAttribute('data-testid', 'pt-block');
  });
});
