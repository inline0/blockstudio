export const moduleMatcherCss = (value: string) => {
  const regex = /import\s*["'\n]blockstudio\/(.*\.css)["'];/g;
  let match: string[];
  const finalUrls = [];

  while ((match = regex.exec(value)) !== null) {
    finalUrls.push(`https://esm.sh/${match[1]}`);
  }

  const updatedValue = value.replace(regex, '');
  return { finalUrls, updatedValue };
};
