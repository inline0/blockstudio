import { BlockstudioBlockStore } from '@/type/types';

export const selectors = {
  getEditor(state: BlockstudioBlockStore = null) {
    const { editor } = state;
    return editor;
  },
  getEditorFocus(state: BlockstudioBlockStore = null) {
    const { editorFocus } = state;
    return editorFocus;
  },
  getIcons(state: BlockstudioBlockStore = null) {
    const { icons } = state;
    return icons;
  },
  getInitialLoad(state: BlockstudioBlockStore = null) {
    const { initialLoad } = state;
    return initialLoad;
  },
  getInitialLoadRendered(state: BlockstudioBlockStore = null) {
    const { initialLoadRendered } = state;
    return initialLoadRendered;
  },
  getMedia(state: BlockstudioBlockStore = null) {
    const { media } = state;
    return media;
  },
  getRichText(state: BlockstudioBlockStore = null) {
    const { richText } = state;
    return richText;
  },
  isLoaded(state: BlockstudioBlockStore = null) {
    const { isLoaded } = state;
    return isLoaded;
  },
};
