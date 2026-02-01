import { get } from 'lodash-es';

export const parseTemplate = (templateString: string, values: object) => {
  return templateString.replace(/\{([^}]+)\}/g, (match, path) => {
    return get(values, path, match);
  });
};
