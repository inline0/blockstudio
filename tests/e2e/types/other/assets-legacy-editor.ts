import { expect, Page, test } from '@playwright/test';
import { login } from '../../utils/playwright-utils';

test.describe('assets with legacy API blocks', () => {
  test('loads Blockstudio block CSS in the active editor canvas', async ({
    page,
  }: {
    page: Page;
  }) => {
    await login(page);

    await page.request.post(
      '/wp-json/blockstudio-test/v1/e2e/legacy-api-block',
      {
        data: { enabled: true },
      },
    );

    try {
      await page.goto('/wp-admin/post.php?post=3950&action=edit');
      const editorIframe = page.locator('iframe[name="editor-canvas"]');
      const parentCanvas = page.locator('.is-root-container');

      await expect
        .poll(
          async () => {
            const frame = page.frame('editor-canvas');

            if (
              frame &&
              (await frame.locator('.is-root-container').isVisible())
            ) {
              return 'iframe';
            }

            if (await parentCanvas.isVisible()) {
              return 'parent';
            }

            return '';
          },
          { timeout: 30000 },
        )
        .not.toBe('');

      if (await editorIframe.count()) {
        const canvas = page.frameLocator('iframe[name="editor-canvas"]');

        await expect(canvas.locator('.is-root-container')).toBeVisible();
        await expect(
          canvas.locator('#blockstudio-blockstudio-assets-test-css'),
        ).toHaveCount(1);
        await expect(
          page.locator('#blockstudio-blockstudio-assets-test-css'),
        ).toHaveCount(0);
      } else {
        await expect(parentCanvas).toBeVisible();
        await expect(
          page.locator('#blockstudio-blockstudio-assets-test-css'),
        ).toHaveCount(1);
      }
    } finally {
      await page.request.post(
        '/wp-json/blockstudio-test/v1/e2e/legacy-api-block',
        {
          data: { enabled: false },
        },
      );
    }
  });
});
