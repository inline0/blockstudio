import { expect, Page, test } from '@playwright/test';
import { checkStyle, count, delay, pEditor } from '../../../playwright-utils';

let page: Page;

const save = async (page: Page) => {
  await page.click('.components-button.is-primary');
  await count(page, '.components-button.is-busy', 0);
  await page.click('.components-button.is-primary');
  await count(page, '.components-button.is-busy', 0);
  await delay(1000);
};

const toggle = async (page: Page, text: string) => {
  await page.click(`text=${text}`);
};

const removeToken = async (page: Page, index: number, subIndex: number) => {
  await page
    .locator('.components-form-token-field__input-container')
    .nth(index)
    .locator('.components-flex-item')
    .nth(subIndex)
    .locator('.components-form-token-field__remove-token')
    .click();
};

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pEditor(browser);
  await page.goto(
    'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
  );
});

test.afterAll(async () => {
  await toggle(page, 'Save as JSON');
  await save(page);
  await page.close();
});

['options', 'json', 'options 2'].forEach((item) => {
  if (item === 'options') {
    test('update markup', async () => {
      await page.click(".components-panel__body-toggle:has-text('Editor')");
      await page.fill('textarea', 'h1 { color: #f08080; }');
      await page.goto(
        'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/editor'
      );
      await page.goto(
        'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
      );
      await page.click(".components-panel__body-toggle:has-text('Editor')");
      await expect(page.locator('textarea')).toHaveValue('');
    });
    test('user ids', async () => {
      await page.click(".components-panel__body-toggle:has-text('Users')");
      await page
        .locator('.components-form-token-field__input-container')
        .nth(0)
        .click();
      await page.keyboard.type('alastai', {
        delay: 100,
      });
      await page.click('text=alastair');
      await save(page);
      await page.reload();
      await page.click(".components-panel__body-toggle:has-text('Users')");
      await count(page, 'text=alastair', 2);
      await removeToken(page, 0, 2);
      await count(page, 'text=alastair', 0);
      await save(page);
      await page.reload();
      await page.click(".components-panel__body-toggle:has-text('Users')");
      await count(page, 'text=alastair', 0);
    });
    test('user roles', async () => {
      await toggle(page, 'Save as JSON');
      await page
        .locator('.components-form-token-field__input-container')
        .nth(1)
        .click();
      await page.keyboard.type('editor', {
        delay: 100,
      });
      await page
        .locator('.components-form-token-field__input-container')
        .nth(1)
        .locator('text=editor')
        .click();
      await save(page);
      await page.reload();
      await page.click(".components-panel__body-toggle:has-text('Users')");
      await expect(
        page
          .locator('.components-form-token-field__input-container')
          .nth(1)
          .locator('text=editor', {
            exact: true,
          } as any)
      ).toHaveCount(2);
      await removeToken(page, 1, 0);
      await expect(
        page
          .locator('.components-form-token-field__input-container')
          .nth(1)
          .locator('text=editor', {
            exact: true,
          } as any)
      ).toHaveCount(1);
      await save(page);
      await page.reload();
      await page.click(".components-panel__body-toggle:has-text('Users')");
      await expect(
        page
          .locator('.components-form-token-field__input-container')
          .nth(1)
          .locator('text=editor', {
            exact: true,
          } as any)
      ).toHaveCount(0);
    });
    test('editor assets', async () => {
      await page.reload();
      await page.click(".components-panel__body-toggle:has-text('Editor')");
      await page
        .locator('.components-form-token-field__input-container')
        .nth(0)
        .click();
      await page.keyboard.type('wp-sanitize', {
        delay: 100,
      });
      await page
        .locator('.components-form-token-field__input-container')
        .nth(0)
        .locator('text=wp-sanitize')
        .click();
      await save(page);
      await page.reload();
      await page.click(".components-panel__body-toggle:has-text('Editor')");
      await expect(
        page
          .locator('.components-form-token-field__input-container')
          .nth(0)
          .locator('text=wp-sanitize')
      ).toHaveCount(2);
      await removeToken(page, 0, 0);
      await page.keyboard.press('Escape');
      await expect(
        page
          .locator('.components-form-token-field__input-container')
          .nth(0)
          .locator('text=wp-sanitize')
      ).toHaveCount(0);
      await save(page);
      await page.reload();
      await page.click(".components-panel__body-toggle:has-text('Editor')");
      await expect(
        page
          .locator('.components-form-token-field__input-container')
          .nth(0)
          .locator('text=wp-sanitize')
      ).toHaveCount(0);
    });
  }
  if (item === 'json') {
    test('check json', async () => {
      await page.goto(
        'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
      );
      await toggle(page, 'Save as JSON');
      await save(page);
    });
  }
  if (item === 'options 2') {
    test('uncheck json', async () => {
      await page.goto(
        'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
      );
      await toggle(page, 'Save as JSON');
      await save(page);
    });
  }

  test.describe(`settings ${item}`, () => {
    test.describe('assets', () => {
      test.describe('enqueue', () => {
        test('editor', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/post.php?post=1&action=edit'
          );
          await page.waitForSelector('.blockstudio-block h1');
          await checkStyle(
            page,
            '.blockstudio-block h1',
            'color',
            'rgb(240, 128, 128)'
          );
        });
        test('front', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/2023/02/10/test/'
          );
          await page.waitForSelector('.wp-block-post-content h1');
        });
      });
      test.describe("don't enqueue", () => {
        test('check', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
          );
          await page.click(".components-panel__body-toggle:has-text('Assets')");
          await toggle(page, 'Enqueue assets in frontend and editor');
          await save(page);
        });
        test('editor', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/post.php?post=1&action=edit'
          );
          await page.waitForSelector('.blockstudio-block h1');
          await checkStyle(
            page,
            '.blockstudio-block h1',
            'color',
            'rgb(240, 128, 128)',
            true
          );
        });
        test('front', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/2023/02/10/test/'
          );
          await page.waitForSelector('.wp-block-post-content h1');
        });
        test('uncheck', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
          );
          await page.click(".components-panel__body-toggle:has-text('Assets')");
          await toggle(page, 'Enqueue assets in frontend and editor');
          await save(page);
        });
      });
    });

    test.describe('process', () => {
      test.describe('modules', () => {
        test('editor', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/post.php?post=1&action=edit'
          );
          await count(page, 'canvas', 1);
        });
        test('front', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/2023/02/10/test/'
          );
          await count(page, 'canvas', 2);
        });
      });
      test.describe("don't minify", () => {
        test('css', async () => {
          await count(
            page,
            'style[id^="blockstudio"]:not([data-processed])',
            2
          );
          await count(page, 'style[id^="blockstudio"][data-processed]', 4);
        });
        test('js', async () => {
          await count(
            page,
            'script[id^="blockstudio"]:not([data-processed])',
            2
          );
        });
      });
      test.describe('minify', () => {
        test('check', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
          );
          await page.click(".components-panel__body-toggle:has-text('Assets')");
          await toggle(page, 'Minify CSS');
          await toggle(page, 'Minify JS');
          await save(page);
        });
        test('css', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/2023/02/10/test/'
          );
          await count(
            page,
            'link[id^="blockstudio"][data-processed], style[id^="blockstudio"][data-processed]',
            6
          );
        });
        test('js', async () => {
          await count(page, 'script[id^="blockstudio"][data-processed]', 4);
        });
        test('uncheck', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
          );
          await page.click(".components-panel__body-toggle:has-text('Assets')");
          await toggle(page, 'Minify CSS');
          await toggle(page, 'Minify JS');
          await save(page);
        });
      });
      test.describe('process SCSS in .css files', () => {
        test('check .scss file', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/2023/02/10/test/'
          );
          await checkStyle(page, '.wp-block-post-title', 'fontSize', '16px');
        });
        test('check', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
          );
          await page.click(".components-panel__body-toggle:has-text('Assets')");
          await toggle(page, 'Process SCSS in .css files');
          await save(page);
        });
        test('editor', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/2023/02/10/test/'
          );
          await checkStyle(
            page,
            '.wp-block-post-content h1',
            'fontSize',
            '16px'
          );
        });
        test('front', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/2023/02/10/test/'
          );
          await checkStyle(page, '.wp-block-post-title', 'fontSize', '16px');
        });
        test('uncheck', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
          );
          await page.click(".components-panel__body-toggle:has-text('Assets')");
          await toggle(page, 'Process SCSS in .css files');
          await save(page);
        });
      });
      test.describe('process CSS in .css files', () => {
        test('check .scss file', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/2023/02/10/test/'
          );
          await checkStyle(
            page,
            'h1:not(.wp-block-post-title)',
            'fontStyle',
            'italic'
          );
        });
        test('check', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
          );
          await page.click(".components-panel__body-toggle:has-text('Assets')");
          await toggle(page, 'Process .scss files to CSS');
          await save(page);
        });
        test('editor', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/2023/02/10/test/'
          );
          await checkStyle(
            page,
            'h1:not(.wp-block-post-title)',
            'fontStyle',
            'italic',
            true
          );
        });
        test('front', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/2023/02/10/test/'
          );
          await checkStyle(
            page,
            'h1:not(.wp-block-post-title)',
            'fontStyle',
            'italic',
            true
          );
        });
        test('uncheck', async () => {
          await page.goto(
            'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
          );
          await page.click(".components-panel__body-toggle:has-text('Assets')");
          await toggle(page, 'Process .scss files to CSS');
          await save(page);
        });
      });
      test('format code disabled', async () => {
        await page.goto(
          'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
        );
        await page.click(".components-panel__body-toggle:has-text('Editor')");
        await count(page, '[type="checkbox"][disabled]', 1);
      });
    });

    test.describe('ai', async () => {
      test('check', async () => {
        await page.goto(
          'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
        );
        await page
          .locator(".components-panel__body-toggle:has-text('AI')")
          .nth(1)
          .click();
        await toggle(page, 'Enables the automatic creation');
        await save(page);
        const response = await page.goto(
          'https://fabrikat.local/site-editor/blockstudio-llm.txt'
        );
        expect(response.status()).toBe(200);
      });
      test('uncheck', async () => {
        await page.goto(
          'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
        );
        await page
          .locator(".components-panel__body-toggle:has-text('AI')")
          .nth(1)
          .click();
        await toggle(page, 'Enables the automatic creation');
        await save(page);
        const response = await page.goto(
          'https://fabrikat.local/site-editor/blockstudio-llm.txt'
        );
        expect(response.status()).toBe(404);
        await page.goto(
          'https://fabrikat.local/site-editor/wp-admin/admin.php?page=blockstudio#/settings'
        );
      });
    });
  });
});
