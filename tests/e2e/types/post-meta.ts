import { count, save, testType } from '../utils/playwright-utils';

testType('post-meta', false, () => {
  return [
    {
      description: 'block refreshes on save',
      testFunction: async (page: any) => {
        await save(page);
        await count(page, '.blockstudio-test__block--refreshed', 1);
      },
    },
  ];
});
