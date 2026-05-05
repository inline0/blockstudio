import { test, expect, type Page } from '@playwright/test';

const PAGE = '/menu-test/';

async function openMenu(page: Page) {
  const root = page.locator('[data-bsui-menu-root]');
  const trigger = root.locator('[data-bsui-menu-trigger]');
  const triggerButton = trigger.locator('button');
  const popup = root.locator('[role="menu"]');

  await triggerButton.click();
  await expect(popup).toBeVisible();
  await page.waitForTimeout(200);

  return { root, trigger, triggerButton, popup };
}

test.describe('bsui/menu', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(PAGE);
    await page.waitForSelector('[data-bsui-menu-root]');
  });

  test('menu popup is hidden by default', async ({ page }) => {
    const root = page.locator('[data-bsui-menu-root]');
    const popup = root.locator('[role="menu"]');
    await expect(popup).toBeHidden();
  });

  test('trigger has aria-haspopup=menu', async ({ page }) => {
    const trigger = page.locator('[data-bsui-menu-trigger]');
    await expect(trigger).toHaveAttribute('aria-haspopup', 'menu');
  });

  test('clicking trigger opens menu', async ({ page }) => {
    const root = page.locator('[data-bsui-menu-root]');
    const trigger = root.locator('[data-bsui-menu-trigger]');
    const triggerButton = trigger.locator('button');
    const popup = root.locator('[role="menu"]');

    await triggerButton.click();
    await expect(popup).toBeVisible();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  });

  test('menu items have role=menuitem', async ({ page }) => {
    const { root } = await openMenu(page);
    const items = root.locator('[role="menuitem"]');
    await expect(items).toHaveCount(4);
  });

  test('ArrowDown moves focus to next item', async ({ page }) => {
    const { root } = await openMenu(page);
    const items = root.locator('[role="menuitem"]:not([aria-disabled="true"])');

    // First ArrowDown focuses first item, second moves to next
    await page.keyboard.press('ArrowDown');
    await expect(items.first()).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(items.nth(1)).toBeFocused();
  });

  test('ArrowUp moves focus to previous item', async ({ page }) => {
    const { root } = await openMenu(page);
    const items = root.locator('[role="menuitem"]:not([aria-disabled="true"])');

    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowUp');
    await expect(items.first()).toBeFocused();
  });

  test('Home moves to first item', async ({ page }) => {
    const { root } = await openMenu(page);
    const items = root.locator('[role="menuitem"]:not([aria-disabled="true"])');

    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Home');
    await expect(items.first()).toBeFocused();
  });

  test('End moves to last enabled item', async ({ page }) => {
    const { root } = await openMenu(page);
    const items = root.locator('[role="menuitem"]:not([aria-disabled="true"])');

    await page.keyboard.press('End');
    await expect(items.last()).toBeFocused();
  });

  test('Escape closes menu and returns focus', async ({ page }) => {
    const { triggerButton, popup } = await openMenu(page);

    await page.keyboard.press('Escape');
    await expect(popup).toBeHidden();
    await expect(triggerButton).toBeFocused();
  });

  test('clicking outside closes menu', async ({ page }) => {
    const { popup } = await openMenu(page);

    await page.click('body', { position: { x: 10, y: 10 } });
    await expect(popup).toBeHidden();
  });

  test('Enter activates focused item and closes', async ({ page }) => {
    const { popup } = await openMenu(page);

    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    await expect(popup).toBeHidden();
  });
});
