import { expect, test } from '@playwright/test';
import { getEditorCanvas, login } from '../utils/playwright-utils';

test('file-backed core classes survive frontend rendering, editor validation, and saving', async ({
  page,
}) => {
  await login(page);
  const url = 'http://localhost:8888/blockstudio-static-core-classes/';
  await page.goto(url);
  await expect(page.locator('p.bs-mapped-paragraph')).toHaveText(
    'Mapped paragraph',
  );
  await expect(page.locator('p.bs-tag-paragraph')).toHaveText(
    'Explicit paragraph',
  );
  await expect(
    page.locator('.wp-block-group.bs-tag-group h2.bs-mapped-heading'),
  ).toHaveText('Mapped heading');
  await expect(
    page.locator('.wp-block-button.is-style-outline a'),
  ).toHaveAttribute('href', '/join/');

  const postId = await page.evaluate(() =>
    Number(document.body.className.match(/page-id-(\d+)/)?.[1]),
  );
  expect(postId).toBeGreaterThan(0);
  await page.goto(
    `http://localhost:8888/wp-admin/post.php?post=${postId}&action=edit`,
  );
  const canvas = await getEditorCanvas(page);
  await expect(
    canvas.locator('[data-type="core/paragraph"]').first(),
  ).toBeVisible();

  const validation = await page.evaluate(() => {
    const blocks = (window as any).wp.data
      .select('core/block-editor')
      .getBlocks();
    const flatten = (items: any[]): any[] =>
      items.flatMap((block) => [block, ...flatten(block.innerBlocks ?? [])]);
    const all = flatten(blocks);
    return {
      count: all.length,
      invalid: all
        .filter((block) => block.isValid === false)
        .map((block) => block.name),
    };
  });
  expect(validation.count).toBe(6);
  expect(validation.invalid).toEqual([]);

  await page.evaluate(() => {
    const { select, dispatch } = (window as any).wp.data;
    const paragraph = select('core/block-editor').getBlocks()[0];
    dispatch('core/block-editor').updateBlockAttributes(paragraph.clientId, {
      content: 'Mapped paragraph saved',
    });
  });
  await expect
    .poll(() =>
      page.evaluate(() =>
        (window as any).wp.data.select('core/editor').isEditedPostDirty(),
      ),
    )
    .toBe(true);
  await page.evaluate(async () => {
    await (window as any).wp.data.dispatch('core/editor').savePost();
  });
  await expect
    .poll(() =>
      page.evaluate(() =>
        (window as any).wp.data.select('core/editor').isEditedPostDirty(),
      ),
    )
    .toBe(false);
  await page.goto(url);
  await expect(page.locator('p.bs-mapped-paragraph')).toHaveText(
    'Mapped paragraph saved',
  );
  await expect(page.locator('p.bs-tag-paragraph')).toHaveText(
    'Explicit paragraph',
  );
  await expect(
    page.locator('.wp-block-group.bs-tag-group h2.bs-mapped-heading'),
  ).toBeVisible();
  await expect(
    page.locator('.wp-block-button.is-style-outline a'),
  ).toHaveAttribute('href', '/join/');
});
