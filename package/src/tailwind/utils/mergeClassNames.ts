import {
  unmanagedPrefixes,
  managedPrefixes,
} from '@/tailwind/data/prefixes';
import { screens as defaultScreens } from '@/tailwind/data/screens';

export const mergeClassNames = (
  currentValues: string,
  newValues: string,
  screens: Record<string, string> = defaultScreens
): string => {
  const screenPrefixes = Object.keys(screens).map((key) => `${key}:`);

  const isManaged = (className: string): boolean => {
    return managedPrefixes.some(
      (prefix) =>
        className.startsWith(prefix) ||
        screenPrefixes.some((screenPrefix) =>
          className.startsWith(screenPrefix + prefix)
        )
    );
  };

  const isUnmanaged = (className: string): boolean => {
    return (
      !isManaged(className) &&
      unmanagedPrefixes.some(
        (prefix) =>
          className.startsWith(prefix) ||
          screenPrefixes.some((screenPrefix) =>
            className.startsWith(screenPrefix + prefix)
          )
      )
    );
  };

  const latestClassNames = new Map<string, string>();

  const addClassName = (className: string) => {
    if (isUnmanaged(className)) {
      latestClassNames.set(className, className);
    } else {
      const split = className.split('-');

      const fullPrefix = isManaged(className)
        ? managedPrefixes.find(
            (prefix) =>
              className.startsWith(prefix) ||
              screenPrefixes.some((screenPrefix) =>
                className.startsWith(screenPrefix + prefix)
              )
          ) + '-'
        : split[0] +
          (split?.[1] === 'x' || split?.[1] === 'y' ? split[1] : '') +
          '-';

      const screenAwarePrefix = screenPrefixes.find((prefix) =>
        className.startsWith(prefix)
      )
        ? screenPrefixes.find((prefix) => className.startsWith(prefix)) +
          fullPrefix
        : fullPrefix;

      if (latestClassNames.has(screenAwarePrefix)) {
        latestClassNames.delete(screenAwarePrefix);
      }
      latestClassNames.set(screenAwarePrefix, className);
    }
  };

  [...currentValues.split(' '), ...newValues.split(' ')].forEach(addClassName);

  return Array.from(latestClassNames.values()).join(' ');
};
