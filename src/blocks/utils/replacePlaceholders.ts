import { cloneDeep, get } from 'lodash-es';

export const replacePlaceholders = (obj: object, sourceObj: object) => {
  const newObj = cloneDeep(obj) as Record<string, unknown>;
  const isObject = (val: unknown) =>
    val && typeof val === 'object' && !Array.isArray(val);

  const replace = (currentObj: Record<string, unknown>) => {
    Object.keys(currentObj).forEach((key) => {
      if (isObject(currentObj[key])) {
        replace(currentObj[key] as Record<string, unknown>);
      } else if (typeof currentObj[key] === 'string') {
        const matches = (currentObj[key] as string).match(/^\{(.*)\}$/);
        if (matches && matches[1]) {
          currentObj[key] = get(sourceObj, matches[1]);
        }
      }
    });
  };

  replace(newObj);
  return newObj;
};
