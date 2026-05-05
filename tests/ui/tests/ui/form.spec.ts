import { test, expect, type Page } from '@playwright/test';

const PAGE = '/form-test/';

type MockHandler = (data: Record<string, unknown>) => unknown;

declare global {
  interface Window {
    bs?: {
      db: () => {
        create: (data: Record<string, unknown>) => Promise<unknown>;
      };
      fn?: () => Promise<unknown>;
    };
    _testResolve?: (value: unknown) => void;
  }
}

function mockBs(page: Page, handler: MockHandler) {
  return page.evaluate((h) => {
    const fn = new Function('return ' + h)() as MockHandler;
    window.bs = {
      db: () => ({
        create: (data) => Promise.resolve(fn(data)),
      }),
    };
  }, handler.toString());
}

test.describe('bsui/form', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(PAGE);
    await page.waitForSelector('[data-bsui-form-root]');
  });

  // Rendering

  test('renders form element', async ({ page }) => {
    const form = page.locator('[data-bsui-form-root]');
    await expect(form).toHaveCount(1);
    await expect(form).toHaveAttribute('data-wp-interactive', 'bsui/form');
  });

  test('renders field labels', async ({ page }) => {
    const labels = page.locator('label');
    await expect(labels).toHaveCount(2);
    await expect(labels.first()).toContainText('Username');
    await expect(labels.nth(1)).toContainText('Email');
  });

  test('renders named inputs', async ({ page }) => {
    const username = page.locator('input[name="username"]');
    const email = page.locator('input[name="email"]');
    await expect(username).toHaveCount(1);
    await expect(email).toHaveCount(1);
  });

  test('field errors are hidden initially', async ({ page }) => {
    const errors = page.locator('[role="alert"]');
    for (let i = 0; i < (await errors.count()); i++) {
      await expect(errors.nth(i)).toBeHidden();
    }
  });

  test('success message is hidden initially', async ({ page }) => {
    await expect(page.locator('.bs-ui-form-success')).toBeHidden();
  });

  // Submission with validation errors

  test('displays field-level validation errors from server', async ({
    page,
  }) => {
    await mockBs(page, () => ({
      code: 'blockstudio_db_validation',
      message: 'Validation failed.',
      data: {
        status: 400,
        errors: {
          username: ['Must be at least 3 characters.'],
          email: ['Must be a valid email address.'],
        },
      },
    }));

    await page.locator('[data-bsui-form-root]').evaluate((form) => {
      (form as HTMLFormElement).requestSubmit();
    });

    const usernameError = page
      .locator('[data-bsui-field-root]')
      .first()
      .locator('[role="alert"]');
    const emailError = page
      .locator('[data-bsui-field-root]')
      .nth(1)
      .locator('[role="alert"]');

    await expect(usernameError).toBeVisible();
    await expect(usernameError).toHaveText('Must be at least 3 characters.');
    await expect(emailError).toBeVisible();
    await expect(emailError).toHaveText('Must be a valid email address.');
  });

  test('displays form-level error message', async ({ page }) => {
    await mockBs(page, () => ({
      code: 'blockstudio_db_validation',
      message: 'Validation failed.',
      data: { status: 400, errors: {} },
    }));

    await page.locator('[data-bsui-form-root]').evaluate((form) => {
      (form as HTMLFormElement).requestSubmit();
    });

    const formError = page.locator('.bs-ui-form-error');
    await expect(formError).toBeVisible();
    await expect(formError).toHaveText('Validation failed.');
  });

  test('adds invalid class to fields with errors', async ({ page }) => {
    await mockBs(page, () => ({
      code: 'blockstudio_db_validation',
      message: 'Validation failed.',
      data: {
        status: 400,
        errors: { email: ['Invalid email.'] },
      },
    }));

    await page.locator('[data-bsui-form-root]').evaluate((form) => {
      (form as HTMLFormElement).requestSubmit();
    });

    const usernameField = page.locator('[data-bsui-field-root]').first();
    const emailField = page.locator('[data-bsui-field-root]').nth(1);

    await expect(emailField).toHaveClass(/bs-ui-invalid/);
    await expect(usernameField).not.toHaveClass(/bs-ui-invalid/);
  });

  // Clearing errors

  test('typing in a field clears its error', async ({ page }) => {
    await mockBs(page, () => ({
      code: 'blockstudio_db_validation',
      message: 'Validation failed.',
      data: {
        status: 400,
        errors: {
          username: ['Required.'],
          email: ['Required.'],
        },
      },
    }));

    await page.locator('[data-bsui-form-root]').evaluate((form) => {
      (form as HTMLFormElement).requestSubmit();
    });

    const usernameError = page
      .locator('[data-bsui-field-root]')
      .first()
      .locator('[role="alert"]');
    const emailError = page
      .locator('[data-bsui-field-root]')
      .nth(1)
      .locator('[role="alert"]');

    await expect(usernameError).toBeVisible();
    await expect(emailError).toBeVisible();

    await page.locator('input[name="username"]').fill('john');

    await expect(usernameError).toBeHidden();
    await expect(emailError).toBeVisible();
  });

  // Success

  test('shows success message on successful submission', async ({ page }) => {
    await mockBs(page, (data) => ({ id: 1, ...data }));

    await page.locator('input[name="username"]').fill('john');
    await page.locator('input[name="email"]').fill('john@test.com');

    await page.locator('[data-bsui-form-root]').evaluate((form) => {
      (form as HTMLFormElement).requestSubmit();
    });

    await expect(page.locator('.bs-ui-form-success')).toBeVisible();
    await expect(page.locator('.bs-ui-form-success')).toHaveText('Thank you!');
  });

  test('hides form fields on success', async ({ page }) => {
    await mockBs(page, (data) => ({ id: 1, ...data }));

    await page.locator('input[name="username"]').fill('john');
    await page.locator('input[name="email"]').fill('john@test.com');

    await page.locator('[data-bsui-form-root]').evaluate((form) => {
      (form as HTMLFormElement).requestSubmit();
    });

    await expect(page.locator('input[name="username"]')).toBeHidden();
  });

  // Submitting state

  test('adds submitting class during submission', async ({ page }) => {
    await page.evaluate(() => {
      let resolve!: (value: unknown) => void;
      const pending = new Promise((r) => {
        resolve = r;
      });
      window._testResolve = resolve;
      window.bs = {
        db: () => ({
          create: () => pending,
        }),
        fn: () => pending,
      };
    });

    const form = page.locator('[data-bsui-form-root]');
    await form.evaluate((f) => {
      (f as HTMLFormElement).requestSubmit();
    });

    await expect(form).toHaveClass(/bs-ui-submitting/);

    await page.evaluate(() => {
      window._testResolve?.({ id: 1 });
    });

    await expect(form).not.toHaveClass(/bs-ui-submitting/);
  });

  // Error only for matching field

  test('only shows error for field that has a server error', async ({
    page,
  }) => {
    await mockBs(page, () => ({
      code: 'blockstudio_db_validation',
      message: 'Validation failed.',
      data: {
        status: 400,
        errors: {
          email: ['Invalid email.'],
        },
      },
    }));

    await page.locator('[data-bsui-form-root]').evaluate((form) => {
      (form as HTMLFormElement).requestSubmit();
    });

    const usernameError = page
      .locator('[data-bsui-field-root]')
      .first()
      .locator('[role="alert"]');

    await expect(usernameError).toBeHidden();
  });
});
