import { test, expect, type Page } from '@playwright/test';

const PAGE = '/multi-select-test/';

async function openSelect(page: Page) {
  const root = page.locator('[data-bsui-select-root]');
  const trigger = root.locator('[data-bsui-select-trigger]');
  const listbox = root.locator('[role="listbox"]');

  await trigger.click();
  await expect(listbox).toBeVisible();

  const firstOption = root
    .locator('[role="option"]:not([aria-disabled="true"])')
    .first();
  await expect(firstOption).toBeFocused();

  return { root, trigger, listbox };
}

test.describe('bsui/select multiple', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(PAGE);
    await page.waitForSelector('[data-bsui-select-root]');
  });

  test('shows placeholder initially', async ({ page }) => {
    const trigger = page.locator('[data-bsui-select-trigger]');
    await expect(trigger).toContainText('Choose fruits');
  });

  test('listbox has aria-multiselectable', async ({ page }) => {
    const { listbox } = await openSelect(page);
    await expect(listbox).toHaveAttribute('aria-multiselectable', 'true');
  });

  test('clicking option selects it and keeps dropdown open', async ({
    page,
  }) => {
    const { root, listbox } = await openSelect(page);

    const apple = root.locator('[role="option"]').first();
    await apple.click();

    await expect(listbox).toBeVisible();
    await expect(apple).toHaveAttribute('aria-selected', 'true');
  });

  test('can select multiple options', async ({ page }) => {
    const { root } = await openSelect(page);

    const apple = root.locator('[role="option"]').filter({ hasText: 'Apple' });
    const cherry = root
      .locator('[role="option"]')
      .filter({ hasText: 'Cherry' });

    await apple.click();
    await cherry.click();

    await expect(apple).toHaveAttribute('aria-selected', 'true');
    await expect(cherry).toHaveAttribute('aria-selected', 'true');
  });

  test('clicking selected option deselects it', async ({ page }) => {
    const { root } = await openSelect(page);

    const apple = root.locator('[role="option"]').filter({ hasText: 'Apple' });

    await apple.click();
    await expect(apple).toHaveAttribute('aria-selected', 'true');

    await apple.click();
    await expect(apple).toHaveAttribute('aria-selected', 'false');
  });

  test('trigger shows selected labels', async ({ page }) => {
    const { root, trigger } = await openSelect(page);

    await root.locator('[role="option"]').filter({ hasText: 'Apple' }).click();
    await root.locator('[role="option"]').filter({ hasText: 'Banana' }).click();

    await expect(trigger).toContainText('Apple, Banana');
  });

  test('Escape closes multi-select', async ({ page }) => {
    const { listbox } = await openSelect(page);

    await page.keyboard.press('Escape');
    await expect(listbox).toBeHidden();
  });
});
