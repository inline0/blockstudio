import { expect, Page } from '@playwright/test';
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
  // 0 - defaultNumberBothWrongDefault (allowNull - no default)
  { defaultStylised: '', index: 4, valueStylised: 'Three', data: `"defaultNumberBothWrongDefault":{"value":3,"label":"Three"}` },
  // 1 - defaultValueLabel
  { defaultStylised: 'Three', index: 2, valueStylised: 'Two', data: `"defaultValueLabel":"Two"` },
  // 2 - defaultNumberArray
  { defaultStylised: '3', index: 2, valueStylised: '2', data: `"defaultNumberArray":2` },
  // 3 - defaultNumberArrayBoth
  { defaultStylised: '3', index: 2, valueStylised: '2', data: `"defaultNumberArrayBoth":{"value":2,"label":2}` },
  // 4 - defaultValueStringNumber
  { defaultStylised: 'Two', index: 2, valueStylised: 'Two', data: `"defaultValueStringNumber":"2"` },
  // 5 - defaultValueStringNumberBoth
  { defaultStylised: '3', index: 3, valueStylised: '3', data: `"defaultValueStringNumberBoth":{"value":"3","label":"3"}` },
  // 6 - populateQuery
  { defaultStylised: 'Native Render', index: 1, valueStylised: 'One', data: `"populateQuery":"one"` },
  // 7 - populateQueryIdBefore
  { defaultStylised: 'native-render', index: 1, valueStylised: 'native-single', data: `"populateQueryIdBefore":1483` },
  // 8 - populateCustom
  { defaultStylised: 'Three', index: 5, valueStylised: 'Custom 2', data: `"populateCustom":"custom-2"` },
  // 9 - populateCustomArrayBeforeBoth
  { defaultStylised: 'three', index: 5, valueStylised: 'two', data: `"populateCustomArrayBeforeBoth":{"value":"two","label":"two"}` },
  // 10 - populateCustomOnly
  { defaultStylised: 'custom-1', index: 2, valueStylised: 'custom-2', data: `"populateCustomOnly":"custom-2"` },
  // 11 - populateOnlyQuery
  { defaultStylised: 'Native Render', index: 2, valueStylised: 'Native Render', data: `"populateOnlyQuery":{"ID":1388` },
  // 12 - populateOnlyQueryUser (default is Test User 644)
  { defaultStylised: 'Test User 644', index: 2, valueStylised: 'Aaron Kessler', data: `"populateOnlyQueryUser":{"data":{"ID":"704"`, skip: true },
  // 13 - populateOnlyQueryTerm (terms vary by install)
  { defaultStylised: 'Test Category 6', index: 3, valueStylised: 'fabrikat', data: `"populateOnlyQueryTerm":{"term_id":`, skip: true },
];

testType('repeater-radio-button-group', false, () => {
  return [
    // Check key default values
    ...defaultChecks.map((check, i) => ({
      description: `check default ${i}`,
      testFunction: async (page: Page) => {
        await text(page, check);
      },
    })),
    // Check default button group inputs (starting from index 1, skip items with dynamic data)
    ...valuesSelect.slice(1).flatMap((item, i) => {
      const index = i + 1;
      return item.skip ? [] : [{
        description: `default input ${index}`,
        testFunction: async (page: Page) => {
          await page.click('[data-type^="blockstudio/type-repeater-radio-button-group"]');
          await openSidebar(page);
          const el = page
            .locator(`.blockstudio-fields .blockstudio-fields__field--radio .components-button.is-primary`)
            .nth(index - 1);
          await expect(el).toHaveText(item.defaultStylised);
        },
      }];
    }),
    // Change attributes - repeater uses field selector, not panel body
    ...valuesSelect.map((item, index) => ({
      description: `change attribute ${index}`,
      testFunction: async (page: Page) => {
        await delay(500);
        await page
          .locator(`.blockstudio-fields .blockstudio-fields__field--radio`)
          .nth(index)
          .locator(`.components-button:nth-of-type(${item.index === 4 ? 3 : item.index})`)
          .click();
      },
    })),
    // Check data after changes (skip items with dynamic data)
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
        await page.click('[data-type^="blockstudio/type-repeater-radio-button-group"]');
        await openSidebar(page);
      },
    },
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
