import { expect, test, Page } from '@playwright/test';
import { count, pBlocks, delay, getEditorCanvas } from './utils/playwright-utils';

test.describe('Patterns', () => {
  test('pattern appears in block inserter and inserts correct blocks', async ({
    browser,
  }) => {
    const page = await pBlocks(browser);
    const canvas = await getEditorCanvas(page);

    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);

    await page.click('role=tab[name="Patterns"]');
    await delay(500);

    await page.fill('[placeholder="Search"]', 'Blockstudio E2E Test Pattern');
    await delay(1000);

    const patternItem = page.locator(
      '.block-editor-block-patterns-list__item:has-text("Blockstudio E2E Test Pattern")'
    );
    await expect(patternItem.first()).toBeVisible({ timeout: 10000 });

    await patternItem.first().click();
    await delay(500);

    await page.keyboard.press('Escape');

    await count(canvas, '.wp-block-group', 1);
    await count(canvas, '.wp-block-heading', 1);
    await count(canvas, '.wp-block-buttons', 1);
    await count(canvas, '.wp-block-button', 1);
    await count(canvas, '.wp-block-separator', 1);
    await count(canvas, '.wp-block-list', 1);
    await count(canvas, '.wp-block-quote', 1);
    await count(canvas, '.wp-block-image', 1);
    await count(canvas, '.wp-block-columns', 1);
    await count(canvas, '.wp-block-column', 2);

    const heading = canvas.locator('.wp-block-heading');
    await expect(heading).toHaveText('Test Pattern Heading');

    const paragraph = canvas.locator('.wp-block-paragraph').first();
    await expect(paragraph).toHaveText('This is a test pattern paragraph.');

    const button = canvas.locator('.wp-block-button__link');
    await expect(button).toHaveText('Test Button');

    await expect(canvas.getByText('Pattern list item one')).toBeVisible();
    await expect(canvas.getByText('Pattern list item two')).toBeVisible();

    await expect(canvas.getByText('Pattern quote text')).toBeVisible();

    const image = canvas.locator('.wp-block-image img');
    await expect(image).toHaveAttribute('alt', 'Pattern image');

    await expect(canvas.getByText('Pattern column A')).toBeVisible();
    await expect(canvas.getByText('Pattern column B')).toBeVisible();
  });

  test('pattern can be found by keywords', async ({ browser }) => {
    const page = await pBlocks(browser);

    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);

    await page.click('role=tab[name="Patterns"]');
    await delay(500);

    await page.fill('[placeholder="Search"]', 'blockstudio test');
    await delay(1000);

    const patternItem = page.locator(
      '.block-editor-block-patterns-list__item:has-text("Blockstudio E2E Test Pattern")'
    );
    await expect(patternItem.first()).toBeVisible({ timeout: 10000 });
  });
});

