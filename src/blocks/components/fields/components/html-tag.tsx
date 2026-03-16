import { SelectControl } from '@wordpress/components';

const presets: Record<string, string[]> = {
  heading: ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
  text: ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div'],
  structural: [
    'div',
    'section',
    'article',
    'aside',
    'main',
    'header',
    'footer',
    'nav',
  ],
  all: [
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'p',
    'span',
    'div',
    'section',
    'article',
    'aside',
    'main',
    'header',
    'footer',
    'nav',
  ],
};

export const HtmlTag = ({
  item,
  v,
  change,
}: {
  item: { tags?: string | string[]; exclude?: string[] };
  v: string;
  change: (value: string) => void;
}) => {
  let tags = Array.isArray(item.tags)
    ? item.tags
    : presets[item.tags as string] || presets.heading;

  if (Array.isArray(item.exclude)) {
    tags = tags.filter((tag) => !item.exclude!.includes(tag));
  }

  return (
    <SelectControl
      value={v || tags[0]}
      options={tags.map((tag) => ({ label: `<${tag}>`, value: tag }))}
      onChange={change}
      className="components-base-control"
      help={false}
      label={false}
      __nextHasNoMarginBottom
    />
  );
};
