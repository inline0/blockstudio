import { test } from '@playwright/test';
import { count, pBlocks } from '../../playwright-utils';

test.describe.configure({ mode: 'serial' });

test.describe('preview', () => {
  let page: any;

  test.beforeAll(async ({ browser }) => {
    page = await pBlocks(browser);
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('open block inserter', async () => {
    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);
  });

  test('native element', async () => {
    await page.fill('[placeholder="Search"]', 'native block');
    await page.hover('.editor-block-list-item-blockstudio-native');
    const iframeElement = await page.waitForSelector(
      '.components-popover__fallback-container iframe'
    );
    const frame = await iframeElement.contentFrame();
    await count(frame, '.blockstudio-test__block', 1);
  });

  test('code element', async () => {
    await page.fill('[placeholder="Search"]', 'code');
    await page.hover('.editor-block-list-item-blockstudio-element-code');
    const iframeElement = await page.waitForSelector(
      '.components-popover__fallback-container iframe'
    );
    const frame = await iframeElement.contentFrame();
    await count(frame, '.blockstudio-element__preview', 1);
  });

  test('InnerBlocks', async () => {
    await page.fill('[placeholder="Search"]', 'innerblocks');
    await page.hover(
      '.editor-block-list-item-blockstudio-component-innerblocks-default'
    );
    const iframeElement = await page.waitForSelector(
      '.components-popover__fallback-container iframe'
    );
    const frame = await iframeElement.contentFrame();
    await count(frame, '[data-type="core/paragraph"]', 1);
  });
});
