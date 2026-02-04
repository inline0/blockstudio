import { createReduxStore } from '@wordpress/data';
import { BlockstudioTailwindStore } from '@/types/types';
import { actions } from './actions';
import { selectors } from './selectors';

const DEFAULT_STATE = {
  customClasses: window.blockstudioAdmin.options.tailwind.customClasses || {},
  temporaryClasses: {},
} as BlockstudioTailwindStore;

export const store = createReduxStore('blockstudio/tailwind', {
  reducer(state = DEFAULT_STATE, action) {
    switch (action.type) {
      case 'SET_CUSTOM_CLASSES':
        return {
          ...state,
          customClasses: action.customClasses,
        };
      case 'SET_TEMPORARY_CLASSES':
        return {
          ...state,
          ...action,
        };
    }

    return state;
  },

  actions,
  selectors,
});
