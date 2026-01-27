import { makeActionCreator } from '@/utils/makeActionCreator';

export const actions = {
  setCustomClasses: makeActionCreator('SET_CUSTOM_CLASSES', 'customClasses'),
  setTemporaryClasses: makeActionCreator(
    'SET_TEMPORARY_CLASSES',
    'temporaryClasses'
  ),
};
