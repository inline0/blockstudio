import { makeActionCreator } from '@/utils/make-action-creator';

export const actions = {
  setCustomClasses: makeActionCreator('SET_CUSTOM_CLASSES', 'customClasses'),
  setTemporaryClasses: makeActionCreator(
    'SET_TEMPORARY_CLASSES',
    'temporaryClasses'
  ),
};
