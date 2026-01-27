import { BlockstudioTailwindStore } from '@/type/types';

export const selectors = {
  getCustomClasses(state: BlockstudioTailwindStore = null) {
    const { customClasses } = state;
    return customClasses;
  },
  getTemporaryClasses(state: BlockstudioTailwindStore = null) {
    const { temporaryClasses } = state;
    return temporaryClasses;
  },
};
