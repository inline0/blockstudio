export const moduleMatcher = (str: string, obj = false, regex = false) => {
  const reg = /\bfrom\s*["']?(blockstudio\/[^"']*)["']/g;

  if (regex) {
    return reg;
  }

  const replacer = (str: string) => {
    return str
      .replaceAll('from"blockstudio', 'from "blockstudio')
      .replaceAll("from'blockstudio", "from 'blockstudio");
  };

  const getter = (str: string) => {
    str = replacer(str);

    const nameVersion = str
      .replace('from ', '')
      .slice(1, -1)
      .replace('blockstudio/', '');

    const lastAtIndex = nameVersion.lastIndexOf('@');
    const name = nameVersion.substring(0, lastAtIndex);
    const version = nameVersion.substring(lastAtIndex + 1);

    return {
      name,
      version,
      nameVersion,
    };
  };

  if (obj) {
    return getter(str);
  }

  str = replacer(str);

  str?.match(reg)?.forEach((item) => {
    const obj = getter(item);
    str = str.replace(
      item.replace('from ', ''),
      `"https://esm.sh/${obj.nameVersion}?bundle"`,
    );
  });

  return str;
};
