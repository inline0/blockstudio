import { Page } from '@playwright/test';
import {
  checkStyle,
  count,
  delay,
  save,
  testType,
} from '../../utils/playwright-utils';

testType('classes-tailwind', '"classes":"text-red-500"', () => {
  return [
    {
      description: 'add class',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-classes-tailwind"]');
        await page.fill('.components-form-token-field input', 'bg-');
        await count(page, 'text=bg-amber-100', 1);
      },
    },
    {
      description: 'add bg-blue-500 class',
      testFunction: async (page: Page) => {
        await page.click('[data-type="blockstudio/type-classes-tailwind"]');
        await page.fill('.components-form-token-field input', 'bg-blue-500');
        await page.keyboard.press('Enter');
        await delay(500);
        await count(page, 'text=bg-blue-500', 1);
      },
    },
    {
      description: 'save and check frontend styles',
      testFunction: async (page: Page) => {
        await save(page);
        await delay(2000);
        await page.goto('http://localhost:8888/native-single');
        await delay(1000);
        await checkStyle(
          page,
          '#blockstudio-type-classes-tailwind',
          'color',
          'rgb(239, 68, 68)'
        );
        await checkStyle(
          page,
          '#blockstudio-type-classes-tailwind',
          'backgroundColor',
          'rgb(59, 130, 246)'
        );
        await page.goto(
          'http://localhost:8888/wp-admin/post.php?post=1483&action=edit'
        );
        await page.reload();
        await count(page, '.editor-styles-wrapper', 1);
      },
    },
  ];
});
