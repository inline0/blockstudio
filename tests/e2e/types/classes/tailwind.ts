import { Page, Frame } from '@playwright/test';
import {
  checkStyle,
  count,
  delay,
  getEditorCanvas,
  save,
  testType,
} from '../../utils/playwright-utils';

testType('classes-tailwind', '"classes":"text-red-500"', () => {
  return [
    {
      description: 'add class',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-classes-tailwind"]');
        await page.fill('.components-form-token-field input', 'bg-');
        await count(page, 'text=bg-amber-100', 1);
      },
    },
    {
      description: 'add bg-blue-500 class',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-classes-tailwind"]');
        await page.fill('.components-form-token-field input', 'bg-blue-500');
        await page.keyboard.press('Enter');
        await delay(500);
        await count(page, 'text=bg-blue-500', 1);
      },
    },
    {
      description: 'search for custom class',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-classes-tailwind"]');
        await page.fill('.components-form-token-field input', 'custom-');
        await count(page, '.components-form-token-field__suggestions-list li', 6);
      },
    },
    {
      description: 'add custom-class-2',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-classes-tailwind"]');
        await page.keyboard.press('Escape');
        await page.fill('.components-form-token-field input', 'custom-class-2');
        await page.keyboard.press('Enter');
        await delay(500);
        await count(page, 'text=custom-class-2', 1);
      },
    },
    {
      description: 'save and check frontend styles including custom class',
      testFunction: async (page: Page, _canvas: Frame) => {
        await save(page);
        await delay(2000);
        await page.goto('http://localhost:8888/native-single/');
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
        await count(page, '#blockstudio-type-classes-tailwind.custom-class-2', 1);
        await page.goto(
          'http://localhost:8888/wp-admin/post.php?post=1483&action=edit'
        );
        await page.reload();
        await getEditorCanvas(page);
      },
    },
  ];
});
