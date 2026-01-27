import { Page, test } from '@playwright/test';
import {
  checkStyle,
  clickEditorToolbar,
  pEditor,
  searchForBlock,
} from '../../../playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pEditor(browser);
  await searchForBlock(
    page,
    'override',
    '#block-plugins-blockstudio-package-test-blocks-types-override-index-twig'
  );
  await page.locator('.mtk9').first().waitFor({ state: 'visible' });
});

test.afterAll(async () => {
  await clickEditorToolbar(page, 'files', false);
  await page.close();
});

test.describe('overrides', () => {
  test('original', async () => {
    await clickEditorToolbar(page, 'files', true);
    await clickEditorToolbar(page, 'preview', true);
    await page.waitForSelector('iframe');
    const frame = page.frames()[1];
    await frame.waitForSelector('.blockstudio-element-gallery');
    await checkStyle(
      frame,
      '.blockstudio-element-gallery',
      'gridAutoRows',
      '20px'
    );
    await checkStyle(frame, '.blockstudio-element-gallery', 'margin', '0px');
  });

  test('override', async () => {
    await page.click('#blockstudio-editor-toolbar-exit');
    await searchForBlock(
      page,
      'gallery',
      '#block-plugins-blockstudio-package-test-blocks-overrides-gallery-index-twig'
    );
    await page.waitForSelector('iframe');
    const frame = page.frames()[1];
    await checkStyle(
      frame,
      '.blockstudio-element-gallery',
      'gridAutoRows',
      '30px'
    );
    await checkStyle(frame, '.blockstudio-element-gallery', 'margin', '12px');
  });
});
