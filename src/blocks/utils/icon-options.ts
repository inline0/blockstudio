export type IconOption = { value: string; label: string };

export const getIconOptions = (
  set: Record<string, string> | undefined,
  selected?: string | null,
): IconOption[] => {
  if (set) {
    return Object.keys(set).map((file) => {
      const name = file.replace('.svg', '');
      return { value: name, label: name };
    });
  }

  // Until the set is fetched, list only the stored icon so its name shows in the closed field.
  return selected ? [{ value: selected, label: selected }] : [];
};
