import { Page, test, Frame } from '@playwright/test';
import { checkStyle, count, delay, pBlocks } from '../../../playwright-utils';

const getIframeContent = async (page: Page): Promise<Frame> => {
  const iframeElement = await page.waitForSelector('iframe');
  return await iframeElement.contentFrame();
};

const clickEdit = async (page: Page) => {
  await page.click(
    '.blockstudio-fields + .components-panel .components-panel__body-toggle'
  );
  await page.click('text=Edit block with Blockstudio');
};

const originalBlockContent = `<div class="<?php echo bs_get_scoped_class($b['name']); ?>"><h1>Hello world!</h1></div>`;
const originalCssContent = `h1 { color: lightcoral; }`;

let page: Page;
let popup: any;
let frame: any;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pBlocks(
    browser,
    'https://fabrikat.local/site-editor/wp-admin/post.php?post=1&action=edit',
    '[data-type="test/test2"]',
    false
  );
});

test.afterAll(async () => {
  await page.close();
});

test.describe('gutenberg', () => {
  test('first block color is light red', async () => {
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(240, 128, 128)'
    );
  });

  test('change content', async () => {
    const popupPromise = page.waitForEvent('popup');
    await page.click('[data-type="test/test"]');
    await clickEdit(page);
    popup = await popupPromise;
    frame = await getIframeContent(popup);
    await frame.click('#blockstudio-editor-tab-index-php');
    await frame.waitForSelector('.mtk10');
    await popup.keyboard.press('Meta+A');
    await popup.keyboard.press('Backspace');
    await popup.keyboard.type(
      `<div class="<?php echo bs_get_scoped_class($b['name']); ?>"><h1>test text from editor</h1></div>`,
      {
        delay: 100,
      }
    );
    await count(page, 'text=test text from editor', 1);
  });

  test('change css', async () => {
    await frame.click('#blockstudio-editor-tab-style-scoped-css');
    await frame.waitForSelector('.colorpicker-color-decoration');
    await popup.keyboard.press('Meta+A');
    await popup.keyboard.press('Backspace');
    await popup.keyboard.type('h1 { color: yellow; }', {
      delay: 100,
    });
    await delay(2000);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(255, 255, 0)'
    );
  });

  test('reset block', async () => {
    await page.click('[data-type="test/test2"]');
    await clickEdit(page);
    await count(page, 'text=Hello world!', 2);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(240, 128, 128)'
    );
  });

  test('change content again', async () => {
    const popupPromise = page.waitForEvent('popup');
    await page.click('[data-type="test/test"]');
    await clickEdit(page);
    popup = await popupPromise;
    frame = await getIframeContent(popup);
    await frame.click('#blockstudio-editor-tab-index-php');
    await frame.waitForSelector('.mtk10');
    await popup.keyboard.press('Meta+A');
    await popup.keyboard.press('Backspace');
    await popup.keyboard.type(
      `<div class="<?php echo bs_get_scoped_class($b['name']); ?>"><h1>test text from editor</h1></div>`,
      {
        delay: 100,
      }
    );
    await count(page, 'text=test text from editor', 1);
  });

  test('change css again', async () => {
    await frame.click('#blockstudio-editor-tab-style-scoped-css');
    await frame.waitForSelector('.colorpicker-color-decoration');
    await popup.keyboard.press('Meta+A');
    await popup.keyboard.press('Backspace');
    await popup.keyboard.type('h1 { color: yellow; }', {
      delay: 100,
    });
    await delay(2000);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(255, 255, 0)'
    );
  });

  test('save block', async () => {
    await frame.click('text=Save Block');
    await count(frame, '.components-button.is-busy', 0);
  });

  test('block has new data', async () => {
    await page.click('[data-type="test/test2"]');
    await clickEdit(page);
    await count(page, 'text=test text from editor', 1);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(255, 255, 0)'
    );
  });

  test('change content to original', async () => {
    const popupPromise = page.waitForEvent('popup');
    await page.click('[data-type="test/test"]');
    await clickEdit(page);
    popup = await popupPromise;
    frame = await getIframeContent(popup);
    await frame.click('#blockstudio-editor-tab-index-php');
    await frame.waitForSelector('.mtk10');
    await popup.keyboard.press('Meta+A');
    await popup.keyboard.press('Backspace');
    await popup.keyboard.type(originalBlockContent, {
      delay: 100,
    });
    await count(page, 'text=test text from editor', 1);
  });

  test('change css to original', async () => {
    await frame.click('#blockstudio-editor-tab-style-scoped-css');
    await frame.waitForSelector('.colorpicker-color-decoration');
    await popup.keyboard.press('Meta+A');
    await popup.keyboard.press('Backspace');
    await popup.keyboard.type(originalCssContent, {
      delay: 100,
    });
    await delay(2000);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(240, 128, 128)'
    );
  });

  test('reset block from original', async () => {
    await page.click('[data-type="test/test2"]');
    await clickEdit(page);
    await count(page, 'text=test text from editor', 1);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(255, 255, 0)'
    );
  });

  test('change content to original again', async () => {
    const popupPromise = page.waitForEvent('popup');
    await page.click('[data-type="test/test"]');
    await clickEdit(page);
    popup = await popupPromise;
    frame = await getIframeContent(popup);
    await frame.click('#blockstudio-editor-tab-index-php');
    await frame.waitForSelector('.mtk10');
    await popup.keyboard.press('Meta+A');
    await popup.keyboard.press('Backspace');
    await popup.keyboard.type(originalBlockContent, {
      delay: 100,
    });
    await delay(2000);
    await count(page, 'text=Hello world!', 2);
  });

  test('change css to original again', async () => {
    await frame.click('#blockstudio-editor-tab-style-scoped-css');
    await frame.waitForSelector('.colorpicker-color-decoration');
    await popup.keyboard.press('Meta+A');
    await popup.keyboard.press('Backspace');
    await popup.keyboard.type(originalCssContent, {
      delay: 100,
    });
    await delay(2000);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(240, 128, 128)'
    );
  });

  test('save block to original', async () => {
    await frame.click('text=Save Block');
    await count(frame, '.components-button.is-busy', 1);
    await count(frame, '.components-button.is-busy', 0);
  });

  test('block has original data', async () => {
    await page.click('[data-type="test/test2"]');
    await clickEdit(page);
    await count(page, 'text=Hello world!', 2);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(240, 128, 128)'
    );
  });

  test('add style.css', async () => {
    const popupPromise = page.waitForEvent('popup');
    await page.click('[data-type="test/test"]');
    await clickEdit(page);
    popup = await popupPromise;
    frame = await getIframeContent(popup);
    await frame.click('[aria-label="Add file"]');
    await frame.fill('.components-text-control__input', 'style.css');
    await frame.click('text=Create file');
    await count(frame, '#blockstudio-editor-tab-style-css', 1);
    await popup.keyboard.type('h1 { color: yellow !important; }', {
      delay: 100,
    });
    await delay(2000);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(255, 255, 0)'
    );
  });

  test('reset css', async () => {
    await page.click('[data-type="test/test2"]');
    await clickEdit(page);
    await delay(2000);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(240, 128, 128)'
    );
  });

  test('add styles to style.css', async () => {
    const popupPromise = page.waitForEvent('popup');
    await page.click('[data-type="test/test"]');
    await clickEdit(page);
    popup = await popupPromise;
    frame = await getIframeContent(popup);
    await frame.click('#blockstudio-editor-tab-style-css');
    await delay(2000);
    await popup.keyboard.type('h1 { color: yellow !important; }', {
      delay: 100,
    });
    await delay(2000);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(255, 255, 0)'
    );
    await delay(2000);
    await frame.click('text=Save Block');
    await count(frame, '.components-button.is-busy', 1);
    await count(frame, '.components-button.is-busy', 0);
    await page.click('[data-type="test/test2"]');
    await clickEdit(page);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(255, 255, 0)'
    );
  });

  test('remove style.css', async () => {
    const popupPromise = page.waitForEvent('popup');
    await page.click('[data-type="test/test"]');
    await clickEdit(page);
    popup = await popupPromise;
    frame = await getIframeContent(popup);
    await frame.click('#blockstudio-editor-tab-style-css');
    await frame.click('#blockstudio-editor-toolbar-more');
    await frame.click('text=Delete file');
    await frame.click(
      '.components-modal__content .components-button.is-destructive'
    );
    await count(frame, '#blockstudio-editor-tab-style-css', 0);
    await page.click('[data-type="test/test2"]');
    await clickEdit(page);
    await checkStyle(
      page,
      '.blockstudio-block h1',
      'color',
      'rgb(240, 128, 128)'
    );
  });
});
