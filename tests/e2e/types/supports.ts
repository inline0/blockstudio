import { expect, Page } from '@playwright/test';
import { click, saveAndReload, testType, text } from '../utils/playwright-utils';

testType('classes', '"classes":"class-1 class-2"', () => {
  return [
    {
      description: 'blockstudio block',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-classes"]');
        await editor.getByRole('button', { name: 'Advanced' }).click();
        await editor.getByLabel('HTML Anchor').fill('anchor-test');
        await editor.getByLabel('Additional CSS class(es)').fill('class-test');
        await text(editor, '"anchor":"anchor-test","className":"class-test"');
        await saveAndReload(editor);
      },
    },
    {
      description: 'check blockstudio block',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-classes"]');
        await text(editor, '"anchor":"anchor-test","className":"class-test"');
        await saveAndReload(editor);
      },
    },
    {
      description: 'core block',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="blockstudio/type-classes"]');
        await editor.locator('body').press('Enter');
        await editor.locator('body').pressSequentially('/heading', { delay: 100 });
        await editor.locator('body').press('Enter');
        await editor.locator('body').pressSequentially('Heading test', { delay: 100 });
        await click(editor, '[data-type="core/heading"]');
        await editor.getByRole('button', { name: 'Advanced' }).click();
        await editor.getByLabel('HTML Anchor').fill('anchor-test');
        await editor.getByLabel('Additional CSS class(es)').fill('class-test');
        await saveAndReload(editor);
      },
    },
    {
      description: 'check core block',
      testFunction: async (editor: Page) => {
        await click(editor, '[data-type="core/heading"]');
        await editor.getByRole('button', { name: 'Advanced' }).click();
        await expect(editor.getByLabel('HTML Anchor')).toHaveValue('anchor-test');
        await expect(editor.getByLabel('Additional CSS class(es)')).toHaveValue(
          'class-test'
        );
      },
    },
  ];
});
