import { cloneDeep, get } from 'lodash-es';

export const replacePlaceholders = (obj: object, sourceObj: object) => {
  const newObj = cloneDeep(obj);
  const isObject = (val: any) =>
    val && typeof val === 'object' && !Array.isArray(val);

  const replace = (currentObj: object) => {
    Object.keys(currentObj).forEach((key) => {
      if (isObject(currentObj[key])) {
        replace(currentObj[key]);
      } else if (typeof currentObj[key] === 'string') {
        const matches = currentObj[key].match(/^\{(.*)\}$/);
        if (matches && matches[1]) {
          currentObj[key] = get(sourceObj, matches[1]);
        }
      }
    });
  };

  replace(newObj);
  return newObj;
};
