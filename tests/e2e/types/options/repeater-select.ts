import { expect, Page } from '@playwright/test';
import {
  count,
  delay,
  openSidebar,
  saveAndReload,
  testType,
  text,
} from '../../utils/playwright-utils';

// Key default values to check (without timestamps/full objects)
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
  // 0 - defaultNumberBothWrongDefault
  { defaultValue: '', index: 4, valueAfter: '3', data: `"defaultNumberBothWrongDefault":{"value":3,"label":"Three"}` },
  // 1 - defaultValueLabel
  { defaultValue: 'three', index: 2, valueAfter: 'two', data: `"defaultValueLabel":"Two"` },
  // 2 - defaultNumberArray
  { defaultValue: '3', index: 2, valueAfter: '2', data: `"defaultNumberArray":2` },
  // 3 - defaultNumberArrayBoth
  { defaultValue: '3', index: 2, valueAfter: '2', data: `"defaultNumberArrayBoth":{"value":2,"label":2}` },
  // 4 - defaultValueStringNumber
  { defaultValue: '2', index: 2, valueAfter: '2', data: `"defaultValueStringNumber":"2"` },
  // 5 - defaultValueStringNumberBoth
  { defaultValue: '3', index: 3, valueAfter: '3', data: `"defaultValueStringNumberBoth":{"value":"3","label":"3"}` },
  // 6 - populateQuery
  { defaultValue: '1388', index: 1, valueAfter: 'one', data: `"populateQuery":"one"` },
  // 7 - populateQueryIdBefore
  { defaultValue: '1388', index: 1, valueAfter: '1483', data: `"populateQueryIdBefore":1483` },
  // 8 - populateCustom
  { defaultValue: 'three', index: 5, valueAfter: 'custom-2', data: `"populateCustom":"custom-2"` },
  // 9 - populateCustomArrayBeforeBoth
  { defaultValue: 'three', index: 5, valueAfter: 'two', data: `"populateCustomArrayBeforeBoth":{"value":"two","label":"two"}` },
  // 10 - populateCustomOnly
  { defaultValue: 'custom-1', index: 2, valueAfter: 'custom-2', data: `"populateCustomOnly":"custom-2"` },
  // 11 - populateOnlyQuery
  { defaultValue: '1388', index: 2, valueAfter: '1388', data: `"populateOnlyQuery":{"ID":1388` },
  // 12 - populateOnlyQueryUser (default is 644, select index 2 for 704)
  { defaultValue: '644', index: 2, valueAfter: '704', data: `"populateOnlyQueryUser":{"data":{"ID":"704"`, skip: true },
  // 13 - populateOnlyQueryTerm (terms vary by install)
  { defaultValue: '6', index: 3, valueAfter: '14', data: `"populateOnlyQueryTerm":{"term_id":`, skip: true },
];

testType('repeater-select', false, () => {
  return [
    // Check key default values
    ...defaultChecks.map((check, i) => ({
      description: `check default ${i}`,
      testFunction: async (page: Page) => {
        await text(page, check);
      },
    })),
    {
      description: 'reset select',
      testFunction: async (page: Page) => {
        await page.click('[data-type^="blockstudio/type-repeater-select"]');
        await openSidebar(page);
        await count(page, 'text=Reset me', 0);
        const el = page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-select-control__input`)
          .nth(1);
        await el.selectOption({ index: valuesSelect[1].index - 1 });
        await count(page, 'text=Reset me', 1);
        await page.click('text=Reset me');
        await count(page, 'text=Reset me', 0);
        await text(page, `"defaultValueLabel":"Three"`);
      },
    },
    ...valuesSelect.map((item, index) => ({
      description: `default input ${index}`,
      testFunction: async (page: Page) => {
        const el = page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-select-control__input`)
          .nth(index);
        await expect(el).toHaveValue(item.defaultValue);
      },
    })),
    ...valuesSelect.map((item, index) => ({
      description: `change attribute ${index}`,
      testFunction: async (page: Page) => {
        await delay(500);
        const el = page
          .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-select-control__input`)
          .nth(index);
        await el.selectOption({ index: item.index - 1 });
      },
    })),
    // Check data (skip items with dynamic data)
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check data ${index}`,
        testFunction: async (page: Page) => {
          await text(page, item.data);
        },
      }]
    ),
    {
      description: 'save and reload',
      testFunction: async (page: Page) => {
        await saveAndReload(page);
      },
    },
    {
      description: 'select block after reload',
      testFunction: async (page: Page) => {
        await page.click('[data-type^="blockstudio/type-repeater-select"]');
        await openSidebar(page);
      },
    },
    // Check persisted values (skip items with dynamic data)
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check persisted value ${index}`,
        testFunction: async (page: Page) => {
          const el = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--select .components-select-control__input`)
            .nth(index);
          await expect(el).toHaveValue(item.valueAfter);
        },
      }]
    ),
    // Check persisted data (skip items with dynamic data)
    ...valuesSelect.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check persisted data ${index}`,
        testFunction: async (page: Page) => {
          await text(page, item.data);
        },
      }]
    ),
  ];
});
