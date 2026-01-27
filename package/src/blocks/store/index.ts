import { createReduxStore } from '@wordpress/data';
import { actions } from './actions';
import { selectors } from './selectors';
import { BlockstudioBlockStore } from '@/type/types';

const DEFAULT_STATE = <BlockstudioBlockStore>{
  editor: {},
  editorFocus: 0,
  icons: {},
  initialLoad: {},
  initialLoadRendered: {},
  isLoaded: false,
  media: {},
  repeaters: {},
  richText: {},
};

export const store = createReduxStore('blockstudio/blocks', {
  reducer(state = DEFAULT_STATE, action) {
    switch (action.type) {
      case 'SET_ICONS':
        return {
          ...state,
          icons: {
            ...state.icons,
            ...action.icons,
          },
        };
      case 'SET_INITIAL_LOAD':
        return {
          ...state,
          initialLoad: {
            ...state.initialLoad,
            ...action.initialLoad,
          },
        };
      case 'SET_INITIAL_LOAD_RENDERED':
        return {
          ...state,
          initialLoadRendered: {
            ...state.initialLoadRendered,
            ...action.initialLoadRendered,
          },
        };
      case 'SET_IS_LOADED':
        return {
          ...state,
          isLoaded: action.isLoaded,
        };
      case 'SET_EDITOR': {
        return {
          ...state,
          editor: action.editor,
        };
      }
      case 'SET_EDITOR_FOCUS': {
        return {
          ...state,
          editorFocus: state.editorFocus + 1,
        };
      }
      case 'SET_MEDIA':
        return {
          ...state,
          media: {
            ...state.media,
            ...action.media,
          },
        };
      case 'SET_RICHTEXT':
        return {
          ...state,
          richText: {
            ...state.richText,
            ...action.richText,
          },
        };
    }

    return state;
  },

  actions,
  selectors,
});
