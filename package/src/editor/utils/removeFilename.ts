export const removeFilename = (path: string) => {
  const filePattern = /\/\w+\.\w+\/?$/;
  return path.replace(filePattern, '');
};
