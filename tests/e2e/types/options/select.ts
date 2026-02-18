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
  `"defaultValueLabel":"Three"`,
  `"defaultNumberArray":3`,
  `"defaultNumberArrayBoth":{"value":3,"label":3}`,
  `"defaultValueStringNumber":"2"`,
  `"defaultValueStringNumberBoth":{"value":"3","label":"3"}`,
  `"populateQueryIdBefore":1388`,
  `"populateCustom":"three"`,
  `"populateCustomArrayBeforeBoth":{"value":"three","label":"three"}`,
  `"populateCustomOnly":"custom-1"`,
];

const valuesSelect = [
  { defaultValue: '', index: 4, valueAfter: '3', data: `"defaultNumberBothWrongDefault":{"value":3,"label":"Three"}` },
  { defaultValue: 'three', index: 2, valueAfter: 'two', data: `"defaultValueLabel":"Two"` },
  { defaultValue: '3', index: 2, valueAfter: '2', data: `"defaultNumberArray":2` },
  { defaultValue: '3', index: 2, valueAfter: '2', data: `"defaultNumberArrayBoth":{"value":2,"label":2}` },
  { defaultValue: '2', index: 2, valueAfter: '2', data: `"defaultValueStringNumber":"2"` },
  { defaultValue: '3', index: 3, valueAfter: '3', data: `"defaultValueStringNumberBoth":{"value":"3","label":"3"}` },
  { defaultValue: '1388', index: 1, valueAfter: 'one', data: `"populateQuery":"one"` },
  { defaultValue: '1388', index: 1, valueAfter: '1483', data: `"populateQueryIdBefore":1483` },
  { defaultValue: 'three', index: 5, valueAfter: 'custom-2', data: `"populateCustom":"custom-2"` },
  { defaultValue: 'three', index: 5, valueAfter: 'two', data: `"populateCustomArrayBeforeBoth":{"value":"two","label":"two"}` },
  { defaultValue: 'custom-1', index: 2, valueAfter: 'custom-2', data: `"populateCustomOnly":"custom-2"` },
  { defaultValue: '1388', index: 2, valueAfter: '1388', data: `"populateOnlyQuery":{"ID":1388` },
  { defaultValue: '644', index: 2, valueAfter: '704', data: `"populateOnlyQueryUser":{"data":{"ID":"704"`, skip: true },
  { defaultValue: '6', index: 3, valueAfter: '14', data: `"populateOnlyQueryTerm":{"term_id":`, skip: true },
];

testType('select', false, () => {
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
        await canvas.click('[data-type^="blockstudio/type-select"]');
        await openSidebar(page);
        await count(page, 'text=Reset me', 0);
        const el = page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-select-control__input`)
          .nth(1);
        await el.selectOption({ index: valuesSelect[1].index - 1 });
        await count(page, 'text=Reset me', 1);
        await page.click('text=Reset me');
        await count(page, 'text=Reset me', 0);
        await text(canvas, `"defaultValueLabel":"Three"`);
      },
    },
    ...valuesSelect.map((item, index) => ({
      description: `default input ${index}`,
      testFunction: async (page: Page, _canvas: Frame) => {
        const el = page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-select-control__input`)
          .nth(index);
        await expect(el).toHaveValue(item.defaultValue);
      },
    })),
    ...valuesSelect.map((item, index) => ({
      description: `change attribute ${index}`,
      testFunction: async (page: Page, _canvas: Frame) => {
        await delay(500);
        const el = page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-select-control__input`)
          .nth(index);
        await el.selectOption({ index: item.index - 1 });
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
        await canvas.click('[data-type^="blockstudio/type-select"]');
        await openSidebar(page);
      },
    },
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check persisted value ${index}`,
        testFunction: async (page: Page, _canvas: Frame) => {
          const el = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-select-control__input`)
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
