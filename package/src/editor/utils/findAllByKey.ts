export const findAllByKey = (obj: object, keyToFind: string) => {
  return (
    Object.entries(obj).reduce(
      (acc, [k, v]) =>
        k === keyToFind
          ? acc.concat(v)
          : typeof v === 'object' && v
            ? acc.concat(findAllByKey(v, keyToFind))
            : acc,
      [],
    ) || []
  );
};
