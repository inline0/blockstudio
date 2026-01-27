import { test, Page } from '@playwright/test';
import { checkStyle, count, innerHTML } from '../../playwright-utils';

let page: Page;

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
});

test.afterAll(async () => {
  await page.close();
});

test.describe('general', async () => {
  test.beforeAll(async () => {
    await page.goto(`https://fabrikat.local/blockstudio/native`);
  });

  test('post ids', async () => {
    await count(page, 'text=Post ID: 1386', 8);
  });

  test('init file', async () => {
    await count(page, '#blockstudio-init', 1);
  });

  test('init-2 file', async () => {
    await count(page, '#blockstudio-init-2', 1);
  });

  test.describe('assets', async () => {
    test('compiled', async () => {
      await count(page, 'canvas', 1);
      await checkStyle(
        page,
        '.blockstudio-test__block h1',
        'color',
        'rgb(255, 0, 0)'
      );
    });
    test('global', async () => {
      await count(page, '#blockstudio-blockstudio-init-global-style-css', 1);
      await count(page, '#blockstudio-blockstudio-init-global-script-js', 1);
    });
    test('no assets', async () => {
      await count(page, 'style[id]', 8);
      await count(page, 'script[type="module"]', 12);
    });
  });

  test.describe(`Modules`, () => {
    ['Script', 'Inline', 'Inline 2'].forEach((item) => {
      test(`${item} initialise web component`, async () => {
        await count(page, `text=Hello ${item}: Hello from Preact!`, 1);
      });
    });
  });
});

['native', 'native-render'].forEach(async (type) => {
  test.describe(`blocks ${type}`, () => {
    test.beforeAll(async () => {
      await page.goto(`https://fabrikat.local/blockstudio/${type}`);
    });

    [
      {
        name: 'blockstudio-native',
        amount: 3,
        amountRender: 2,
        assets: true,
        assetsRender: true,
      },
      {
        name: 'blockstudio-native-twig',
        amount: 3,
        amountRender: 2,
      },
      {
        name: 'blockstudio-native-nested',
        amount: 5,
        amountRender: 4,
        assets: true,
        assetsRender: true,
        fields: {
          '#blockstudio-native-nested #blockstudio-native-nested .blockstudio-test__block-fields h1':
            'Message Inside: First message inside',
          '#blockstudio-native-nested #blockstudio-native-nested .blockstudio-test__block-fields h2':
            'Message Outside: First message outside',
          '#blockstudio-native-nested #blockstudio-native-nested h3':
            'Hello extra content',
        },
      },
    ].forEach(async (item) => {
      const assets =
        (item.assets &&
          (type === 'native' ||
            type === 'acf' ||
            type === 'mb' ||
            type === 'reusable')) ||
        (item.assetsRender &&
          (type === 'native-render' || type === 'acf-render'))
          ? 1
          : 0;
      const amount =
        type === 'native-render' ? item?.amountRender : item?.amount || 1;
      const fields = item.fields;

      const assetName = `${item.name.replace('blockstudio-', '')}`;

      test.describe(`render ${item.name}`, () => {
        test('block', async () => {
          await count(page, `#${item.name}`, amount);
        });
        if (fields) {
          Object.entries(fields).forEach(async ([k, v]) => {
            test(k, async () => {
              await innerHTML(page, k, v);
            });
          });
        }
        test('style', async () => {
          await count(
            page,
            `#blockstudio-blockstudio-${assetName}-style-css`,
            assets
          );
        });
        test('inline style', async () => {
          await count(
            page,
            `#blockstudio-blockstudio-${assetName}-style-inline-css`,
            assets
          );
        });
        test('editor style', async () => {
          await count(
            page,
            `#blockstudio-blockstudio-${assetName}-style-editor-css`,
            0
          );
        });
        test('scoped style', async () => {
          await count(
            page,
            `#blockstudio-blockstudio-${assetName}-style-scoped-css`,
            assets
          );
        });
        test('script', async () => {
          await count(
            page,
            `#blockstudio-blockstudio-${assetName}-script-js`,
            assets
          );
        });
        test('inline script', async () => {
          await count(
            page,
            `#blockstudio-blockstudio-${assetName}-script-inline-js`,
            assets
          );
        });
        test('editor script', async () => {
          await count(
            page,
            `#blockstudio-blockstudio-${assetName}-script-editor-js`,
            0
          );
        });
        test('view script', async () => {
          await count(
            page,
            `#blockstudio-blockstudio-${assetName}-script-view-js`,
            assets
          );
        });
      });
    });
  });
});
