import { expect, Page, Frame } from '@playwright/test';
import {
  count,
  openSidebar,
  saveAndReload,
  testType,
  text,
} from '../../utils/playwright-utils';
const defaultChecks = [
  `"defaultNumberBothWrongDefault":false`,
  `"defaultValueLabel":"Three"`,
  `"defaultNumberArray":"3"`,
  `"defaultNumberArrayBoth":{"value":"3","label":"3"}`,
  `"defaultValueStringNumber":"2"`,
  `"defaultValueStringNumberBoth":{"value":"3","label":"3"}`,
  `"populateQueryIdBefore":1388`,
  `"populateCustom":"three"`,
  `"populateCustomArrayBeforeBoth":{"value":"three","label":"three"}`,
  `"populateCustomOnly":"custom-1"`,
];

const valuesSelect = [
  { defaultValue: '', indexStylised: 2, valueAfter: 'Three', data: `"defaultNumberBothWrongDefault":{"value":3,"label":"Three"}` },
  { defaultValue: 'Three', indexStylised: 1, valueAfter: 'Two', data: `"defaultValueLabel":"Two"` },
  { defaultValue: '3', indexStylised: 1, valueAfter: '2', data: `"defaultNumberArray":"2"` },
  { defaultValue: '3', indexStylised: 1, valueAfter: '2', data: `"defaultNumberArrayBoth":{"value":"2","label":"2"}` },
  { defaultValue: 'Two', indexStylised: 1, valueAfter: 'Two', data: `"defaultValueStringNumber":"2"` },
  { defaultValue: '3', indexStylised: 2, valueAfter: '3', data: `"defaultValueStringNumberBoth":{"value":"3","label":"3"}` },
  { defaultValue: 'Native Render', indexStylised: 0, valueAfter: 'One', data: `"populateQuery":"one"` },
  { defaultValue: 'native-render', indexStylised: 0, valueAfter: 'native-single', data: `"populateQueryIdBefore":1483` },
  { defaultValue: 'Three', indexStylised: 4, valueAfter: 'Custom 2', data: `"populateCustom":"custom-2"` },
  { defaultValue: 'three', indexStylised: 4, valueAfter: 'two', data: `"populateCustomArrayBeforeBoth":{"value":"two","label":"two"}` },
  { defaultValue: 'custom-1', indexStylised: 1, valueAfter: 'custom-2', data: `"populateCustomOnly":"custom-2"` },
  { defaultValue: 'Native Render', indexStylised: 1, valueAfter: 'Native Render', data: `"populateOnlyQuery":{"ID":1388` },
  { defaultValue: 'Test User 644', indexStylised: 1, valueAfter: 'Aaron Kessler', data: `"populateOnlyQueryUser":{"data":{"ID":"704"`, skip: true },
  { defaultValue: 'Test Category 6', indexStylised: 2, valueAfter: 'fabrikat', data: `"populateOnlyQueryTerm":{"term_id":`, skip: true },
];

testType('select-stylised', false, () => {
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
        await canvas.click('[data-type^="blockstudio/type-select-stylised"]');
        await openSidebar(page);
        await count(page, 'text=Reset me', 0);
        const button = page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-combobox-control__input`)
          .nth(1);
        await button.click();
        await page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-form-token-field__suggestion`)
          .nth(valuesSelect[1].indexStylised)
          .click();
        await count(page, 'text=Reset me', 1);
        await page.click('text=Reset me');
        await count(page, 'text=Reset me', 0);
        await text(canvas, `"defaultValueLabel":"Three"`);
      },
    },
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `default input ${index}`,
        testFunction: async (page: Page, _canvas: Frame) => {
          const el = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-combobox-control__input`)
            .nth(index);
          await expect(el).toHaveValue(item.defaultValue);
        },
      }]
    ),
    ...valuesSelect.map((item, index) => ({
      description: `change attribute ${index}`,
      testFunction: async (page: Page, _canvas: Frame) => {
        const button = page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-combobox-control__input`)
          .nth(index);
        await button.click();
        await page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-form-token-field__suggestion`)
          .nth(item.indexStylised)
          .click();
      },
    })),
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
        await canvas.click('[data-type^="blockstudio/type-select-stylised"]');
        await openSidebar(page);
      },
    },
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check persisted value ${index}`,
        testFunction: async (page: Page, _canvas: Frame) => {
          const el = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-combobox-control__input`)
            .nth(index);
          await expect(el).toHaveValue(item.valueAfter);
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
