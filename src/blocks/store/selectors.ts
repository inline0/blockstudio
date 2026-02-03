import { BlockstudioBlockStore } from '@/types/types';

export const selectors = {
  getIcons(state: BlockstudioBlockStore | null = null) {
    const { icons } = state || {};
    return icons;
  },
  getInitialLoad(state: BlockstudioBlockStore | null = null) {
    const { initialLoad } = state || {};
    return initialLoad;
  },
  getInitialLoadRendered(state: BlockstudioBlockStore | null = null) {
    const { initialLoadRendered } = state || {};
    return initialLoadRendered;
  },
  getMedia(state: BlockstudioBlockStore | null = null) {
    const { media } = state || {};
    return media;
  },
  getRichText(state: BlockstudioBlockStore | null = null) {
    const { richText } = state || {};
    return richText;
  },
  isLoaded(state: BlockstudioBlockStore | null = null) {
    const { isLoaded } = state || {};
    return isLoaded;
  },
};
