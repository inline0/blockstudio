import { Page, test } from '@playwright/test';
import {
  count,
  openSidebar,
  pBlocks,
  removeBlocks,
} from '../../playwright-utils';

test.describe.configure({ mode: 'serial' });

test.describe('block conditions', () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    page = await pBlocks(
      browser,
      'https://fabrikat.local/blockstudio/wp-admin/post.php?post=774&action=edit'
    );
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('open block inserter', async () => {
    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);
  });

  test('repeater not shown', async () => {
    await page.type('[placeholder="Search"]', 'repeater');
    await count(page, '.block-editor-block-types-list__list-item', 10);
  });
});

test.describe('attribute conditions', () => {
  let page: Page;

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

  test('add block', async () => {
    await page.click(`text=Native Conditions`);
    await count(page, '.is-root-container > .wp-block', 1);
  });

  test('click condition block', async () => {
    await page.click(`[data-type="blockstudio/type-conditions"]`);
    await openSidebar(page);
  });

  test.describe('operators', () => {
    ['group', 'repeater'].forEach((item) => {
      test.describe(item, () => {
        test('text on toggle', async () => {
          if (item === 'repeater') {
            await page.click(`text=Add row`);
          }

          await page
            .locator(
              `.blockstudio-fields__field--${item} .blockstudio-fields__field--toggle .components-form-toggle__input`
            )
            .first()
            .click();
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Text on toggle"]`,
            1
          );
        });
        test('text on includes value', async () => {
          await page.click(
            `.blockstudio-fields__field--${item} [aria-label="Text on toggle"] + div input`
          );
          await page.keyboard.press('t');
          await page.keyboard.press('e');
          await page.keyboard.press('s');
          await page.keyboard.press('t');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Text on includes value"]`,
            1
          );
        });
        test('number on empty', async () => {
          await page.click(
            `.blockstudio-fields__field--${item} [aria-label="Text on includes value"] + div input`
          );
          await page.keyboard.press('t');
          await page.keyboard.press('e');
          await page.keyboard.press('s');
          await page.keyboard.press('t');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Number on empty"]`,
            1
          );
        });
        test('number on smaller than', async () => {
          await page.click(
            `.blockstudio-fields__field--${item} [aria-label="Number on empty"] + div input`
          );
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Number on smaller than"]`,
            0
          );
          await page.keyboard.press('ArrowDown');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Number on smaller than"]`,
            1
          );
        });
        test('number on smaller than or even', async () => {
          await page.click(
            `.blockstudio-fields__field--${item} [aria-label="Number on smaller than"] + div input`
          );
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="=Number on smaller than or even"]`,
            0
          );
          await page.keyboard.press('ArrowDown');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Number on smaller than or even"]`,
            1
          );
        });
        test('number on bigger than', async () => {
          await page.click(
            `.blockstudio-fields__field--${item} [aria-label="Number on smaller than or even"] + div input`
          );
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Number on bigger than"]`,
            0
          );
          await page.keyboard.press('ArrowUp');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Number on bigger than"]`,
            1
          );
        });
        test('number on bigger than or even', async () => {
          await page.click(
            `.blockstudio-fields__field--${item} [aria-label="Number on bigger than"] + div input`
          );
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Number on bigger than or even"]`,
            0
          );
          await page.keyboard.press('ArrowUp');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Number on bigger than or even"]`,
            1
          );
        });
        test('select on bigger than or even', async () => {
          await page.click(
            `.blockstudio-fields__field--${item} [aria-label="Number on bigger than or even"] + div input`
          );
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await page.keyboard.press('ArrowUp');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Select on bigger than or even"]`,
            0
          );
          await page.keyboard.press('ArrowUp');
          await count(
            page,
            `.blockstudio-fields__field--${item} [aria-label="Select on bigger than or even"]`,
            1
          );
        });
        test('final toggle', async () => {
          await page.selectOption(
            `.blockstudio-fields__field--${item} .blockstudio-fields__field--select select`,
            {
              value: 'test2',
            }
          );
          await count(
            page,
            `.blockstudio-fields__field--${item} >> text=Final toggle`,
            1
          );
        });
      });
    });

    test.describe('operators repeater main', () => {
      test('text on toggle', async () => {
        await page.click(`text=Add main`);
        await count(page, `text=Text on toggle`, 3);
      });
      test('text on includes value', async () => {
        await count(page, `text=Text on includes value`, 3);
      });
      test('number on empty', async () => {
        await count(page, `text=Number on empty`, 3);
      });
      test('number on smaller than', async () => {
        await count(page, `text=Number on smaller than`, 6);
      });
      test('number on smaller than or even', async () => {
        await count(page, `text=Number on smaller than or even`, 3);
      });
      test('number on bigger than', async () => {
        await count(page, `text=Number on bigger than`, 6);
      });
      test('number on bigger than or even', async () => {
        await count(page, `text=Number on bigger than or even`, 3);
      });
      test('select on bigger than or even', async () => {
        await count(page, `text=Select on bigger than or even`, 3);
      });
      test('final toggle', async () => {
        await count(page, `text=Final toggle`, 3);
      });
    });

    test('remove block', async () => {
      await removeBlocks(page);
    });
  });
});
