import { Page } from '@playwright/test';
import { count, testType, text } from '../../../playwright-utils';

testType(
  'group',
  '"text":"Override test","toggle":false,"toggle2":false,"group_text":"Override ID test","group_toggle3":false,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"Added test"',
  () => {
    return [
      {
        description: 'without id',
        testFunction: async (page: Page) => {
          await page.fill(
            '.blockstudio-fields__field.blockstudio-fields__field--group input[type="text"]',
            'test'
          );
          await text(
            page,
            '"text":"test","toggle":false,"toggle2":false,"group_text":"Override ID test","group_toggle3":false,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"Added test"'
          );
        },
      },
      {
        description: 'condition without id',
        testFunction: async (page: Page) => {
          await page.check(
            '.blockstudio-fields__field.blockstudio-fields__field--group input[type="checkbox"]'
          );
          await text(
            page,
            '"text":"test","toggle":true,"toggle2":false,"group_text":"Override ID test","group_toggle3":false,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"Added test"'
          );
          await count(page, 'text=Toggle 2', 1);
        },
      },
      {
        description: 'with id',
        testFunction: async (page: Page) => {
          await page
            .locator(
              '.blockstudio-fields__field.blockstudio-fields__field--group input[type="text"]'
            )
            .nth(1)
            .fill('test');
          await text(
            page,
            '"text":"test","toggle":true,"toggle2":false,"group_text":"test","group_toggle3":false,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"Added test"'
          );
        },
      },
      {
        description: 'condition with id',
        testFunction: async (page: Page) => {
          await page
            .locator(
              '.blockstudio-fields__field.blockstudio-fields__field--group input[type="checkbox"]'
            )
            .nth(2)
            .check();
          await text(
            page,
            '"text":"test","toggle":true,"toggle2":false,"group_text":"test","group_toggle3":true,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"Added test"'
          );
          await count(page, 'text=Toggle 4', 1);
        },
      },
      {
        description: 'added element from override',
        testFunction: async (page: Page) => {
          await page
            .locator(
              '.blockstudio-fields__field.blockstudio-fields__field--group input[type="text"]'
            )
            .nth(4)
            .fill('test');
          await text(
            page,
            '"text":"test","toggle":true,"toggle2":false,"group_text":"test","group_toggle3":true,"group_toggle4":false,"group_text1":false,"group_text2":false,"addedText":"test"'
          );
        },
      },
      {
        description: 'with style',
        testFunction: async (page: Page) => {
          await text(
            page,
            'style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;"'
          );
        },
      },
      {
        description: 'with disabled toggles',
        testFunction: async (page: Page) => {
          await count(
            page,
            '.blockstudio-fields__field--group >> nth=2 .blockstudio-fields__field-toggle',
            0
          );
        },
      },
    ];
  }
);
