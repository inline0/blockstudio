export const limitName = (str: string) => {
  return str
    .replace(' ', '-')
    .replace(/[^a-zA-Z-_/]/g, '')
    .toLowerCase();
};
