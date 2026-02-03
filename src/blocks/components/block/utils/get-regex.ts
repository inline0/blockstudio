export const getRegex = (elementName: string, flag = 's') => {
  return new RegExp(`<${elementName}\\s*(.*?)\\s*\\/?\\>`, flag);
};
