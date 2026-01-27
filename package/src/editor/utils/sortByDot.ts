export const sortByDot = (arr: string[]) => {
  arr.sort((a, b) => {
    const aHasDotKey = Object.keys(a).some((key) => key === '.');
    const bHasDotKey = Object.keys(b).some((key) => key === '.');

    if (aHasDotKey && !bHasDotKey) {
      return -1;
    } else if (!aHasDotKey && bHasDotKey) {
      return 1;
    } else {
      return 0;
    }
  });

  return arr;
};
