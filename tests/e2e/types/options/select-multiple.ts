import { expect, Page, Frame } from '@playwright/test';
import {
  count,
  delay,
  openSidebar,
  saveAndReload,
  testType,
  text,
} from '../../utils/playwright-utils';
const defaultChecks = [
  `"defaultNumberBothWrongDefault":false`,
  `"defaultValueLabel":["Three"]`,
  `"defaultNumberArray":["3"]`,
  `"defaultValueStringNumber":["2"]`,
  `"defaultValueStringNumberBoth":[{"value":"3","label":"3"}]`,
  `"populateQueryIdBefore":[1388]`,
  `"populateCustom":["three"]`,
  `"populateCustomArrayBeforeBoth":[{"value":"three","label":"three"}]`,
  `"populateCustomOnly":["custom-1"]`,
];

const valuesSelect = [
  { defaultMultiple: [], data: `"defaultNumberBothWrongDefault":[{"value":1,"label":"One"}]` },
  { defaultMultiple: ['Three'], data: `"defaultValueLabel":["Three","One"]` },
  { defaultMultiple: ['3'], data: `"defaultNumberArray":["3","1"]` },
  { defaultMultiple: ['1', '3'], data: `"defaultNumberArrayBoth":[{"value":"1","label":"1"},{"value":"3","label":"3"},{"value":"2","label":"2"}]` },
  { defaultMultiple: ['Two'], data: `"defaultValueStringNumber":["2","1"]` },
  { defaultMultiple: ['3'], data: `"defaultValueStringNumberBoth":[{"value":"3","label":"3"},{"value":"1","label":"1"}]` },
  { defaultMultiple: ['Native Render'], data: `"populateQuery":[{"ID":1388` },
  { defaultMultiple: ['native-render'], data: `"populateQueryIdBefore":[1388,1483]` },
  { defaultMultiple: ['Three'], data: `"populateCustom":["three","one"]` },
  { defaultMultiple: ['three'], data: `"populateCustomArrayBeforeBoth":[{"value":"three","label":"three"},{"value":"custom-1","label":"custom-1"}]` },
  { defaultMultiple: ['custom-1'], data: `"populateCustomOnly":["custom-1","custom-2"]` },
  { defaultMultiple: ['Native Render'], data: `"populateOnlyQuery":[{"ID":1388` },
  { defaultMultiple: ['admin'], data: `"populateOnlyQueryUser":[{"data":{"ID":"1"`, skip: true },
  { defaultMultiple: ['Test Category 6'], data: `"populateOnlyQueryTerm":[{"term_id":`, skip: true },
];

testType('select-multiple', false, () => {
  return [
    ...defaultChecks.map((check, i) => ({
      description: `check default ${i}`,
      testFunction: async (_page: Page, canvas: Frame) => {
        await text(canvas, check);
      },
    })),
    {
      description: 'reset select',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type^="blockstudio/type-select-multiple"]');
        await openSidebar(page);
        await count(page, 'text=Reset me', 0);
        const button = page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-form-token-field__input`)
          .nth(1);
        await button.click();
        await page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-form-token-field__suggestion`)
          .nth(0)
          .click();
        await delay(2000);
        await count(page, 'text=Reset me', 1);
        await page.click('text=Reset me');
        await count(page, 'text=Reset me', 0);
        await text(canvas, `"defaultValueLabel":["Three"]`);
      },
    },
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `default input ${index}`,
        testFunction: async (page: Page, _canvas: Frame) => {
          const el = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select`)
            .nth(index);
          for (const value of item.defaultMultiple) {
            const j = item.defaultMultiple.indexOf(value);
            const card = el.locator(`[data-rfd-draggable-context-id]`).nth(j);
            await expect(card).toHaveText(value);
          }
        },
      }]
    ),
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `change attribute ${index}`,
        testFunction: async (page: Page, _canvas: Frame) => {
          const button = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-form-token-field__input`)
            .nth(index);
          await button.click();
          await page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-form-token-field__suggestion`)
            .nth(0)
            .click();
          await delay(2000);
        },
      }]
    ),
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check data ${index}`,
        testFunction: async (_page: Page, canvas: Frame) => {
          await text(canvas, item.data);
        },
      }]
    ),
    {
      description: 'save and reload',
      testFunction: async (page: Page, _canvas: Frame) => {
        await saveAndReload(page);
      },
    },
    {
      description: 'select block after reload',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type^="blockstudio/type-select-multiple"]');
        await openSidebar(page);
      },
    },
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check persisted cards ${index}`,
        testFunction: async (page: Page, _canvas: Frame) => {
          const el = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select`)
            .nth(index);
          const cards = el.locator('[data-rfd-draggable-context-id]');
          await expect(cards).toHaveCount(item.defaultMultiple.length + 1);
        },
      }]
    ),
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check persisted data ${index}`,
        testFunction: async (_page: Page, canvas: Frame) => {
          await text(canvas, item.data);
        },
      }]
    ),
  ];
});
