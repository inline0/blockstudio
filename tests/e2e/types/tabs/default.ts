import { Page } from '@playwright/test';
import {
  count,
  saveAndReload,
  testType,
  text,
} from '../../utils/playwright-utils';

testType(
  'tabs',
  '"toggle":false,"toggle2":false,"text":false,"text2":false,"group_text":false,"group_text2":false',
  () => {
    return [
      {
        description: 'only two tabs',
        testFunction: async (page: Page) => {
          await count(page, 'text=Tab 1', 1);
          await count(page, 'text=Override tab', 1);
          await count(page, 'text=Tab 2', 0);
        },
      },
      {
        description: 'change first tab',
        testFunction: async (page: Page) => {
          await page
            .locator('.blockstudio-fields__field--text input')
            .nth(0)
            .fill('test');
          await page
            .locator('.blockstudio-fields__field--text input')
            .nth(1)
            .fill('test');
          await text(
            page,
            '"toggle":false,"toggle2":false,"text":"test","text2":"test","group_text":false,"group_text2":false'
          );
        },
      },
      {
        description: 'toggle',
        testFunction: async (page: Page) => {
          await page
            .locator('.blockstudio-fields__field--toggle input')
            .check();
          await text(
            page,
            '"toggle":true,"toggle2":false,"text":"test","text2":"test","group_text":false,"group_text2":false'
          );
          await count(page, 'text=Toggle 2', 1);
        },
      },
      {
        description: 'change second tab',
        testFunction: async (page: Page) => {
          await page.click('text=Override tab');
          await page
            .locator('.blockstudio-fields__field--text input')
            .nth(0)
            .fill('test');
          await page
            .locator('.blockstudio-fields__field--text input')
            .nth(1)
            .fill('test');
          await text(
            page,
            '"toggle":true,"toggle2":false,"text":"test","text2":"test","group_text":"test","group_text2":"test"'
          );
          await saveAndReload(page);
        },
      },
      {
        description: 'check',
        testFunction: async (page: Page) => {
          await text(
            page,
            '"toggle":true,"toggle2":false,"text":"test","text2":"test","group_text":"test","group_text2":"test"'
          );
        },
      },
    ];
  }
);
