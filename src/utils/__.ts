import { __ as _ } from '@wordpress/i18n';

export const __ = (str: string, noDomain = false) => {
  if (!noDomain) {
    return _(str, 'blockstudio');
  }
  return _(str);
};
