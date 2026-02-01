export const getCssClasses = (allClasses: Set<string>, css: string) => {
  const regex = /\.([\w-]+)/g;
  let match: RegExpExecArray | null;
  while ((match = regex.exec(css)) !== null) {
    allClasses.add(match[1]);
  }
};
