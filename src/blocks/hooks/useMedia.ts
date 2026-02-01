import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { selectors } from '@/blocks/store/selectors';

export const useMedia = (v: string[] | string) => {
  const { setMedia } = useDispatch('blockstudio/blocks');
  const media = useSelect(
    (select) => (select('blockstudio/blocks') as typeof selectors).getMedia(),
    []
  );

  useEffect(() => {
    if (v?.length || v) {
      const existingIds = Object.keys(media).map((item) => `${item}`);

      const newIds = ((v.length ? v : [v]) as string[]).filter(
        (item: string) => !existingIds.includes(`${item}`)
      );
      if (!newIds.length) return;

      apiFetch({
        path: `/wp/v2/media?include=${((v.length ? v : [v]) as string[]).join(
          ','
        )}&per_page=${(v.length ? v : [v]).length}`,
      }).then(
        (
          res: {
            id: number;
            mime_type: string;
          }[]
        ) => {
          res.forEach((item) => {
            if (!media[item.id]) {
              setMedia({
                ...media,
                [item.id]: item,
              });
            }
          });
        }
      );
    }
  }, [media, v]);
};
