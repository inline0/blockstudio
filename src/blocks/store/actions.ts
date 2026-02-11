import { makeActionCreator } from '@/utils/make-action-creator';

export const actions = {
  setIcons: makeActionCreator('SET_ICONS', 'icons'),
  setMedia: makeActionCreator('SET_MEDIA', 'media'),
  setRichText: makeActionCreator('SET_RICHTEXT', 'richText'),
};
