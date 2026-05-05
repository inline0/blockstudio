import { test, expect, type Locator } from '@playwright/test';

const PAGE = '/confirmation-delete/';

// The dialog uses data-wp-bind--hidden with an inline display:flex style.
// The inline style overrides the hidden attribute's display:none, so the dialog
// overlay is always visually rendered. Clicks must go through element.click()
// to bypass the overlay, and dialog state is checked via the hidden attribute.

async function jsClick(locator: Locator) {
  await locator.evaluate((el) => (el as HTMLElement).click());
}

test.describe('interaction/confirmation-delete', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(PAGE);
    await page.waitForSelector('[data-interaction-confirmation-delete]');
    await page.waitForLoadState('networkidle');
  });

  test('3 items visible initially', async ({ page }) => {
    const items = page.locator('[data-testid="project-item"]');
    await expect(items).toHaveCount(3);
  });

  test('renders correct project names', async ({ page }) => {
    const names = page.locator('[data-testid="project-name"]');
    await expect(names.nth(0)).toHaveText('Project A');
    await expect(names.nth(1)).toHaveText('Project B');
    await expect(names.nth(2)).toHaveText('Project C');
  });

  test('dialog is hidden initially', async ({ page }) => {
    const dialog = page.locator('[data-testid="confirm-dialog"]');
    await expect(dialog).toHaveAttribute('hidden', '');
  });

  test('clicking delete opens dialog with correct name', async ({ page }) => {
    await jsClick(page.locator('[data-testid="delete-btn"]').nth(1));
    await page.waitForTimeout(300);

    const dialog = page.locator('[data-testid="confirm-dialog"]');
    await expect(dialog).not.toHaveAttribute('hidden', '');
    await expect(page.locator('[data-testid="dialog-message"]')).toContainText(
      'Project B',
    );
  });

  test('cancel preserves all items', async ({ page }) => {
    await jsClick(page.locator('[data-testid="delete-btn"]').nth(1));
    await page.waitForTimeout(300);

    await page.locator('[data-testid="cancel-btn"]').click();
    await page.waitForTimeout(300);

    const dialog = page.locator('[data-testid="confirm-dialog"]');
    await expect(dialog).toHaveAttribute('hidden', '');
    await expect(page.locator('[data-testid="project-item"]')).toHaveCount(3);
  });

  test('confirm removes the item', async ({ page }) => {
    await jsClick(page.locator('[data-testid="delete-btn"]').nth(1));
    await page.waitForTimeout(300);

    await page.locator('[data-testid="confirm-btn"]').click();
    await page.waitForTimeout(300);

    const dialog = page.locator('[data-testid="confirm-dialog"]');
    await expect(dialog).toHaveAttribute('hidden', '');
    await expect(page.locator('[data-testid="project-item"]')).toHaveCount(2);
    const names = page.locator('[data-testid="project-name"]');
    await expect(names.nth(0)).toHaveText('Project A');
    await expect(names.nth(1)).toHaveText('Project C');
  });

  test('cancel then confirm flow works correctly', async ({ page }) => {
    // Cancel first
    await jsClick(page.locator('[data-testid="delete-btn"]').nth(1));
    await page.waitForTimeout(300);
    await page.locator('[data-testid="cancel-btn"]').click();
    await page.waitForTimeout(300);
    await expect(page.locator('[data-testid="project-item"]')).toHaveCount(3);

    // Now confirm
    await jsClick(page.locator('[data-testid="delete-btn"]').nth(1));
    await page.waitForTimeout(300);
    await page.locator('[data-testid="confirm-btn"]').click();
    await page.waitForTimeout(300);
    await expect(page.locator('[data-testid="project-item"]')).toHaveCount(2);

    const names = page.locator('[data-testid="project-name"]');
    const texts = await names.allTextContents();
    expect(texts).not.toContain('Project B');
  });

  test('delete dialog shows correct name for different items', async ({
    page,
  }) => {
    await jsClick(page.locator('[data-testid="delete-btn"]').nth(0));
    await page.waitForTimeout(300);
    await expect(page.locator('[data-testid="dialog-message"]')).toContainText(
      'Project A',
    );
    await page.locator('[data-testid="cancel-btn"]').click();
    await page.waitForTimeout(300);

    await jsClick(page.locator('[data-testid="delete-btn"]').nth(2));
    await page.waitForTimeout(300);
    await expect(page.locator('[data-testid="dialog-message"]')).toContainText(
      'Project C',
    );
  });

  test('can delete all items one by one', async ({ page }) => {
    // Delete first item (Project A)
    await jsClick(page.locator('[data-testid="delete-btn"]').first());
    await page.waitForTimeout(300);
    await page.locator('[data-testid="confirm-btn"]').click();
    await page.waitForTimeout(300);
    await expect(page.locator('[data-testid="project-item"]')).toHaveCount(2);

    // Delete first remaining item (Project B)
    await jsClick(page.locator('[data-testid="delete-btn"]').first());
    await page.waitForTimeout(300);
    await page.locator('[data-testid="confirm-btn"]').click();
    await page.waitForTimeout(300);
    await expect(page.locator('[data-testid="project-item"]')).toHaveCount(1);

    // Delete last item (Project C)
    await jsClick(page.locator('[data-testid="delete-btn"]').first());
    await page.waitForTimeout(300);
    await page.locator('[data-testid="confirm-btn"]').click();
    await page.waitForTimeout(300);
    await expect(page.locator('[data-testid="project-item"]')).toHaveCount(0);
  });
});
