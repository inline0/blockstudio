import { Any } from '@/typetypes';

export const replaceEmptyStringsWithFalse = (obj: Any): Any => {
  if (Array.isArray(obj)) {
    return obj.map(replaceEmptyStringsWithFalse);
  }
  if (typeof obj === 'object' && obj !== null) {
    return Object.entries(obj).reduce((acc, [key, value]) => {
      acc[key] = replaceEmptyStringsWithFalse(value);
      return acc;
    }, {} as Any);
  }
  if (obj === '') {
    return false;
  }
  return obj;
};
