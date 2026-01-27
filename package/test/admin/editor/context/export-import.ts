import { Page, expect, test } from '@playwright/test';
import {
  clickEditorToolbar,
  count,
  testContext,
} from '../../../../playwright-utils';

test.describe.configure({ mode: 'serial' });

testContext('export & import', () => [
  {
    groupName: 'Export',
    testCases: [
      {
        description: 'file',
        testFunction: async (page: Page) => {
          await page.keyboard.down('Control');
          await page
            .locator(
              '#block-plugins-blockstudio-includes-library-blockstudio-element-code-block-json'
            )
            .click();
          await page.keyboard.up('Control');
          await page.locator('#blockstudio').locator('text=Export').click();
          await expect(
            page.locator('#blockstudio').locator('text=Export')
          ).toHaveCount(0);
        },
      },
      {
        description: 'folder',
        testFunction: async (page: Page) => {
          await page.keyboard.down('Control');
          await page
            .locator(
              '#folder-plugins-blockstudio-includes-library-blockstudio-element-code'
            )
            .click();
          await page.keyboard.up('Control');
          await page.locator('#blockstudio').locator('text=Export').click();
          await expect(
            page.locator('#blockstudio').locator('text=Export')
          ).toHaveCount(0);
        },
      },
    ],
  },
  {
    groupName: 'Import',
    testCases: [
      {
        description: 'open modal',
        testFunction: async (page: Page) => {
          await page.keyboard.down('Control');
          await page
            .locator(
              '#folder-plugins-blockstudio-includes-library-blockstudio-element'
            )
            .click();
          await page.keyboard.up('Control');
          await page.locator('#blockstudio').locator('text=Import').click();
        },
      },
      {
        description: 'folder name exists',
        testFunction: async (page: Page) => {
          await count(page, '.components-modal__screen-overlay', 1);
          await page.type('.components-text-control__input', `code`);
          await count(page, 'text=Folder name already exists', 1);
          await page.type('.components-text-control__input', `-tester`);
          await count(page, 'text=Folder name already exists', 0);
        },
      },
      {
        description: 'successful',
        testFunction: async (page: Page) => {
          const fileChooserPromise = page.waitForEvent('filechooser');
          await page.getByText('Select .zip').click();
          const fileChooser = await fileChooserPromise;
          await fileChooser.setFiles('./test/import-code-new.zip');
          await page.click('.components-button.is-primary');
          await count(
            page,
            '#folder-plugins-blockstudio-includes-library-blockstudio-element-code-tester',
            1
          );
        },
      },
      {
        description: 'delete',
        testFunction: async (page: Page) => {
          await page.click(
            '#folder-plugins-blockstudio-includes-library-blockstudio-element-code-tester'
          );
          await count(
            page,
            '#block-plugins-blockstudio-includes-library-blockstudio-element-code-tester-block-json',
            1
          );
          await page.keyboard.down('Control');
          await page
            .locator(
              '#block-plugins-blockstudio-includes-library-blockstudio-element-code-tester-block-json'
            )
            .click();
          await page.keyboard.up('Control');
          await page.click('text=Delete block');
          await count(page, '.components-modal__screen-overlay', 1);
          await page.keyboard.press('Enter');
          await count(page, '#block-blockstudio-element-code-tester-block', 0);
          const clicker = async () => {
            await page.keyboard.down('Control');
            await page
              .locator(
                '#folder-plugins-blockstudio-includes-library-blockstudio-element-code-tester'
              )
              .click();
            await page.keyboard.up('Control');
            if (!(await page.$('text=Delete folder'))) {
              await clicker();
            } else {
              await page.click('text=Delete folder');
              await page.keyboard.press('Enter');
              await count(
                page,
                '#folder-plugins-blockstudio-includes-library-blockstudio-element-code-tester',
                0
              );
            }
          };
          await clicker();
        },
      },
      {
        description: 'check console',
        testFunction: async (page: Page) => {
          await page.click(
            '#block-plugins-blockstudio-includes-library-blockstudio-element-code-block-json'
          );
          await clickEditorToolbar(page, 'console', true);
          await count(page, '#blockstudio-editor-console > div', 9);
          await clickEditorToolbar(page, 'console', false);
        },
      },
      {
        description: 'file is deleted from media library',
        testFunction: async (page: Page) => {
          await page.goto(
            `https://fabrikat.local/blockstudio/wp-admin/upload.php`
          );
          await count(page, '.subtype-zip', 0);
        },
      },
    ],
  },
]);
