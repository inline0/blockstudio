import { makeActionCreator } from '@/utils/make-action-creator';

export const actions = {
  setIcons: makeActionCreator('SET_ICONS', 'icons'),
  setInitialLoad: makeActionCreator('SET_INITIAL_LOAD', 'initialLoad'),
  setInitialLoadRendered: makeActionCreator(
    'SET_INITIAL_LOAD_RENDERED',
    'initialLoadRendered',
  ),
  setIsLoaded: makeActionCreator('SET_IS_LOADED', 'isLoaded'),
  setMedia: makeActionCreator('SET_MEDIA', 'media'),
  setRichText: makeActionCreator('SET_RICHTEXT', 'richText'),
};
