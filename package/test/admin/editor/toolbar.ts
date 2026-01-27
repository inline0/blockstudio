import { expect, Page, test } from '@playwright/test';
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
  await searchForBlock(
    page,
    'native',
    '#block-plugins-blockstudio-package-test-blocks-single-native-index-php'
  );
  await page.locator('.mtk10').first().waitFor({ state: 'visible' });
});

test.afterAll(async () => {
  await page.close();
});

test.describe('toolbar', () => {
  test('toggle list view', async () => {
    await clickEditorToolbar(page, 'files', true);
    await clickEditorToolbar(page, 'files', false);
  });

  test('toggle console', async () => {
    await clickEditorToolbar(page, 'console', true);
    await clickEditorToolbar(page, 'console', false);
  });

  test('toggle minimap', async () => {
    await page.click('#blockstudio-editor-toolbar-minimap');
    await page.locator('.minimap').first().waitFor({ state: 'visible' });
    await page.click('#blockstudio-editor-toolbar-minimap');
    await page.locator('.minimap').first().waitFor({ state: 'hidden' });
  });

  test('toggle preview', async () => {
    await clickEditorToolbar(page, 'preview', true);
    await clickEditorToolbar(page, 'preview', false);
  });

  test.describe('theme', async () => {
    test('has 50 options', async () => {
      await page.click('#blockstudio-editor-toolbar-theme');
      await count(
        page,
        '.components-popover .components-menu-group [role="menuitemradio"]',
        50
      );
    });

    test('select Active4D', async () => {
      await page.click('text=Active4D');
      await expect(
        page.locator('.view-line:first-of-type > span > span').first()
      ).toHaveCSS('color', 'rgb(56, 56, 56)');
    });

    test('select Blockstudio', async () => {
      await page
        .locator(
          '.components-popover .components-menu-group [role="menuitemradio"]'
        )
        .first()
        .click();
      await expect(
        page.locator('.view-line:first-of-type > span > span').first()
      ).toHaveCSS('color', 'rgb(128, 128, 128)');
    });
  });

  test.describe('preview sizes', () => {
    const toggleSize = async () => {
      await page.click('#blockstudio-editor-toolbar-preview-size');
    };
    const toggleEdit = async () => {
      await page.click('#blockstudio-editor-toolbar-preview-size-edit');
    };
    const addSize = async (size, taken = false) => {
      await page.click('#blockstudio-editor-toolbar-preview-size-add');
      await count(page, 'text=Create a new size', 1);
      await count(
        page,
        '.components-modal__content .components-button.is-primary[disabled]',
        1
      );
      await page.type('.components-modal__content input[type="number"]', size);
      if (taken) {
        await count(
          page,
          '.components-modal__content .components-button.is-primary[disabled]',
          1
        );
        return await page.click('[aria-label="Close"]');
      }
      await page.click(
        '.components-modal__content .components-button.is-primary'
      );
      await page.click('#blockstudio-editor-toolbar-preview-size');
      await count(
        page,
        '.components-menu-group button:nth-of-type(2)[aria-checked]',
        1
      );
    };
    const deleteSize = async (item) => {
      await toggleSize();
      await toggleSize();
      await toggleEdit();
      await page.click(`text=Delete ${item}`);
      if (item === 1920) {
        await count(page, '.components-menu-group button', 0);
      }
    };
    const isNone = async () => {
      await toggleSize();
      await count(page, '.components-popover__content', 1);
      await count(
        page,
        '.components-menu-group button:first-of-type[aria-checked]',
        1
      );
    };

    test.afterAll(async () => {
      await clickEditorToolbar(page, 'preview', false);
    });

    test('toggle and none selected', async () => {
      await clickEditorToolbar(page, 'preview', true);
      await isNone();
    });

    test('add 640 size', async () => {
      await addSize('640');
    });

    test('add 1280 size', async () => {
      await addSize('1280');
    });

    test('add 1920 size', async () => {
      await addSize('1920');
    });

    test('add 0 size and fail', async () => {
      await toggleSize();
      await toggleSize();
      await addSize('0', true);
    });

    test('add 640 size and fail', async () => {
      await toggleSize();
      await addSize('640', true);
    });

    test('click edit and quit edit', async () => {
      await toggleSize();
      await toggleEdit();
      await toggleEdit();
      await count(
        page,
        '.components-menu-group button:first-of-type[aria-checked]',
        1
      );
    });

    test('click edit and click size button', async () => {
      await toggleSize();
      await toggleSize();
      await toggleEdit();
      await toggleSize();
      await toggleSize();
      await count(
        page,
        '.components-menu-group button:first-of-type[aria-checked]',
        1
      );
    });

    [640, 1280, 1920].forEach(async (item) => {
      test(`delete size ${item}`, async () => {
        await deleteSize(item);
      });
    });
  });

  test('exit editor', async () => {
    await page.click('#blockstudio-editor-toolbar-exit');
    await count(page, '#blockstudio-editor', 1);
  });
});
