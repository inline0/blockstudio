type JsonValue = string | number | boolean | null | JsonValue[] | { [key: string]: JsonValue };

export const replaceEmptyStringsWithFalse = <T extends JsonValue>(obj: T): T | false => {
  if (Array.isArray(obj)) {
    return obj.map(replaceEmptyStringsWithFalse) as T;
  }
  if (typeof obj === 'object' && obj !== null) {
    return Object.entries(obj).reduce((acc, [key, value]) => {
      acc[key] = replaceEmptyStringsWithFalse(value);
      return acc;
    }, {} as Record<string, JsonValue | false>) as T;
  }
  if (obj === '') {
    return false;
  }
  return obj;
};
