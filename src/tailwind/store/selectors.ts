import { BlockstudioTailwindStore } from '@/types/types';

export const selectors = {
  getCustomClasses(state: BlockstudioTailwindStore | null = null) {
    const { customClasses } = state || {};
    return customClasses;
  },
  getTemporaryClasses(state: BlockstudioTailwindStore | null = null) {
    const { temporaryClasses } = state || {};
    return temporaryClasses;
  },
};
