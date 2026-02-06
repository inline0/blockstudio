import { expect, Page, Frame } from '@playwright/test';
import {
  delay,
  openSidebar,
  saveAndReload,
  testType,
  text,
} from '../../utils/playwright-utils';

// Key default values to check
const defaultChecks = [
  `"defaultNumberBothWrongDefault":false`,
  `"defaultValueLabel":["Three"]`,
  `"defaultNumberArray":[3]`,
  `"defaultNumberArrayBoth":[{"value":3,"label":3}]`,
  `"defaultValueStringNumber":["2"]`,
  `"defaultValueStringNumberBoth":[{"value":"3","label":"3"}]`,
  `"populateQueryIdBefore":[1388]`,
  `"populateCustom":["three"]`,
  `"populateCustomArrayBeforeBoth":[{"value":"three","label":"three"}]`,
  `"populateCustomOnly":["custom-1"]`,
];

const valuesCheckbox = [
  // 0 - defaultNumberBothWrongDefault
  { index: [1], checked: 4, data: `"defaultNumberBothWrongDefault":[{"value":1,"label":"One"},{"value":2,"label":"Two"},{"value":3,"label":"Three"}]` },
  // 1 - defaultValueLabel
  { index: [3, 2], checked: 4, data: `"defaultValueLabel":["One","Two","Three"]` },
  // 2 - defaultNumberArray
  { index: [2, 1], checked: 3, data: `"defaultNumberArray":[1,2,3]` },
  // 3 - defaultNumberArrayBoth
  { index: [1, 2], checked: 3, data: `"defaultNumberArrayBoth":[{"value":1,"label":1},{"value":2,"label":2},{"value":3,"label":3}]` },
  // 4 - defaultValueStringNumber
  { index: [2, 1], checked: 1, data: `"defaultValueStringNumber":["1"]` },
  // 5 - defaultValueStringNumberBoth
  { index: [1, 2, 3], checked: 2, data: `"defaultValueStringNumberBoth":[{"value":"1","label":"1"},{"value":"2","label":"2"}]` },
  // 6 - populateQuery
  { index: [3, 1], checked: 3, data: `"populateQuery":["one","three",{"ID":1388` },
  // 7 - populateQueryIdBefore
  { index: [8, 6, 1], checked: 4, data: `"populateQueryIdBefore":[1483,1388,"one","three"]` },
  // 8 - populateCustom
  { index: [1, 5], checked: 3, data: `"populateCustom":["one","three","custom-2"]` },
  // 9 - populateCustomArrayBeforeBoth
  { index: [5, 2], checked: 3, data: `"populateCustomArrayBeforeBoth":[{"value":"custom-2","label":"custom-2"},{"value":"two","label":"two"},{"value":"three","label":"three"}]` },
  // 10 - populateCustomOnly
  { index: [3, 2], checked: 3, data: `"populateCustomOnly":["custom-1","custom-2","custom-3"]` },
  // 11 - populateOnlyQuery
  { index: [2, 1], checked: 1, data: `"populateOnlyQuery":[{"ID":` },
  // 12 - populateOnlyQueryUser (5 users displayed, default 644)
  // Click checkboxes 3 (admin) and 2 (Aaron Kessler/704) to check them
  { index: [3, 2], checked: 3, data: `"populateOnlyQueryUser":[{"data":{"ID":"704"`, skip: true },
  // 13 - populateOnlyQueryTerm (terms vary by install - just check for valid term data)
  // Terms are dynamically populated, check for term_id in result
  { index: [3, 4], checked: 3, data: `"populateOnlyQueryTerm":[{"term_id":`, skip: true },
];

testType('checkbox', false, () => {
  return [
    // Check key default values
    ...defaultChecks.map((check, i) => ({
      description: `check default ${i}`,
      testFunction: async (_page: Page, canvas: Frame) => {
        await text(canvas, check);
      },
    })),
    // Change attributes (click checkboxes)
    ...valuesCheckbox.map((item, index) => ({
      description: `change attribute ${index}`,
      testFunction: async (page: Page, _canvas: Frame) => {
        await delay(500);
        for (const checkbox of item.index) {
          await page
            .locator(`.blockstudio-fields .components-panel__body:nth-of-type(${index + 1}) .components-checkbox-control:nth-of-type(${checkbox}) .components-checkbox-control__input`)
            .click();
        }
      },
    })),
    // Check data after changes
    ...valuesCheckbox.map((item, index) => ({
      description: `check data ${index}`,
      testFunction: async (_page: Page, canvas: Frame) => {
        await text(canvas, item.data);
      },
    })),
    {
      description: 'save and reload',
      testFunction: async (page: Page, _canvas: Frame) => {
        await saveAndReload(page);
      },
    },
    {
      description: 'select block after reload',
      testFunction: async (page: Page, canvas: Frame) => {
        // Click on the block to select it
        await canvas.click('[data-type^="blockstudio/type-checkbox"]');
        await openSidebar(page);
      },
    },
    // Check persisted checkbox counts (skip items with dynamic data)
    ...valuesCheckbox.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check persisted checked ${index}`,
        testFunction: async (page: Page, _canvas: Frame) => {
          const els = page.locator(`.blockstudio-fields .components-panel__body:nth-of-type(${index + 1}) .components-checkbox-control__input:checked`);
          await expect(els).toHaveCount(item.checked);
        },
      }]
    ),
    // Check persisted data (skip items with dynamic data)
    ...valuesCheckbox.flatMap((item, index) =>
      item.skip ? [] : [{
        description: `check persisted data ${index}`,
        testFunction: async (_page: Page, canvas: Frame) => {
          await text(canvas, item.data);
        },
      }]
    ),
  ];
});
