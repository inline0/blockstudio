export const isNumeric = (num: string | number) => {
  return (
    (typeof num === 'number' ||
      (typeof num === 'string' && num.trim() !== '')) &&
    !isNaN(num as number)
  );
};
