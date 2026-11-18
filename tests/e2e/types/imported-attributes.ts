import { test, expect } from '@playwright/test';
import { parse } from '@wordpress/block-serialization-default-parser';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const name = 'blockstudio/issue-208';
const cases = {
  'flat scalar': { heading: 'Hello', side: 'left' },
  'flat option object': {
    heading: 'Hello',
    side: { value: 'left', label: 'Left' },
  },
  'existing nested scalar': {
    blockstudio: { attributes: { heading: 'Hello', side: 'left' } },
  },
  'existing nested object': {
    blockstudio: {
      attributes: { heading: 'Hello', side: { value: 'left', label: 'Left' } },
    },
  },
  'nested wins over stale flat values': {
    heading: 'Stale',
    side: 'right',
    blockstudio: { attributes: { heading: 'Hello', side: 'left' } },
  },
};

for (const [label, fields] of Object.entries(cases)) {
  test(`imported attributes: ${label} survives editing and saving`, async ({
    page,
    request,
  }) => {
    await login(page);
    await page.goto('/wp-admin/tools.php?page=blockstudio-admin');
    await page.waitForFunction(
      () => (window as any).blockstudioAdminPage?.nonce,
    );
    const nonce = await page.evaluate(
      () => (window as any).blockstudioAdminPage.nonce as string,
    );
    const headers = { 'X-WP-Nonce': nonce };
    const attrs = {
      ...fields,
      className: 'imported-custom',
      align: 'wide',
      anchor: 'imported-anchor',
    };
    const content = `<!-- wp:${name} ${JSON.stringify(attrs)} /-->`;
    const created = await page.request.post('/wp-json/wp/v2/pages', {
      headers,
      data: { title: `Issue 208 ${label}`, status: 'publish', content },
    });
    expect(created.ok()).toBe(true);
    const post = await created.json();

    try {
      for (let pass = 0; pass < 2; pass++) {
        const response = await request.get(post.link);
        expect(response.ok()).toBe(true);
        expect(await response.text()).toContain('heading=Hello side=left');
      }
      await page.goto(`/wp-admin/post.php?post=${post.id}&action=edit`);
      const close = page.locator(
        '.components-modal__header button[aria-label="Close"]',
      );
      if (await close.isVisible()) await close.click();
      let canvas = await getEditorCanvas(page);
      await canvas.locator(`[data-type="${name}"]`).click();
      const blockTab = page.getByRole('tab', { name: 'Block', exact: true });
      if (await blockTab.isVisible()) await blockTab.click();
      const heading = page.locator('[data-id="heading"] input');
      const side = page.locator('[data-id="side"] select');
      await expect(heading).toHaveValue('Hello');
      await expect(side).toHaveValue('left');
      await heading.fill('Edited heading');
      await side.selectOption('right');
      await expect(canvas.locator('[data-issue-208]')).toHaveText(
        'heading=Edited heading side=right',
      );
      await page.evaluate(() =>
        (window as any).wp.data.dispatch('core/editor').savePost(),
      );
      const saved = await page.request.get(
        `/wp-json/wp/v2/pages/${post.id}?context=edit`,
        { headers },
      );
      expect(saved.ok()).toBe(true);
      const raw = (await saved.json()).content.raw;
      // Inspect storage without invoking the hydration filter under test.
      const stored = parse(raw)[0].attrs!;
      expect(raw).toContain('Edited heading');
      expect(stored.blockstudio.attributes.heading).toBe('Edited heading');
      expect(stored.blockstudio.attributes.side.value).toBe('right');
      expect(stored.className).toBe('imported-custom');
      expect(stored.align).toBe('wide');
      expect(stored.anchor).toBe('imported-anchor');
      await page.reload();
      canvas = await getEditorCanvas(page);
      await canvas.locator(`[data-type="${name}"]`).click();
      if (await blockTab.isVisible()) await blockTab.click();
      await expect(heading).toHaveValue('Edited heading');
      await expect(side).toHaveValue('right');
      expect(await (await request.get(post.link)).text()).toContain(
        'heading=Edited heading side=right',
      );
    } finally {
      const removed = await page.request.delete(
        `/wp-json/wp/v2/pages/${post.id}?force=true`,
        { headers },
      );
      expect(removed.ok()).toBe(true);
    }
  });
}
