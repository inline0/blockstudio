import { BlockstudioTailwindStore } from '@/types/types';

export const selectors = {
  getTemporaryClasses(state: BlockstudioTailwindStore | null = null) {
    const { temporaryClasses } = state || {};
    return temporaryClasses;
  },
};
