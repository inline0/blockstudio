import { expect, Page, test } from '@playwright/test';
import {
  clickEditorToolbar,
  count,
  getFrame,
  pEditor,
  searchForBlock,
  text,
} from '../../../playwright-utils';

const originalBlock = `<div class="blockstudio-test__block" id="<?php echo str_replace( "/", "-", $b["name"] ); ?>">
    <h1>ID: <?php echo str_replace( "/", "-", $b["name"] ); ?></h1>
    <div class="blockstudio-test__block-fields">
        <h1>Message Inside: <?php echo bs_get_group( $a, "group" )["message"]; ?></h1>
        <h2>Message Outside: <?php echo $a["message"]; ?></h2>
        <div class="<?php echo bs_get_scoped_class( $block["name"] ); ?>">
            <h2>Scoped H2</h2>
        </div>
    </div>
    <code>Attributes: <?php echo json_encode( $a ); ?></code>
    <code>Block: <?php echo json_encode( $b ); ?></code>
</div>
`;

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
  await clickEditorToolbar(page, 'preview', false);
  await clickEditorToolbar(page, 'files', false);
  await clickEditorToolbar(page, 'console', false);
  await page.close();
});

test.describe('preview', () => {
  test('change content', async () => {
    await page.keyboard.press('Meta+A');
    await page.keyboard.press('Backspace');
    await page.keyboard.insertText('This is test data');
    await clickEditorToolbar(page, 'preview', true);
    await expect(
      page.frameLocator('iframe').locator('text=This is test data')
    ).toHaveCount(1);
  });

  test('check assets', async () => {
    const preview = await getFrame(page, 'blockstudio-preview');
    await expect(preview.locator('link')).toHaveCount(4);
    await expect(preview.locator('style')).toHaveCount(7);
    await expect(preview.locator('script')).toHaveCount(3);
  });

  test('warn on switch', async () => {
    await clickEditorToolbar(page, 'files', true);
    await page.fill('.components-text-control__input', 'code');
    await page.click(
      '#block-plugins-blockstudio-includes-library-blockstudio-element-code-index-php'
    );
    await count(page, 'text=Save changes on current block?', 1);
    await page.click('[aria-label="Close"]');
  });

  test('warn on exit', async () => {
    await page.click('#blockstudio-editor-toolbar-exit');
    await count(page, 'text=Save changes on current block?', 1);
    await page.click('[aria-label="Close"]');
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

  test('create error', async () => {
    await page.keyboard.press('Meta+A');
    await page.keyboard.press('Backspace');
    await page.keyboard.insertText('<?php not working');
    await clickEditorToolbar(page, 'console', true);
    await count(page, 'text=There is an error in your block template file', 2);
  });

  test('insert old content', async () => {
    await page.click('.monaco-scrollable-element');
    await page.keyboard.press('Meta+A');
    await page.keyboard.press('Backspace');
    await page.keyboard.insertText(originalBlock);
    const preview = await getFrame(page, 'blockstudio-preview');
    await expect(preview.locator('text=Message Inside:')).toHaveCount(1);
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

  test.describe('es modules', () => {
    test('initializing', async () => {
      await page.click('#blockstudio-editor-toolbar-exit');
      await searchForBlock(
        page,
        'element',
        '#block-plugins-blockstudio-package-test-blocks-single-element-script-script-js'
      );
      const preview = await getFrame(page, 'blockstudio-preview');
      await expect(preview.locator('canvas')).toHaveCount(1);
      await expect(preview.locator('link')).toHaveCount(5);
      await expect(preview.locator('style')).toHaveCount(6);
      await expect(preview.locator('script')).toHaveCount(2);
    });

    test('load es module', async () => {
      await page.keyboard.press('Meta+A');
      await page.keyboard.press('Backspace');
      const preview = await getFrame(page, 'blockstudio-preview');
      await expect(preview.locator('canvas')).toHaveCount(0);
      await page.keyboard.insertText(
        'import confetti from "blockstudio/canvas-confetti@1.6.0"; confetti();'
      );
      await expect(preview.locator('canvas')).toHaveCount(1);
    });
  });

  test.describe('examples', () => {
    test('default', async () => {
      await page.click('#blockstudio-editor-toolbar-exit');
      await page.click("text=Don't save");
      await searchForBlock(
        page,
        'text',
        '#block-plugins-blockstudio-package-test-blocks-types-text-index-twig'
      );
      const preview = await getFrame(page, 'blockstudio-preview');
      await text(preview, 'Default value');
    });

    test('InnerBlocks', async () => {
      await page.click('#blockstudio-editor-toolbar-exit');
      await searchForBlock(
        page,
        'default',
        '#block-plugins-blockstudio-package-test-blocks-components-innerblocks-default-index-php'
      );
      const preview = await getFrame(page, 'blockstudio-preview');
      await text(preview, 'Test content');
    });
  });
});
