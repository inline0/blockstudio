export const isAllowedToRender = (
  searchedBlocks: string[],
  paths: string | string[],
) => {
  let render = 0;

  if (searchedBlocks) {
    for (let i = 0; i < paths.length; i++) {
      if (render === 1) break;
      if (searchedBlocks.some((inner) => paths[i].includes(inner))) {
        render = 1;
      }
    }
  }

  return !((render === 0 && searchedBlocks.length) || !searchedBlocks);
};
