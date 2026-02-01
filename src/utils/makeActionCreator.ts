export const makeActionCreator = (type: string, ...argNames: string[]) => {
  return (...args: string[]) => {
    const action: Record<string, string> = { type };
    argNames.forEach((_arg, index) => {
      action[argNames[index]] = args[index];
    });
    return action;
  };
};
