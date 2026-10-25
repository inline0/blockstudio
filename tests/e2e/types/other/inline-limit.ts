import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';
import { keepSqliteFixtureOpen } from '../../utils/sqlite-fixture.ts';

let releaseFixture: (() => void) | undefined;
test.beforeAll(() => {
  if (
    process.env.BLOCKSTUDIO_PLAYGROUND_RUNTIME ||
    process.env.BLOCKSTUDIO_PLAYGROUND_BRIDGE
  )
    return;
  const database = keepSqliteFixtureOpen(
    resolve('tests/theme/blockstudio/types/db/sqlite/db/default.sqlite'),
  );
  releaseFixture = () => database.close();
});
test.afterAll(() => {
  releaseFixture?.();
});

for (const limit of [0, 40000]) {
  test(`cold and warm asset output matches with inline limit ${limit}`, async ({
    page,
  }) => {
    const headers = { 'X-Blockstudio-Test-Inline-Limit': String(limit) };
    await page.setExtraHTTPHeaders(headers);
    const endpoint = '/wp-json/blockstudio-test/v1/cache/asset-records';
    const reset = await page.request.post(endpoint, {
      data: { action: 'clear' },
      headers,
    });
    expect(reset.ok()).toBeTruthy();
    expect(await reset.json()).toEqual({ cleared: true });

    const output: string[] = [];
    const backgrounds: string[] = [];
    for (let request = 0; request < 2; request++) {
      expect((await page.goto('/native-single/'))?.status()).toBe(200);
      const stylesheet = page.locator(
        `${limit ? 'style' : 'link'}#blockstudio-blockstudio-init-global-style-css`,
      );
      await expect(stylesheet).toHaveCount(1);
      output.push(await stylesheet.evaluate((element) => element.outerHTML));
      backgrounds.push(
        await page
          .locator('body')
          .evaluate((body) => getComputedStyle(body).backgroundColor),
      );
    }
    expect(output[1]).toBe(output[0]);
    expect(backgrounds[1]).toBe(backgrounds[0]);

    const inspection = await page.request.post(endpoint, { headers });
    expect(inspection.ok()).toBeTruthy();
    const records = await inspection.json();
    expect(records.cached, JSON.stringify(records.diagnostics)).toBe(true);
    const stylesheet = Object.values(records.assets).find((asset: any) =>
      asset.path.endsWith('/global-style.css'),
    ) as any;
    expect(stylesheet).toBeDefined();
    expect(stylesheet.output.source).toBe(stylesheet.path);
    expect(stylesheet.output.path).toEqual(expect.any(String));
    expect(stylesheet.output.size).toBeGreaterThan(0);
  });
}

test('small external stylesheets can inline without changing applied styles and stay linked by default', async ({
  page,
}) => {
  const id = 'blockstudio-blockstudio-init-global-style-css';
  const url = 'http://localhost:8888/native-single/';
  expect((await page.goto(url))?.status()).toBe(200);
  await expect(page.locator(`link#${id}`)).toHaveCount(1);
  await expect(page.locator(`style#${id}`)).toHaveCount(0);
  const background = await page
    .locator('body')
    .evaluate((body) => getComputedStyle(body).backgroundColor);

  await page.setExtraHTTPHeaders({
    'X-Blockstudio-Test-Inline-Limit': '40000',
  });
  expect((await page.goto(url))?.status()).toBe(200);
  await expect(page.locator(`style#${id}`)).toHaveCount(1);
  await expect(page.locator(`link#${id}`)).toHaveCount(0);
  const stylesheet = page.locator(`style#${id}`);
  expect(await stylesheet.textContent()).toContain('background');
  expect(
    await stylesheet.evaluate(
      (element) => (element as HTMLStyleElement).sheet?.cssRules.length ?? 0,
    ),
  ).toBeGreaterThan(0);
  expect(
    await page
      .locator('body')
      .evaluate((body) => getComputedStyle(body).backgroundColor),
  ).toBe(background);

  await page.setExtraHTTPHeaders({ 'X-Blockstudio-Test-Inline-Limit': '0' });
  expect((await page.goto(url))?.status()).toBe(200);
  await expect(page.locator(`link#${id}`)).toHaveCount(1);
  await expect(page.locator(`style#${id}`)).toHaveCount(0);
});
