import { Page, test } from '@playwright/test';
import { count, delay, pEditor } from '../../../../playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pEditor(browser);
  await page.goto(
    'https://fabrikat.local/blockstudio/wp-admin/admin.php?page=blockstudio#/settings'
  );
});

test.describe('tailwind classes', async () => {
  test('add class', async () => {
    await page.click('text=Tailwind');
    await page.click('text=Edit Global Classes');
    await page.click('text=Add class');
    await page
      .locator('[placeholder="Classname"]')
      .nth(6)
      .fill('custom-class-7');
    await page.keyboard.press('Tab');
    await page.keyboard.type('bg-amber-50');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    await delay(1000);
    await page.click('text=Save classes');
    await delay(1000);
  });

  test('check classes', async () => {
    await page.reload();
    await page.click('text=Tailwind');
    await page.click('text=Edit Global Classes');
    await count(page, '[placeholder="Classname"]', 7);
    await count(page, 'text=bg-amber-50', 1);
  });

  test('remove class', async () => {
    await page
      .locator('text=bg-amber-50')
      .locator('..')
      .locator('..')
      .locator('..')
      .locator('..')
      .locator('..')
      .locator('..')
      .locator('..')
      .locator('..')
      .locator('.components-button:not([title="Remove bg-amber-50"])')
      .click();
    await delay(1000);
    await page.click('text=Save classes');
    await delay(1000);
    await page.reload();
    await page.click('text=Tailwind');
    await page.click('text=Edit Global Classes');
    await count(page, '[placeholder="Classname"]', 6);
    await count(page, 'text=bg-amber-50', 0);
  });
});
