export const getFilename = (str: string) => {
  return str?.split(/(\\|\/)/g).pop();
};
