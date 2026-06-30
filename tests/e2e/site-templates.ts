import { expect, test, Page } from '@playwright/test';
import { login } from './utils/playwright-utils';

const BASE = 'http://localhost:8888';
const TEMPLATE_ID = 'theme//blockstudio-custom';
const PART_ID = 'theme//blockstudio-header';
const FRONTEND_PAGE_SLUG = 'site-template-e2e';

let page: Page;
let nonce = '';

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  await login(page);
  await page.goto(`${BASE}/wp-admin/`);
  nonce = await page.evaluate(
    () =>
      (window as any).wpApiSettings?.nonce ||
      (window as any).blockstudioAdmin?.nonceRest ||
      '',
  );
});

test.afterAll(async () => {
  await resetTemplateToTheme();
  await deleteFrontendPage();
  await page.close();
});

async function api(
  path: string,
  method = 'GET',
  body?: Record<string, unknown>,
) {
  return page.evaluate(
    async ({ path, method, body, nonce }) => {
      const response = await fetch(`/wp-json${path}`, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: body ? JSON.stringify(body) : undefined,
      });
      const text = await response.text();

      return {
        status: response.status,
        body: text ? JSON.parse(text) : null,
      };
    },
    { path, method, body, nonce },
  );
}

async function resetTemplateToTheme() {
  await api(`/wp/v2/templates/${TEMPLATE_ID}?context=edit`, 'POST', {
    source: 'theme',
  }).catch(() => null);
}

async function deleteFrontendPage() {
  const pages = await api(
    `/wp/v2/pages?slug=${FRONTEND_PAGE_SLUG}&status=any&context=edit`,
  ).catch(() => null);

  if (!pages || !Array.isArray(pages.body)) {
    return;
  }

  for (const item of pages.body) {
    await api(`/wp/v2/pages/${item.id}?force=true`, 'DELETE').catch(() => null);
  }
}

test.describe('File-based Site Editor templates', () => {
  test('file-backed template and part are exposed through WordPress REST', async () => {
    await resetTemplateToTheme();

    const template = await api(`/wp/v2/templates/${TEMPLATE_ID}?context=edit`);
    const part = await api(`/wp/v2/template-parts/${PART_ID}?context=edit`);

    expect(template.status).toBe(200);
    expect(template.body.id).toBe(TEMPLATE_ID);
    expect(template.body.slug).toBe('blockstudio-custom');
    expect(template.body.source).toBe('theme');
    expect(template.body.original_source).toBe('theme');
    expect(template.body.content.raw).toContain('Blockstudio Site Template');
    expect(template.body.content.raw).toContain('wp:template-part');

    expect(part.status).toBe(200);
    expect(part.body.id).toBe(PART_ID);
    expect(part.body.slug).toBe('blockstudio-header');
    expect(part.body.area).toBe('header');
    expect(part.body.original_source).toBe('theme');
    expect(part.body.content.raw).toContain('Blockstudio Header Part');
  });

  test('file-backed templates and parts appear in REST collection responses', async () => {
    const templates = await api('/wp/v2/templates?context=edit');
    const parts = await api('/wp/v2/template-parts?context=edit&area=header');

    expect(templates.status).toBe(200);
    expect(parts.status).toBe(200);

    const template = templates.body.find(
      (item: { id: string }) => item.id === TEMPLATE_ID,
    );
    const part = parts.body.find((item: { id: string }) => item.id === PART_ID);

    expect(template).toBeTruthy();
    expect(template.source).toBe('theme');
    expect(template.content.raw).toContain('Blockstudio Site Template');

    expect(part).toBeTruthy();
    expect(part.source).toBe('theme');
    expect(part.area).toBe('header');
  });

  test('Site Editor customizations win and reset returns to file source', async () => {
    await resetTemplateToTheme();

    const customized = await api(
      `/wp/v2/templates/${TEMPLATE_ID}?context=edit`,
      'POST',
      {
        title: 'Customized Blockstudio Template',
        content:
          '<!-- wp:paragraph --><p>Customized Site Editor Template.</p><!-- /wp:paragraph -->',
      },
    );

    expect(customized.status).toBe(200);
    expect(customized.body.source).toBe('custom');
    expect(customized.body.content.raw).toContain(
      'Customized Site Editor Template.',
    );

    const afterSave = await api(`/wp/v2/templates/${TEMPLATE_ID}?context=edit`);

    expect(afterSave.status).toBe(200);
    expect(afterSave.body.source).toBe('custom');
    expect(afterSave.body.content.raw).toContain(
      'Customized Site Editor Template.',
    );
    expect(afterSave.body.content.raw).not.toContain(
      'Blockstudio Site Template',
    );

    const reset = await api(`/wp/v2/templates/${TEMPLATE_ID}?context=edit`, 'POST', {
      source: 'theme',
    });

    expect(reset.status).toBe(200);
    expect(reset.body.source).toBe('theme');
    expect(reset.body.original_source).toBe('theme');
    expect(reset.body.content.raw).toContain('Blockstudio Site Template');
    expect(reset.body.content.raw).not.toContain(
      'Customized Site Editor Template.',
    );
  });

  test('frontend page request uses the file-backed template', async () => {
    await deleteFrontendPage();

    const created = await api('/wp/v2/pages?context=edit', 'POST', {
      title: 'Site Template E2E Page',
      slug: FRONTEND_PAGE_SLUG,
      status: 'publish',
      content:
        '<!-- wp:paragraph --><p>Frontend page body from WordPress content.</p><!-- /wp:paragraph -->',
    });

    expect(created.status).toBe(201);

    await page.goto(`${BASE}/${FRONTEND_PAGE_SLUG}/`);

    await expect(page.getByText('Frontend Site Template E2E')).toBeVisible();
    await expect(
      page.getByText('Frontend page body from WordPress content.'),
    ).toBeVisible();
  });
});
