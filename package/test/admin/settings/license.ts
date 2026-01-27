import { Page, test } from '@playwright/test';
import { count, pEditor } from '../../../playwright-utils';

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await pEditor(browser);
});

test.afterAll(async () => {
  await page.close();
});

test('license', async () => {
  await page.goto(
    'https://fabrikat.local/streamline/wp-admin/admin.php?page=blockstudio'
  );
  await page.locator('text=Deactivate License').click();
  await count(page, 'text=License activated.', 0);
  await page.fill(
    'input[placeholder="Your license key"]',
    'd2533fd93fdc1dbead90acf0b98e9db9'
  );
  await page.locator('.components-button.is-secondary').click();
  await count(page, 'text=License activated', 1);
});
