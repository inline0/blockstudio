import { Page, test } from '@playwright/test';
import {
  addBlock,
  checkForLeftoverAttributes,
  count,
  openSidebar,
  pBlocks,
  removeBlocks,
  save,
  text,
} from '../../../playwright-utils';

test.describe.configure({ mode: 'serial' });

let page: Page;

test.beforeAll(async ({ browser }) => {
  page = await pBlocks(browser);
});

test.afterAll(async () => {
  await page.close();
});

test.describe('InnerBlocks', () => {
  [
    'no-wrapper',
    'context',
    'context-child',
    'bare',
    'bare-twig',
    'bare-spaceless',
    'bare-spaceless-twig',
    'default',
    'default-twig',
    'spaceless',
    'spaceless-twig',
    'richtext',
    'richtext-twig',
  ].forEach((item) => {
    test.describe(`${item}`, () => {
      test.describe.configure({ mode: 'serial' });

      test('add block', async () => {
        await page.goto(
          'https://fabrikat.local/blockstudio/wp-admin/edit.php?post_type=post'
        );
        await page.locator('.row-title').first().click();
        await removeBlocks(page);
        await addBlock(page, `component-innerblocks-${item}`);
      });

      if (item === 'bare') {
        // test('correct attribute presets', async () => {
        //   await text(
        //     page,
        //     '"text":"test","textClasses":false,"textAttributes":false,"textClassSelect":"class-1"'
        //   );
        // });
      }

      if (item === 'context') {
        test('classes in editor', async () => {
          await count(page, '.blockstudio-test__block.test.test2.test3', 2);
        });

        test('context working', async () => {
          await openSidebar(page);
          await page.click('.wp-block-post-title');
          await page.keyboard.press('ArrowDown');
          await page.waitForSelector(
            '#blockstudio-component-innerblocks-context-child'
          );
          await openSidebar(page);
          await page.click('.blockstudio-fields__field--text input');
          await page.keyboard.press('$', { delay: 100 });
          await page.keyboard.press('C', { delay: 100 });
          await page.keyboard.press('O', { delay: 100 });
          await page.keyboard.press('N', { delay: 100 });
          await page.keyboard.press('T', { delay: 100 });
          await page.keyboard.press('E', { delay: 100 });
          await page.keyboard.press('X', { delay: 100 });
          await page.keyboard.press('T', { delay: 100 });
          await count(page, 'text=Text: $CONTEXT', 2);
          await page.click('.editor-post-publish-button');
          await count(page, '.components-snackbar', 1);
        });

        test('check content', async () => {
          await page.goto(`https://fabrikat.local/blockstudio/native-single`);
          await count(page, 'text=Text: $CONTEXT', 2);
          await count(page, '.blockstudio-test__block.test.test2.test3', 2);
          await count(page, '[data-attr]', 2);
        });
      } else if (item === 'context-child') {
        test('classes in editor', async () => {
          await count(page, '.blockstudio-test__block.test.test2.test3', 1);
        });

        test('context working', async () => {
          await openSidebar(page);
          await page.click('.wp-block-post-title');
          await page.keyboard.press('ArrowDown');
          await page.waitForSelector(
            '#blockstudio-component-innerblocks-context-child'
          );
          await openSidebar(page);
          await page.click('.blockstudio-fields__field--text input');
          await page.keyboard.press('$', { delay: 100 });
          await page.keyboard.press('C', { delay: 100 });
          await page.keyboard.press('O', { delay: 100 });
          await page.keyboard.press('N', { delay: 100 });
          await page.keyboard.press('T', { delay: 100 });
          await page.keyboard.press('E', { delay: 100 });
          await page.keyboard.press('X', { delay: 100 });
          await page.keyboard.press('T', { delay: 100 });
          await count(page, 'text=$CONTEXT', 1);
          await page.click('.editor-post-publish-button');
          await count(page, '.components-snackbar', 1);
        });

        test('check content', async () => {
          await page.goto(`https://fabrikat.local/blockstudio/native-single`);
          await count(page, 'text=$CONTEXT', 1);
          await count(page, '.blockstudio-test__block.test.test2.test3', 1);
          await count(page, '[data-attr]', 1);
        });
      } else {
        if (item !== 'bare-spaceless' && item !== 'bare-spaceless-twig') {
          test('classes in editor', async () => {
            await count(page, '.blockstudio-test__block.test.test2.test3', 1);
          });
        }

        test('add content', async () => {
          if (item.includes('richtext')) {
            await page.click(
              '.is-root-container > .wp-block > div > [contenteditable]'
            );
            await page.keyboard.type('Test text');
          }

          if (item === 'bare' || item === 'bare-twig') {
            await page.click(`[aria-label="Block: Heading"]`);
          } else {
            await page.click(`text=Type / to choose a block`);
          }
          await page.keyboard.press('T');
          await page.keyboard.press('E');
          await page.keyboard.press('S');
          await page.keyboard.press('T');
          await page.keyboard.press('$');
          await page.keyboard.press('Enter');
          await page.keyboard.press('T');
          await page.keyboard.press('E');
          await page.keyboard.press('S');
          await page.keyboard.press('T');
          await page.keyboard.press('$');
          await page.keyboard.press('Enter');
          await page.keyboard.press('/');
          await openSidebar(page);
          // await count(
          //   page,
          //   '.components-popover__content button',
          //   9
          //   // item === 'bare-spaceless' || item === 'bare-spaceless-twig' ? 9 : 1
          // );
          await save(page);
        });

        test('check content', async () => {
          await page.goto(`https://fabrikat.local/blockstudio/native-single`);
          await checkForLeftoverAttributes(page);
          await count(page, 'text=TEST$', 2);
          if (item.includes('richtext')) {
            await count(page, 'text=Test text', 1);
          }
          if (
            item !== 'no-wrapper' &&
            item !== 'bare-spaceless' &&
            item !== 'bare-spaceless-twig'
          ) {
            await count(page, '.blockstudio-test__block.test.test2.test3', 1);
            await count(page, '[data-attr]', 1);
          } else {
            await count(page, '.blockstudio-test__block.test.test2.test3', 0);
          }
        });
      }
    });
  });
});
