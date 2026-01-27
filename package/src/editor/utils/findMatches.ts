export const findMatches = (regex: RegExp, str: string, matches = []) => {
  const res = regex.exec(str);
  if (res && matches.push(res)) {
    findMatches(regex, str, matches);
  }
  return matches;
};
