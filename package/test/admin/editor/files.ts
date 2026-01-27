import { Page, test } from '@playwright/test';
import {
  clickEditorToolbar,
  count,
  pEditor,
  searchForBlock,
} from '../../../playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pEditor(browser);
});

test.afterAll(async () => {
  await clickEditorToolbar(page, 'files', false);
  await page.close();
});

test.describe('files', () => {
  test('open first level', async () => {
    await page.click('#instance-plugins-blockstudio-package-test-blocks');
    await count(
      page,
      '#folder-plugins-blockstudio-package-test-blocks-components',
      1
    );
  });

  test('open second level', async () => {
    await page.click(
      '#folder-plugins-blockstudio-package-test-blocks-components'
    );
    await count(
      page,
      '#folder-plugins-blockstudio-package-test-blocks-components-innerblocks',
      1
    );
  });

  test('open third level', async () => {
    await page.click(
      '#folder-plugins-blockstudio-package-test-blocks-components-innerblocks'
    );
    await count(page, '#tree-plugins-blockstudio-package-test-blocks li', 24);
  });

  test.describe('search', () => {
    test("for 'twig'", async () => {
      await page.fill('[placeholder="Search"]', 'twig');
      await count(page, '#blockstudio-table li', 226);
    });

    test("for 'folder'", async () => {
      await page.fill('[placeholder="Search"]', 'folder');
      await count(page, '#tree-plugins-blockstudio-package-test-blocks li', 1);
    });

    test("for '_components'", async () => {
      await page.fill('[placeholder="Search"]', '_components');
      await count(page, '#tree-plugins-blockstudio-package-test-blocks li', 0);
    });

    test("for ''", async () => {
      await page.fill('[placeholder="Search"]', '');
      await count(page, '#tree-plugins-blockstudio-package-test-blocks li', 24);
    });

    test('click into block and open files', async () => {
      await searchForBlock(
        page,
        'single',
        '#file-plugins-blockstudio-package-test-blocks-single-native-index-php'
      );
      await clickEditorToolbar(page, 'files', true);
    });

    test.describe('search inside editor', () => {
      test("for 'twig'", async () => {
        await page.fill('.components-text-control__input', 'twig');
        await count(page, '#blockstudio-table li', 226);
      });

      test("for ''", async () => {
        await page.fill('.components-text-control__input', '');
        await count(
          page,
          '#tree-plugins-blockstudio-package-test-blocks li',
          24
        );
      });

      test('click on element/code', async () => {
        await page.fill('.components-text-control__input', 'element');
        await page.click(
          '#block-plugins-blockstudio-includes-library-blockstudio-element-code-index-php'
        );
        await count(page, '#blockstudio-editor-tab-block-json', 1);
        await count(page, '#blockstudio-editor-tab-index-php', 1);
        await count(page, '#blockstudio-editor-tab-script-inline-js', 1);
        await count(page, '#blockstudio-editor-tab-style-inline-scss', 1);
      });

      test('click on element/icon', async () => {
        await page.fill('.components-text-control__input', 'element');
        await page.click(
          '#block-plugins-blockstudio-includes-library-blockstudio-element-icon-index-php'
        );
        await count(page, '#blockstudio-editor-tab-block-json', 1);
        await count(page, '#blockstudio-editor-tab-index-php', 1);
        await count(page, '#blockstudio-editor-tab-style-inline-scss', 1);
      });
    });
  });
});
