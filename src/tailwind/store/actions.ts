import { makeActionCreator } from '@/utils/make-action-creator';

export const actions = {
  setTemporaryClasses: makeActionCreator(
    'SET_TEMPORARY_CLASSES',
    'temporaryClasses',
  ),
};
