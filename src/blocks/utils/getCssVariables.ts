export const getCssVariables = (allVariables: Set<string>, css: string) => {
  const regex = /--([\w-]+)(?=:)/g;
  let match: string[];
  while ((match = regex.exec(css)) !== null) {
    allVariables.add(match[1]);
  }
};
