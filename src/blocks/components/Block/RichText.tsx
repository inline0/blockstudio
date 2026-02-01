import {
  useBlockProps,
  RichText as WordPressRichText,
} from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import styleToObject from 'style-to-object';
import { selectors } from '@/blocks/store/selectors';
import {
  Any,
  BlockstudioBlock,
  BlockstudioBlockAttributes,
} from '@/types/types';

export const RichText = ({
  attributes,
  setAttributes,
  block,
  clientId,
  data,
  hasOwnBlockProps,
}: {
  attributes: BlockstudioBlockAttributes;
  setAttributes: (attributes: BlockstudioBlockAttributes) => void;
  block: BlockstudioBlock;
  clientId: string;
  data: { attribute: string; tag?: string; [key: string]: Any };
  hasOwnBlockProps: boolean;
}) => {
  const { setRichText } = useDispatch('blockstudio/blocks');
  const richText = useSelect(
    (select) =>
      (select('blockstudio/blocks') as typeof selectors).getRichText(),
    []
  );

  const { tag, ...rest } = data;
  const blockProps = hasOwnBlockProps
    ? useBlockProps()
    : ({} as {
        className: string;
      });
  const richTextBlockProps = {
    ...blockProps,
    tagName: (tag || 'p') as keyof HTMLElementTagNameMap,
    className:
      (rest?.class || '') +
      (hasOwnBlockProps ? ` ${blockProps.className}` : ''),
    style: styleToObject(rest?.style || '') || undefined,
  };

  if (rest.class) delete rest.class;
  if (rest.style) delete rest.style;

  const props = { ...richTextBlockProps, ...rest };

  if (!(block.attributes as Record<string, Any>)?.[data.attribute]) return null;

  const setter = (value: string) => {
    const obj = {
      ...richText,
      [clientId]: {
        ...((richText?.[clientId] as NonNullable<unknown>) || {}),
        [data.attribute]: value,
      },
    };
    setAttributes({
      ...attributes,
      ...{
        [`BLOCKSTUDIO_RICH_TEXT-${data.attribute}-${clientId}`]: value,
      },
    });
    setRichText(obj);
  };

  return (
    <WordPressRichText
      {...props}
      value={(richText?.[clientId] as unknown as Record<string, string>)?.[data?.attribute] || ''}
      onChange={(value: string) => setter(value)}
    />
  );
};
