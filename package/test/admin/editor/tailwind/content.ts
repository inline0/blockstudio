import { expect, Page, test } from '@playwright/test';
import {
  addBlock,
  checkStyle,
  clickEditorToolbar,
  count,
  delay,
  getFrame,
  pEditor,
  removeBlocks,
  save,
  searchForBlock,
} from '../../../../playwright-utils';

const originalBlock = `<div class="blockstudio-test__block" id="{{ b.name|replace({'/': '-'}) }}">
  <h1 class="!text-weird custom-class">ID: {{ b.name|replace({'/': '-'}) }}</h1>
  <code>Attributes: {{ a|json_encode }}</code> 
  <code>Block: {{ b|json_encode }}</code> 
</div>
`;

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pEditor(browser);
  await searchForBlock(
    page,
    'tailwind',
    '#block-plugins-blockstudio-package-test-blocks-types-tailwind-default-index-twig'
  );
  await page.locator('.mtk10').first().waitFor({ state: 'visible' });
});

test.afterAll(async () => {
  await clickEditorToolbar(page, 'preview', false);
  await clickEditorToolbar(page, 'files', false);
  await clickEditorToolbar(page, 'console', false);
  await page.close();
});

test.describe('tailwind content', async () => {
  test('change content', async () => {
    await page.keyboard.press('Meta+A');
    await page.keyboard.press('Backspace');
    await page.keyboard.insertText(
      '<h1 class="!text-blue-500">This is test data</h1>'
    );
    await clickEditorToolbar(page, 'preview', true);
    const preview = await getFrame(page, 'blockstudio-preview');
    await checkStyle(
      preview,
      'text=This is test data',
      'color',
      'rgb(59, 130, 246)'
    );
  });

  test('save block', async () => {
    await page.click('text=Save Block');
    await count(
      page,
      '#blockstudio-editor-toolbar .components-button.is-primary.is-busy',
      1
    );
    await count(
      page,
      '#blockstudio-editor-toolbar .components-button.is-primary.is-busy',
      0
    );
  });

  test('check if saved', async () => {
    await page.reload();
    const preview = await getFrame(page, 'blockstudio-preview');
    await expect(preview.locator('text=This is test data')).toHaveCount(1);
  });

  test('check in block editor', async () => {
    await page.goto(
      'https://fabrikat.local/blockstudio/wp-admin/post.php?post=1483&action=edit'
    );
    await removeBlocks(page);
    await addBlock(page, 'type-tailwind');
    await checkStyle(
      page,
      '[data-type="blockstudio/type-tailwind"] h1',
      'color',
      'rgb(59, 130, 246)'
    );
  });

  test('check in frontend', async () => {
    await save(page);
    await page.locator('text=View Post').nth(1).click();
    await page.waitForSelector('body:not(.wp-admin)');
    await checkStyle(page, 'h1', 'color', 'rgb(59, 130, 246)');
  });

  test('insert old content', async ({ browser }) => {
    page = await pEditor(browser);
    await searchForBlock(
      page,
      'tailwind',
      '#block-plugins-blockstudio-package-test-blocks-types-tailwind-default-index-twig'
    );
    await page.locator('.mtk10').first().waitFor({ state: 'visible' });
    await page.click('.monaco-scrollable-element');
    await page.keyboard.press('Meta+A');
    await page.keyboard.press('Backspace');
    await page.keyboard.insertText(originalBlock);
    const preview = await getFrame(page, 'blockstudio-preview');
    await checkStyle(preview, 'h1', 'color', 'rgb(255, 192, 203)');
    await checkStyle(preview, 'h1', 'background-color', 'rgb(239, 68, 68)');
  });

  test('save block again', async () => {
    await page.click('text=Save Block');
    await count(
      page,
      '#blockstudio-editor-toolbar .components-button.is-primary.is-busy',
      1
    );
    await count(
      page,
      '#blockstudio-editor-toolbar .components-button.is-primary.is-busy',
      0
    );
  });
});
