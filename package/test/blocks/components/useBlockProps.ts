import { Page, test } from '@playwright/test';
import {
  addBlock,
  checkForLeftoverAttributes,
  count,
  openSidebar,
  pBlocks,
  removeBlocks,
  save,
} from '../../../playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pBlocks(browser);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('useBlockProps', () => {
  [
    'default',
    'default-twig',
    'double',
    'double-twig',
    'double-root',
    'double-root-twig',
    'img',
    'img-twig',
    'innerblocks-default',
    'innerblocks-default-twig',
    'innerblocks-outer',
    'innerblocks-outer-twig',
    'innerblocks-richtext',
    'innerblocks-richtext-twig',
    'richtext-default',
    'richtext-default-twig',
  ].forEach((item) => {
    test.describe(`${item}`, () => {
      test.describe.configure({ mode: 'serial' });

      test('add block', async () => {
        await page.goto(
          'https://fabrikat.local/blockstudio/wp-admin/edit.php?post_type=post'
        );
        await page.locator('.row-title').first().click();
        await removeBlocks(page);
        await addBlock(page, `component-useblockprops-${item}`);
      });

      test('check wrapper', async () => {
        if (
          item === 'innerblocks-default' ||
          item === 'innerblocks-default-twig'
        ) {
          await page.click(`text=Type / to choose a block`);
        }

        await page.click('.editor-document-tools__document-overview-toggle');
        await page.click('.block-editor-list-view-block__contents-container a');

        if (item === 'default' || item === 'default-twig') {
          await count(page, '.is-root-container > .wp-block > *', 2);
        } else if (item === 'double-root' || item === 'double-root-twig') {
          await count(page, '.is-root-container > .wp-block *', 3);
        } else if (
          item === 'innerblocks-default' ||
          item === 'innerblocks-default-twig'
        ) {
          await count(page, '.is-root-container > [data-is-drop-zone]', 1);
        } else if (item === 'img' || item === 'img-twig') {
          await count(page, '.is-root-container > .wp-block', 1);
        } else if (
          item === 'innerblocks-outer' ||
          item === 'innerblocks-outer-twig'
        ) {
          await count(page, '.is-root-container > .wp-block', 1);
          await count(
            page,
            '.is-root-container > .wp-block > [data-is-drop-zone]',
            1
          );
        } else if (
          item === 'innerblocks-richtext' ||
          item === 'innerblocks-richtext-twig'
        ) {
          await count(page, '.is-root-container > .wp-block', 1);
          await count(
            page,
            '.is-root-container > .wp-block > [data-is-drop-zone]',
            1
          );
          await count(
            page,
            '.is-root-container > .wp-block > [contenteditable]',
            1
          );
          await page.click(
            '.is-root-container > .wp-block > [contenteditable]'
          );
          await page.keyboard.type('Test text');
          await page.locator('text=Type / to choose a block').click();
          await page.keyboard.type('Test text');
        } else if (
          item === 'richtext-default' ||
          item === 'richtext-default-twig'
        ) {
          await page.click(
            `[data-type="blockstudio/component-useblockprops-${item}"]`
          );
          await page.keyboard.type('Test text');
          await count(page, '.is-root-container > [contenteditable]', 1);
        } else {
          await count(page, '.is-root-container > .wp-block *', 2);
        }

        await count(page, '.blockstudio-test__block.test.test2.test3', 1);
        await count(page, '.blockstudio-test__block.test.test2.test3.test4', 0);

        if (item === 'default') {
          await page.click(
            '[data-type="blockstudio/component-useblockprops-default"]'
          );
          await openSidebar(page);
          await page.click('.block-editor-block-inspector__advanced');
          await page
            .locator('.block-editor-block-inspector__advanced input')
            .nth(0)
            .type('test-id', { delay: 100 });
        }
      });

      test('check content', async () => {
        await save(page);
        await page.goto(`https://fabrikat.local/blockstudio/native-single`);
        await checkForLeftoverAttributes(page);
        await count(page, '.blockstudio-test__block.test.test2.test3', 1);
        await count(page, '.blockstudio-test__block.test.test2.test3.test4', 0);
        if (item === 'default') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-default',
            1
          );
          await count(page, '#test-id', 1);
        }
        if (item === 'default-twig') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-default-twig',
            1
          );
        }
        if (item === 'img') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-img',
            1
          );
        }
        if (item === 'img-twig') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-img-twig',
            1
          );
        }
        if (item === 'innerblocks-default') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-innerblocks-default',
            1
          );
        }
        if (item === 'innerblocks-default-twig') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-innerblocks-default-twig',
            1
          );
          await count(page, '.blockstudio-test__block.test.test2.test3', 1);
        }
        if (item === 'innerblocks-outer') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-innerblocks-outer',
            1
          );
          await count(page, '.blockstudio-test__block.test.test2.test3', 1);
        }
        if (item === 'innerblocks-outer-twig') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-innerblocks-outer-twig',
            1
          );
        }
        if (item === 'innerblocks-richtext') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-innerblocks-richtext',
            1
          );
          await count(page, '.blockstudio-test__block.test.test2.test3', 1);
          await count(page, 'text=Test text', 2);
        }
        if (item === 'innerblocks-richtext-twig') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-innerblocks-richtext-twig',
            1
          );
          await count(page, '.blockstudio-test__block.test.test2.test3', 1);
          await count(page, 'text=Test text', 2);
        }
        if (item === 'richtext-default') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-richtext-default',
            1
          );
        }
        if (item === 'richtext-default-twig') {
          await count(
            page,
            '.wp-block-blockstudio-component-useblockprops-richtext-default-twig',
            1
          );
        }
        if (item === 'richtext-default' || item === 'richtext-default-twig') {
          await count(page, 'text=Test text', 1);
        }
      });
    });
  });
});
