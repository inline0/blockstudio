export const uniqueObjectArrayBy = (arr: [], key: string | number) => {
  const seen = new Set();
  return arr.filter((item) => {
    const k = item[key];
    return seen.has(k) ? false : seen.add(k);
  });
};
