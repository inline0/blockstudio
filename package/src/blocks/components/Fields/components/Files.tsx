import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { Button, PanelRow, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { result } from 'lodash-es';
import { Base } from '@/blocks/components/Base';
import { Cards } from '@/blocks/components/List/Cards';
import { useMedia } from '@/blocks/hooks/useMedia';
import { selectors } from '@/blocks/store/selectors';
import { BlockstudioAttribute } from '@/type/block';
import { BlockstudioBlockAttributes } from '@/type/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

const imageSizes = window.blockstudioAdmin?.data.imageSizes ?? [];

export const Files = ({
  attributes,
  change,
  config,
  disable,
  inRepeater,
  item,
  v,
  repeaterId,
  ...rest
}: {
  attributes: BlockstudioBlockAttributes;
  change: (val: string[] | string, media?: boolean, size?: string) => void;
  config: boolean;
  disable: (id: string) => void;
  inRepeater: boolean;
  item: BlockstudioAttribute;
  v: string[];
  repeaterId: string;
}) => {
  useMedia(v);
  const media = useSelect(
    (select) => (select('blockstudio/blocks') as typeof selectors).getMedia(),
    [],
  );

  const Btn = ({ open }) => {
    return (
      <Button variant="secondary" onClick={open}>
        {__(
          item?.textMediaButton || 'Open Media Library',
          item?.textMediaButton as unknown as boolean,
        )}
      </Button>
    );
  };

  return (
    <Base>
      {!config && item.size && (
        <SelectControl
          css={css({
            marginBottom: 'var(--blockstudio-fields-space)',
          })}
          label={__('Media size')}
          options={[...imageSizes, 'full'].map((e) => {
            return {
              value: e,
              label: e,
            };
          })}
          value={
            (!inRepeater
              ? attributes?.blockstudio?.attributes?.[item.id + '__size']
              : result(
                  attributes,
                  `blockstudio.attributes.${repeaterId}__size`,
                )) || 'full'
          }
          onChange={(value: string | string[]) => change(value, true, '__size')}
        />
      )}
      <Cards
        {...{
          attributes,
          change,
          disable,
          inRepeater,
          item,
          media,
          v,
        }}
        style={{ margin: '16px 0' }}
        ids={v}
      />
      <PanelRow>
        {config ? (
          <Btn open={undefined} />
        ) : (
          <MediaUploadCheck>
            <MediaUpload
              {...rest}
              value={v as unknown as number}
              modalClass={` blockstudio-function__media-min-${
                item?.min || 0
              } blockstudio-function__media-max-${item?.max || 'unlimited'}`}
              onSelect={(media) =>
                media?.id
                  ? change(
                      (
                        media as unknown as {
                          id: string;
                        }
                      ).id,
                    )
                  : media.length === 1
                    ? change(media[0].id)
                    : change(media?.map((e) => e.id))
              }
              render={({ open }) => <Btn {...{ open }} />}
            />
          </MediaUploadCheck>
        )}
      </PanelRow>
    </Base>
  );
};
