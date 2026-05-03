import { APIRequestContext, expect, Page, test } from '@playwright/test';
import { login } from '../../utils/playwright-utils';

const toggleLegacyApiBlock = async (
  request: APIRequestContext,
  enabled: boolean,
) => {
  await request.post('/wp-json/blockstudio-test/v1/e2e/legacy-api-block', {
    data: { enabled },
  });
};

test.describe('assets in legacy non-iframed editor', () => {
  test.afterEach(async ({ request }) => {
    await toggleLegacyApiBlock(request, false);
  });

  test('loads Blockstudio block CSS when a legacy API v1 block disables the iframe', async ({
    page,
    request,
  }: {
    page: Page;
    request: APIRequestContext;
  }) => {
    await toggleLegacyApiBlock(request, true);
    await login(page);

    await page.goto('/wp-admin/post.php?post=1483&action=edit');
    await expect(page.locator('.is-root-container')).toBeVisible({
      timeout: 30000,
    });
    await expect(page.locator('iframe[name="editor-canvas"]')).toHaveCount(0);
    await expect
      .poll(
        () => page.locator('#blockstudio-blockstudio-assets-test-css').count(),
        { timeout: 30000 },
      )
      .toBeGreaterThan(0);
  });
});