test.describe('Patterns (Twig)', () => {
  const searchAndFindTwigPattern = async (page: Page) => {
    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);

    await page.click('role=tab[name="Patterns"]');
    await delay(500);

    await page.fill('[placeholder="Search"]', 'twig');
    await delay(1500);

    return page.locator(
      '.block-editor-block-patterns-list__item:has-text("Twig")'
    );
  };

  test('twig pattern appears in block inserter and inserts correct blocks', async ({
    browser,
  }) => {
    const page = await pBlocks(browser);
    const canvas = await getEditorCanvas(page);

    const patternItem = await searchAndFindTwigPattern(page);
    await expect(patternItem).toBeVisible({ timeout: 15000 });

    await patternItem.click();
    await delay(500);

    await page.keyboard.press('Escape');

    await count(canvas, '.wp-block-group', 1);
    await count(canvas, '.wp-block-heading', 1);
    await count(canvas, '.wp-block-buttons', 1);
    await count(canvas, '.wp-block-button', 1);
    await count(canvas, '.wp-block-separator', 1);
    await count(canvas, '.wp-block-list', 1);
    await count(canvas, '.wp-block-quote', 1);
    await count(canvas, '.wp-block-image', 1);
    await count(canvas, '.wp-block-columns', 1);
    await count(canvas, '.wp-block-column', 2);

    const heading = canvas.locator('.wp-block-heading');
    await expect(heading).toHaveText('Twig Pattern Heading');

    const paragraph = canvas.locator('.wp-block-paragraph').first();
    await expect(paragraph).toContainText('TWIG');

    const button = canvas.locator('.wp-block-button__link');
    await expect(button).toHaveText('Twig Pattern Button');

    await expect(canvas.getByText('Twig pattern item 1')).toBeVisible();
    await expect(canvas.getByText('Twig pattern item 2')).toBeVisible();

    await expect(canvas.getByText('Twig pattern quote text')).toBeVisible();

    const image = canvas.locator('.wp-block-image img');
    await expect(image).toHaveAttribute('alt', 'Twig pattern image');

    await expect(canvas.getByText('Twig pattern column A')).toBeVisible();
    await expect(canvas.getByText('Twig pattern column B')).toBeVisible();
  });

  test('twig pattern can be found by twig keyword', async ({ browser }) => {
    const page = await pBlocks(browser);

    const patternItem = await searchAndFindTwigPattern(page);
    await expect(patternItem).toBeVisible({ timeout: 15000 });
  });
});

test.describe('Patterns (Blade)', () => {
  const searchAndFindBladePattern = async (page: Page) => {
    await page.click('.editor-document-tools__inserter-toggle');
    await count(page, '.block-editor-inserter__block-list', 1);

    await page.click('role=tab[name="Patterns"]');
    await delay(500);

    await page.fill('[placeholder="Search"]', 'blade');
    await delay(1500);

    return page.locator(
      '.block-editor-block-patterns-list__item:has-text("Blade")'
    );
  };

  test('blade pattern appears in block inserter and inserts correct blocks', async ({
    browser,
  }) => {
    const page = await pBlocks(browser);
    const canvas = await getEditorCanvas(page);

    const patternItem = await searchAndFindBladePattern(page);
    await expect(patternItem).toBeVisible({ timeout: 15000 });

    await patternItem.click();
    await delay(500);

    await page.keyboard.press('Escape');

    await count(canvas, '.wp-block-group', 1);
    await count(canvas, '.wp-block-heading', 1);
    await count(canvas, '.wp-block-buttons', 1);
    await count(canvas, '.wp-block-button', 1);
    await count(canvas, '.wp-block-separator', 1);
    await count(canvas, '.wp-block-list', 1);
    await count(canvas, '.wp-block-quote', 1);
    await count(canvas, '.wp-block-image', 1);
    await count(canvas, '.wp-block-columns', 1);
    await count(canvas, '.wp-block-column', 2);

    const heading = canvas.locator('.wp-block-heading');
    await expect(heading).toHaveText('Blade Pattern Heading');

    const paragraph = canvas.locator('.wp-block-paragraph').first();
    await expect(paragraph).toContainText('BLADE');

    const button = canvas.locator('.wp-block-button__link');
    await expect(button).toHaveText('Blade Pattern Button');

    await expect(canvas.getByText('Blade pattern item 1')).toBeVisible();
    await expect(canvas.getByText('Blade pattern item 2')).toBeVisible();

    await expect(canvas.getByText('Blade pattern quote text')).toBeVisible();

    const image = canvas.locator('.wp-block-image img');
    await expect(image).toHaveAttribute('alt', 'Blade pattern image');

    await expect(canvas.getByText('Blade pattern column A')).toBeVisible();
    await expect(canvas.getByText('Blade pattern column B')).toBeVisible();
  });

  test('blade pattern can be found by blade keyword', async ({ browser }) => {
    const page = await pBlocks(browser);

    const patternItem = await searchAndFindBladePattern(page);
    await expect(patternItem).toBeVisible({ timeout: 15000 });
  });
});
