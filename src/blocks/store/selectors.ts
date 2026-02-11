import { BlockstudioBlockStore } from '@/types/types';

export const selectors = {
  getIcons(state: BlockstudioBlockStore | null = null) {
    const { icons } = state || {};
    return icons;
  },
  getMedia(state: BlockstudioBlockStore | null = null) {
    const { media } = state || {};
    return media;
  },
  getRichText(state: BlockstudioBlockStore | null = null) {
    const { richText } = state || {};
    return richText;
  },
};
