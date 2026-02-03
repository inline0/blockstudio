import { javascript } from '@codemirror/lang-javascript';
import CodeMirror from '@uiw/react-codemirror';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import {
  Button,
  DropdownMenu,
  MenuGroup,
  MenuItem,
  TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { trash, moreHorizontal, link, image } from '@wordpress/icons';
import { cloneDeep, get, isArray, set, unset } from 'lodash-es';
import { LinkModal } from '@/blocks/components/fields/components/link';
import { Card } from '@/blocks/components/list/cards';
import { useMedia } from '@/blocks/hooks/use-media';
import { selectors } from '@/blocks/store/selectors';
import { style } from '@/const/index';
import { BlockstudioBlockAttributes } from '@/types/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

type AttributePair = {
  attribute: string;
  value: string;
  data?: {
    media?: number;
  };
};

export const Attribute = ({
  attributes,
  index,
  keyName,
  link: linkControl,
  media: mediaControl,
  pair,
  setAttributes,
}: {
  attributes: BlockstudioBlockAttributes;
  index: number;
  keyName: string;
  link: boolean;
  media: boolean;
  pair: AttributePair;
  setAttributes: (attributes: BlockstudioBlockAttributes) => void;
}) => {
  const media = useSelect(
    (select) => (select('blockstudio/blocks') as typeof selectors).getMedia(),
    [],
  );
  const [showLinkModal, setShowLinkModal] = useState(false);

  const handleAttributeChange = (
    index: number,
    type: string,
    value: string,
  ) => {
    const clone = cloneDeep(attributes);
    set(clone, `${keyName}[${index}].${type}`, value);
    setAttributes({
      ...attributes,
      blockstudio: clone.blockstudio,
    });
  };

  const removeAttributePair = (index: number) => {
    const clone = cloneDeep(attributes);
    unset(clone, `${keyName}[${index}]`);
    setAttributes({
      ...attributes,
      blockstudio: clone.blockstudio,
    });
  };

  const handleMediaChange = (m: { id: number; url: string }) => {
    const clone = cloneDeep(attributes);
    set(clone, `${keyName}[${index}].value`, m.url);
    set(clone, `${keyName}[${index}].data.media`, m.id);
    setAttributes({
      ...attributes,
      blockstudio: clone.blockstudio,
    });
  };

  const handleMediaDelete = () => {
    const clone = cloneDeep(attributes);
    set(clone, `${keyName}[${index}].value`, '');
    set(clone, `${keyName}[${index}].data`, {});
    setAttributes({
      ...attributes,
      blockstudio: clone.blockstudio,
    });
  };

  if (!pair) return null;

  return (
    <>
      <div>
        <div
          css={css({
            display: 'grid',
            gridTemplateColumns: '1fr auto',

            '.components-base-control': {
              marginBottom: '0',
            },
          })}
        >
          <TextControl
            placeholder={__('Attribute')}
            value={pair.attribute || ''}
            onChange={(val) => handleAttributeChange(index, 'attribute', val)}
            __nextHasNoMarginBottom
          />
          <DropdownMenu
            css={css({
              '.components-button': {
                width: '32px',
                height: '32px',
              },
            })}
            icon={moreHorizontal}
            label="More"
          >
            {({ onClose }) => (
              <>
                {(mediaControl || linkControl) && (
                  <MenuGroup>
                    {linkControl && (
                      <MenuItem
                        icon={link}
                        onClick={() => {
                          setShowLinkModal(true);
                          onClose();
                        }}
                      >
                        {__('Insert Link')}
                      </MenuItem>
                    )}
                    {mediaControl && (
                      <MediaUploadCheck>
                        <MediaUpload
                          // @ts-ignore
                          onClose={onClose}
                          onSelect={handleMediaChange}
                          render={({ open }) => (
                            <MenuItem
                              icon={image}
                              onClick={() => {
                                open();
                                document
                                  .querySelectorAll('.media-modal')
                                  .forEach((el) => {
                                    (el as HTMLElement).style.zIndex = '999999999999';
                                  });
                              }}
                            >
                              {__('Insert Media')}
                            </MenuItem>
                          )}
                        />
                      </MediaUploadCheck>
                    )}
                  </MenuGroup>
                )}
                <MenuGroup>
                  <MenuItem
                    isDestructive
                    icon={trash}
                    onClick={() => {
                      removeAttributePair(index);
                      onClose();
                    }}
                  >
                    {__('Delete')}
                  </MenuItem>
                </MenuGroup>
              </>
            )}
          </DropdownMenu>
        </div>
        {pair?.data?.media ? (
          <Card
            attributes={{} as BlockstudioBlockAttributes}
            inRepeater
            index={0}
            moveUp={() => {}}
            moveDown={() => {}}
            change={() => {}}
            v={{}}
            data={media?.[pair.data.media] || {}}
            id={media?.[pair.data.media]?.id || ''}
            loaded={!!media?.[pair.data.media]}
            onClick={() => {}}
            onClickDelete={handleMediaDelete}
            style={{
              border: 'var(--blockstudio-border)',
              borderRadius: 'var(--blockstudio-border-radius)',
              marginTop: '12px',
            }}
          />
        ) : (
          <div
            css={css({
              border: style.border,
              borderRadius: style.borderRadius,
              overflow: 'hidden',
              marginTop: '12px',
            })}
          >
            <CodeMirror
              placeholder="Value"
              value={pair.value || ''}
              onChange={(val) => handleAttributeChange(index, 'value', val)}
              basicSetup={{
                autocompletion: true,
                lineNumbers: false,
                foldGutter: false,
              }}
              extensions={[javascript()]}
            />
          </div>
        )}
      </div>
      {showLinkModal && (
        <LinkModal
          onChange={(link) => handleAttributeChange(index, 'value', link.url)}
          onRemove={() => handleAttributeChange(index, 'value', '')}
          opensInNewTab={false}
          setOpen={setShowLinkModal}
          value={{
            url: pair.value,
          }}
        />
      )}
    </>
  );
};

export const Attributes = ({
  attributes,
  keyName,
  link,
  media,
  setAttributes,
}: {
  attributes: BlockstudioBlockAttributes;
  keyName: string;
  link: boolean;
  media: boolean;
  setAttributes?: (props: BlockstudioBlockAttributes) => void;
}) => {
  const attr = get(attributes, keyName) as AttributePair[];

  useMedia(
    attr
      ? attr
          .filter((attribute: AttributePair) => attribute?.data?.media)
          .map((attribute: AttributePair) => `${attribute.data?.media}`)
      : '',
  );

  const addAttributePair = () => {
    const clone = cloneDeep(attributes);
    const existing = get(clone, keyName, []) as AttributePair[];
    if (!existing || !isArray(existing)) {
      set(clone, keyName, [{ attribute: '', value: '' }]);
    } else {
      existing.push({ attribute: '', value: '' });
      set(clone, keyName, existing);
    }
    setAttributes?.({
      ...attributes,
      blockstudio: clone.blockstudio,
    });
  };

  return (
    <div>
      <div
        css={css({
          display: 'grid',
          gap: '20px',
          gridTemplateColumns: 'minmax(0, 1fr)',
        })}
      >
        {attr &&
          attr.map((pair, index) => {
            return (
              <Attribute
                key={index}
                {...{
                  attributes,
                  index,
                  keyName,
                  link,
                  media,
                  pair,
                }}
                setAttributes={setAttributes!}
              />
            );
          })}
      </div>
      <Button
        variant="secondary"
        onClick={addAttributePair}
        css={css({
          marginTop: isArray(attr) && attr?.filter((e) => e).length ? '16px' : undefined,
        })}
      >
        {__('Add Attribute')}
      </Button>
    </div>
  );
};
