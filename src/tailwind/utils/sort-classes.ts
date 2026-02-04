import { classes } from '@/tailwind/data/classes';
import { screens } from '@/tailwind/data/screens';

const screenOrder = Object.keys(screens).reduce(
  (acc: Record<string, number>, key, index) => {
    acc[key] = index;
    return acc;
  },
  {},
);

const classOrder = classes.reduce(
  (acc: Record<string, number>, className, index) => {
    acc[className] = index;
    return acc;
  },
  {},
);

export const sortClasses = (value: string) => {
  return value.split(' ').sort((a, b) => {
    const aParts = a.split(':');
    const bParts = b.split(':');
    const aPrefix = aParts.length === 2 ? aParts[0] : '';
    const bPrefix = bParts.length === 2 ? bParts[0] : '';
    const aClass = aParts[aParts.length - 1];
    const bClass = bParts[bParts.length - 1];

    if (aPrefix && bPrefix) {
      const orderDiff = screenOrder[aPrefix] - screenOrder[bPrefix];
      if (orderDiff !== 0) return orderDiff;
    } else if (aPrefix || bPrefix) {
      return aPrefix ? 1 : -1;
    }

    return classOrder[aClass] - classOrder[bClass];
  });
};
