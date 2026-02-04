import { BlockstudioBlock } from '@/types/types';

export const dispatch = (
  block: BlockstudioBlock,
  event: string,
  detail = {},
) => {
  const loadedEvent = new CustomEvent(
    `blockstudio${block?.name ? `/${block.name}` : ''}/${event}`,
    {
      detail,
    },
  );
  return document.dispatchEvent(loadedEvent);
};
