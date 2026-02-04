import { writeFileSync, mkdirSync } from 'fs';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const dataDir = resolve(__dirname, '../tailwind/data');

const spacingScale = [
  '0',
  'px',
  '0.5',
  '1',
  '1.5',
  '2',
  '2.5',
  '3',
  '3.5',
  '4',
  '5',
  '6',
  '7',
  '8',
  '9',
  '10',
  '11',
  '12',
  '14',
  '16',
  '20',
  '24',
  '28',
  '32',
  '36',
  '40',
  '44',
  '48',
  '52',
  '56',
  '60',
  '64',
  '72',
  '80',
  '96',
];

const spacingScaleWithAuto = [...spacingScale, 'auto'];

const spacingScaleWithFractions = [
  ...spacingScale,
  '1/2',
  '1/3',
  '2/3',
  '1/4',
  '2/4',
  '3/4',
  '1/5',
  '2/5',
  '3/5',
  '4/5',
  '1/6',
  '2/6',
  '3/6',
  '4/6',
  '5/6',
  '1/12',
  '2/12',
  '3/12',
  '4/12',
  '5/12',
  '6/12',
  '7/12',
  '8/12',
  '9/12',
  '10/12',
  '11/12',
  'full',
];

const colors = [
  'inherit',
  'current',
  'transparent',
  'black',
  'white',
  ...[
    'slate',
    'gray',
    'zinc',
    'neutral',
    'stone',
    'red',
    'orange',
    'amber',
    'yellow',
    'lime',
    'green',
    'emerald',
    'teal',
    'cyan',
    'sky',
    'blue',
    'indigo',
    'violet',
    'purple',
    'fuchsia',
    'pink',
    'rose',
  ].flatMap((color) =>
    [
      '50',
      '100',
      '200',
      '300',
      '400',
      '500',
      '600',
      '700',
      '800',
      '900',
      '950',
    ].map((shade) => `${color}-${shade}`),
  ),
];

const opacityScale = [
  '0',
  '5',
  '10',
  '15',
  '20',
  '25',
  '30',
  '35',
  '40',
  '45',
  '50',
  '55',
  '60',
  '65',
  '70',
  '75',
  '80',
  '85',
  '90',
  '95',
  '100',
];

const blurScale = ['none', 'sm', '', 'md', 'lg', 'xl', '2xl', '3xl'];
const radiusScale = ['none', 'sm', '', 'md', 'lg', 'xl', '2xl', '3xl', 'full'];
const shadowScale = ['sm', '', 'md', 'lg', 'xl', '2xl', 'inner', 'none'];

function withPrefix(
  prefix: string,
  values: string[],
  includeNegative = false,
): string[] {
  const result: string[] = [];
  for (const v of values) {
    result.push(v === '' ? prefix : `${prefix}-${v}`);
    if (
      includeNegative &&
      v !== '' &&
      v !== 'auto' &&
      v !== 'full' &&
      v !== 'none'
    ) {
      result.push(`-${prefix}-${v}`);
    }
  }
  return result;
}

function withPrefixSimple(prefix: string, values: string[]): string[] {
  return values.map((v) => (v === '' ? prefix : `${prefix}-${v}`));
}

const classes: string[] = [
  // ===== LAYOUT =====
  // Aspect Ratio
  'aspect-auto',
  'aspect-square',
  'aspect-video',

  // Container
  'container',

  // Columns
  ...withPrefixSimple('columns', [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    '10',
    '11',
    '12',
    'auto',
    '3xs',
    '2xs',
    'xs',
    'sm',
    'md',
    'lg',
    'xl',
    '2xl',
    '3xl',
    '4xl',
    '5xl',
    '6xl',
    '7xl',
  ]),

  // Break After
  'break-after-auto',
  'break-after-avoid',
  'break-after-all',
  'break-after-avoid-page',
  'break-after-page',
  'break-after-left',
  'break-after-right',
  'break-after-column',

  // Break Before
  'break-before-auto',
  'break-before-avoid',
  'break-before-all',
  'break-before-avoid-page',
  'break-before-page',
  'break-before-left',
  'break-before-right',
  'break-before-column',

  // Break Inside
  'break-inside-auto',
  'break-inside-avoid',
  'break-inside-avoid-page',
  'break-inside-avoid-column',

  // Box Decoration Break
  'box-decoration-clone',
  'box-decoration-slice',

  // Box Sizing
  'box-border',
  'box-content',

  // Display
  'block',
  'inline-block',
  'inline',
  'flex',
  'inline-flex',
  'table',
  'inline-table',
  'table-caption',
  'table-cell',
  'table-column',
  'table-column-group',
  'table-footer-group',
  'table-header-group',
  'table-row-group',
  'table-row',
  'flow-root',
  'grid',
  'inline-grid',
  'contents',
  'list-item',
  'hidden',

  // Float
  'float-start',
  'float-end',
  'float-right',
  'float-left',
  'float-none',

  // Clear
  'clear-start',
  'clear-end',
  'clear-left',
  'clear-right',
  'clear-both',
  'clear-none',

  // Isolation
  'isolate',
  'isolation-auto',

  // Object Fit
  'object-contain',
  'object-cover',
  'object-fill',
  'object-none',
  'object-scale-down',

  // Object Position
  'object-bottom',
  'object-center',
  'object-left',
  'object-left-bottom',
  'object-left-top',
  'object-right',
  'object-right-bottom',
  'object-right-top',
  'object-top',

  // Overflow
  'overflow-auto',
  'overflow-hidden',
  'overflow-clip',
  'overflow-visible',
  'overflow-scroll',
  'overflow-x-auto',
  'overflow-y-auto',
  'overflow-x-hidden',
  'overflow-y-hidden',
  'overflow-x-clip',
  'overflow-y-clip',
  'overflow-x-visible',
  'overflow-y-visible',
  'overflow-x-scroll',
  'overflow-y-scroll',

  // Overscroll
  'overscroll-auto',
  'overscroll-contain',
  'overscroll-none',
  'overscroll-y-auto',
  'overscroll-y-contain',
  'overscroll-y-none',
  'overscroll-x-auto',
  'overscroll-x-contain',
  'overscroll-x-none',

  // Position
  'static',
  'fixed',
  'absolute',
  'relative',
  'sticky',

  // Top / Right / Bottom / Left / Inset
  ...withPrefix(
    'inset',
    [...spacingScaleWithAuto, ...spacingScaleWithFractions],
    true,
  ),
  ...withPrefix(
    'inset-x',
    [...spacingScaleWithAuto, ...spacingScaleWithFractions],
    true,
  ),
  ...withPrefix(
    'inset-y',
    [...spacingScaleWithAuto, ...spacingScaleWithFractions],
    true,
  ),
  ...withPrefix(
    'start',
    [...spacingScaleWithAuto, ...spacingScaleWithFractions],
    true,
  ),
  ...withPrefix(
    'end',
    [...spacingScaleWithAuto, ...spacingScaleWithFractions],
    true,
  ),
  ...withPrefix(
    'top',
    [...spacingScaleWithAuto, ...spacingScaleWithFractions],
    true,
  ),
  ...withPrefix(
    'right',
    [...spacingScaleWithAuto, ...spacingScaleWithFractions],
    true,
  ),
  ...withPrefix(
    'bottom',
    [...spacingScaleWithAuto, ...spacingScaleWithFractions],
    true,
  ),
  ...withPrefix(
    'left',
    [...spacingScaleWithAuto, ...spacingScaleWithFractions],
    true,
  ),

  // Visibility
  'visible',
  'invisible',
  'collapse',

  // Z-Index
  ...withPrefix('z', ['0', '10', '20', '30', '40', '50', 'auto'], true),

  // ===== FLEXBOX & GRID =====
  // Flex Basis
  ...withPrefix('basis', [
    ...spacingScale,
    ...spacingScaleWithFractions,
    'auto',
    'full',
  ]),

  // Flex Direction
  'flex-row',
  'flex-row-reverse',
  'flex-col',
  'flex-col-reverse',

  // Flex Wrap
  'flex-wrap',
  'flex-wrap-reverse',
  'flex-nowrap',

  // Flex
  'flex-1',
  'flex-auto',
  'flex-initial',
  'flex-none',

  // Flex Grow
  'grow',
  'grow-0',

  // Flex Shrink
  'shrink',
  'shrink-0',

  // Order
  ...withPrefix(
    'order',
    [
      '1',
      '2',
      '3',
      '4',
      '5',
      '6',
      '7',
      '8',
      '9',
      '10',
      '11',
      '12',
      'first',
      'last',
      'none',
    ],
    true,
  ),

  // Grid Template Columns
  ...withPrefixSimple('grid-cols', [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    '10',
    '11',
    '12',
    'none',
    'subgrid',
  ]),

  // Grid Column Start / End / Span
  ...withPrefixSimple('col-span', [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    '10',
    '11',
    '12',
    'full',
  ]),
  'col-auto',
  ...withPrefixSimple('col-start', [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    '10',
    '11',
    '12',
    '13',
    'auto',
  ]),
  ...withPrefixSimple('col-end', [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    '10',
    '11',
    '12',
    '13',
    'auto',
  ]),

  // Grid Template Rows
  ...withPrefixSimple('grid-rows', [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    '10',
    '11',
    '12',
    'none',
    'subgrid',
  ]),

  // Grid Row Start / End / Span
  ...withPrefixSimple('row-span', [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    '10',
    '11',
    '12',
    'full',
  ]),
  'row-auto',
  ...withPrefixSimple('row-start', [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    '10',
    '11',
    '12',
    '13',
    'auto',
  ]),
  ...withPrefixSimple('row-end', [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    '10',
    '11',
    '12',
    '13',
    'auto',
  ]),

  // Grid Auto Flow
  'grid-flow-row',
  'grid-flow-col',
  'grid-flow-dense',
  'grid-flow-row-dense',
  'grid-flow-col-dense',

  // Grid Auto Columns
  'auto-cols-auto',
  'auto-cols-min',
  'auto-cols-max',
  'auto-cols-fr',

  // Grid Auto Rows
  'auto-rows-auto',
  'auto-rows-min',
  'auto-rows-max',
  'auto-rows-fr',

  // Gap
  ...withPrefix('gap', spacingScale),
  ...withPrefix('gap-x', spacingScale),
  ...withPrefix('gap-y', spacingScale),

  // Justify Content
  'justify-normal',
  'justify-start',
  'justify-end',
  'justify-center',
  'justify-between',
  'justify-around',
  'justify-evenly',
  'justify-stretch',

  // Justify Items
  'justify-items-start',
  'justify-items-end',
  'justify-items-center',
  'justify-items-stretch',

  // Justify Self
  'justify-self-auto',
  'justify-self-start',
  'justify-self-end',
  'justify-self-center',
  'justify-self-stretch',

  // Align Content
  'content-normal',
  'content-center',
  'content-start',
  'content-end',
  'content-between',
  'content-around',
  'content-evenly',
  'content-baseline',
  'content-stretch',

  // Align Items
  'items-start',
  'items-end',
  'items-center',
  'items-baseline',
  'items-stretch',

  // Align Self
  'self-auto',
  'self-start',
  'self-end',
  'self-center',
  'self-stretch',
  'self-baseline',

  // Place Content
  'place-content-center',
  'place-content-start',
  'place-content-end',
  'place-content-between',
  'place-content-around',
  'place-content-evenly',
  'place-content-baseline',
  'place-content-stretch',

  // Place Items
  'place-items-start',
  'place-items-end',
  'place-items-center',
  'place-items-baseline',
  'place-items-stretch',

  // Place Self
  'place-self-auto',
  'place-self-start',
  'place-self-end',
  'place-self-center',
  'place-self-stretch',

  // ===== SPACING =====
  // Padding
  ...withPrefix('p', spacingScale),
  ...withPrefix('px', spacingScale),
  ...withPrefix('py', spacingScale),
  ...withPrefix('ps', spacingScale),
  ...withPrefix('pe', spacingScale),
  ...withPrefix('pt', spacingScale),
  ...withPrefix('pr', spacingScale),
  ...withPrefix('pb', spacingScale),
  ...withPrefix('pl', spacingScale),

  // Margin
  ...withPrefix('m', spacingScaleWithAuto, true),
  ...withPrefix('mx', spacingScaleWithAuto, true),
  ...withPrefix('my', spacingScaleWithAuto, true),
  ...withPrefix('ms', spacingScaleWithAuto, true),
  ...withPrefix('me', spacingScaleWithAuto, true),
  ...withPrefix('mt', spacingScaleWithAuto, true),
  ...withPrefix('mr', spacingScaleWithAuto, true),
  ...withPrefix('mb', spacingScaleWithAuto, true),
  ...withPrefix('ml', spacingScaleWithAuto, true),

  // Space Between
  ...withPrefix('space-x', spacingScale, true),
  ...withPrefix('space-y', spacingScale, true),
  'space-x-reverse',
  'space-y-reverse',

  // ===== SIZING =====
  // Width
  ...withPrefix('w', [
    ...spacingScale,
    ...spacingScaleWithFractions,
    'auto',
    'screen',
    'svw',
    'lvw',
    'dvw',
    'min',
    'max',
    'fit',
  ]),

  // Min-Width
  ...withPrefixSimple('min-w', ['0', 'full', 'min', 'max', 'fit']),

  // Max-Width
  ...withPrefixSimple('max-w', [
    '0',
    'none',
    'xs',
    'sm',
    'md',
    'lg',
    'xl',
    '2xl',
    '3xl',
    '4xl',
    '5xl',
    '6xl',
    '7xl',
    'full',
    'min',
    'max',
    'fit',
    'prose',
    'screen-sm',
    'screen-md',
    'screen-lg',
    'screen-xl',
    'screen-2xl',
  ]),

  // Height
  ...withPrefix('h', [
    ...spacingScale,
    ...spacingScaleWithFractions,
    'auto',
    'screen',
    'svh',
    'lvh',
    'dvh',
    'min',
    'max',
    'fit',
  ]),

  // Min-Height
  ...withPrefixSimple('min-h', [
    '0',
    'full',
    'screen',
    'svh',
    'lvh',
    'dvh',
    'min',
    'max',
    'fit',
  ]),

  // Max-Height
  ...withPrefixSimple('max-h', [
    ...spacingScale,
    'none',
    'full',
    'screen',
    'svh',
    'lvh',
    'dvh',
    'min',
    'max',
    'fit',
  ]),

  // Size
  ...withPrefix('size', [
    ...spacingScale,
    ...spacingScaleWithFractions,
    'auto',
    'screen',
    'svw',
    'svh',
    'lvw',
    'lvh',
    'dvw',
    'dvh',
    'min',
    'max',
    'fit',
  ]),

  // ===== TYPOGRAPHY =====
  // Font Family
  'font-sans',
  'font-serif',
  'font-mono',

  // Font Size
  'text-xs',
  'text-sm',
  'text-base',
  'text-lg',
  'text-xl',
  'text-2xl',
  'text-3xl',
  'text-4xl',
  'text-5xl',
  'text-6xl',
  'text-7xl',
  'text-8xl',
  'text-9xl',

  // Font Smoothing
  'antialiased',
  'subpixel-antialiased',

  // Font Style
  'italic',
  'not-italic',

  // Font Weight
  'font-thin',
  'font-extralight',
  'font-light',
  'font-normal',
  'font-medium',
  'font-semibold',
  'font-bold',
  'font-extrabold',
  'font-black',

  // Font Variant Numeric
  'normal-nums',
  'ordinal',
  'slashed-zero',
  'lining-nums',
  'oldstyle-nums',
  'proportional-nums',
  'tabular-nums',
  'diagonal-fractions',
  'stacked-fractions',

  // Letter Spacing
  'tracking-tighter',
  'tracking-tight',
  'tracking-normal',
  'tracking-wide',
  'tracking-wider',
  'tracking-widest',

  // Line Clamp
  ...withPrefixSimple('line-clamp', ['1', '2', '3', '4', '5', '6', 'none']),

  // Line Height
  'leading-none',
  'leading-tight',
  'leading-snug',
  'leading-normal',
  'leading-relaxed',
  'leading-loose',
  ...withPrefixSimple('leading', ['3', '4', '5', '6', '7', '8', '9', '10']),

  // List Style Image
  'list-image-none',

  // List Style Position
  'list-inside',
  'list-outside',

  // List Style Type
  'list-none',
  'list-disc',
  'list-decimal',

  // Text Align
  'text-left',
  'text-center',
  'text-right',
  'text-justify',
  'text-start',
  'text-end',

  // Text Color
  ...colors.map((c) => `text-${c}`),

  // Text Decoration
  'underline',
  'overline',
  'line-through',
  'no-underline',

  // Text Decoration Color
  ...colors.map((c) => `decoration-${c}`),

  // Text Decoration Style
  'decoration-solid',
  'decoration-double',
  'decoration-dotted',
  'decoration-dashed',
  'decoration-wavy',

  // Text Decoration Thickness
  'decoration-auto',
  'decoration-from-font',
  'decoration-0',
  'decoration-1',
  'decoration-2',
  'decoration-4',
  'decoration-8',

  // Text Underline Offset
  'underline-offset-auto',
  'underline-offset-0',
  'underline-offset-1',
  'underline-offset-2',
  'underline-offset-4',
  'underline-offset-8',

  // Text Transform
  'uppercase',
  'lowercase',
  'capitalize',
  'normal-case',

  // Text Overflow
  'truncate',
  'text-ellipsis',
  'text-clip',

  // Text Wrap
  'text-wrap',
  'text-nowrap',
  'text-balance',
  'text-pretty',

  // Text Indent
  ...withPrefix('indent', spacingScale, true),

  // Vertical Align
  'align-baseline',
  'align-top',
  'align-middle',
  'align-bottom',
  'align-text-top',
  'align-text-bottom',
  'align-sub',
  'align-super',

  // Whitespace
  'whitespace-normal',
  'whitespace-nowrap',
  'whitespace-pre',
  'whitespace-pre-line',
  'whitespace-pre-wrap',
  'whitespace-break-spaces',

  // Word Break
  'break-normal',
  'break-words',
  'break-all',
  'break-keep',

  // Hyphens
  'hyphens-none',
  'hyphens-manual',
  'hyphens-auto',

  // Content
  'content-none',

  // ===== BACKGROUNDS =====
  // Background Attachment
  'bg-fixed',
  'bg-local',
  'bg-scroll',

  // Background Clip
  'bg-clip-border',
  'bg-clip-padding',
  'bg-clip-content',
  'bg-clip-text',

  // Background Color
  ...colors.map((c) => `bg-${c}`),

  // Background Origin
  'bg-origin-border',
  'bg-origin-padding',
  'bg-origin-content',

  // Background Position
  'bg-bottom',
  'bg-center',
  'bg-left',
  'bg-left-bottom',
  'bg-left-top',
  'bg-right',
  'bg-right-bottom',
  'bg-right-top',
  'bg-top',

  // Background Repeat
  'bg-repeat',
  'bg-no-repeat',
  'bg-repeat-x',
  'bg-repeat-y',
  'bg-repeat-round',
  'bg-repeat-space',

  // Background Size
  'bg-auto',
  'bg-cover',
  'bg-contain',

  // Background Image
  'bg-none',
  'bg-gradient-to-t',
  'bg-gradient-to-tr',
  'bg-gradient-to-r',
  'bg-gradient-to-br',
  'bg-gradient-to-b',
  'bg-gradient-to-bl',
  'bg-gradient-to-l',
  'bg-gradient-to-tl',

  // Gradient Color Stops
  ...colors.map((c) => `from-${c}`),
  ...colors.map((c) => `via-${c}`),
  ...colors.map((c) => `to-${c}`),
  // Gradient positions
  ...[
    '0%',
    '5%',
    '10%',
    '15%',
    '20%',
    '25%',
    '30%',
    '35%',
    '40%',
    '45%',
    '50%',
    '55%',
    '60%',
    '65%',
    '70%',
    '75%',
    '80%',
    '85%',
    '90%',
    '95%',
    '100%',
  ].flatMap((p) => [`from-${p}`, `via-${p}`, `to-${p}`]),

  // ===== BORDERS =====
  // Border Radius
  ...radiusScale.flatMap((size) => {
    const suffix = size === '' ? '' : `-${size}`;
    return [
      `rounded${suffix}`,
      `rounded-s${suffix}`,
      `rounded-e${suffix}`,
      `rounded-t${suffix}`,
      `rounded-r${suffix}`,
      `rounded-b${suffix}`,
      `rounded-l${suffix}`,
      `rounded-ss${suffix}`,
      `rounded-se${suffix}`,
      `rounded-ee${suffix}`,
      `rounded-es${suffix}`,
      `rounded-tl${suffix}`,
      `rounded-tr${suffix}`,
      `rounded-br${suffix}`,
      `rounded-bl${suffix}`,
    ];
  }),

  // Border Width
  ...['', '0', '2', '4', '8'].flatMap((size) => {
    const suffix = size === '' ? '' : `-${size}`;
    return [
      `border${suffix}`,
      `border-x${suffix}`,
      `border-y${suffix}`,
      `border-s${suffix}`,
      `border-e${suffix}`,
      `border-t${suffix}`,
      `border-r${suffix}`,
      `border-b${suffix}`,
      `border-l${suffix}`,
    ];
  }),

  // Border Color
  ...colors.flatMap((c) => [
    `border-${c}`,
    `border-x-${c}`,
    `border-y-${c}`,
    `border-s-${c}`,
    `border-e-${c}`,
    `border-t-${c}`,
    `border-r-${c}`,
    `border-b-${c}`,
    `border-l-${c}`,
  ]),

  // Border Style
  'border-solid',
  'border-dashed',
  'border-dotted',
  'border-double',
  'border-hidden',
  'border-none',

  // Divide Width
  ...['', '0', '2', '4', '8', 'reverse'].flatMap((size) => {
    const suffix = size === '' ? '' : `-${size}`;
    return [`divide-x${suffix}`, `divide-y${suffix}`];
  }),

  // Divide Color
  ...colors.map((c) => `divide-${c}`),

  // Divide Style
  'divide-solid',
  'divide-dashed',
  'divide-dotted',
  'divide-double',
  'divide-none',

  // Outline Width
  'outline-0',
  'outline-1',
  'outline-2',
  'outline-4',
  'outline-8',

  // Outline Color
  ...colors.map((c) => `outline-${c}`),

  // Outline Style
  'outline-none',
  'outline',
  'outline-dashed',
  'outline-dotted',
  'outline-double',

  // Outline Offset
  'outline-offset-0',
  'outline-offset-1',
  'outline-offset-2',
  'outline-offset-4',
  'outline-offset-8',

  // Ring Width
  'ring-0',
  'ring-1',
  'ring-2',
  'ring',
  'ring-4',
  'ring-8',
  'ring-inset',

  // Ring Color
  ...colors.map((c) => `ring-${c}`),

  // Ring Offset Width
  'ring-offset-0',
  'ring-offset-1',
  'ring-offset-2',
  'ring-offset-4',
  'ring-offset-8',

  // Ring Offset Color
  ...colors.map((c) => `ring-offset-${c}`),

  // ===== EFFECTS =====
  // Box Shadow
  ...shadowScale.map((s) => (s === '' ? 'shadow' : `shadow-${s}`)),

  // Box Shadow Color
  ...colors.map((c) => `shadow-${c}`),

  // Opacity
  ...opacityScale.map((o) => `opacity-${o}`),

  // Mix Blend Mode
  'mix-blend-normal',
  'mix-blend-multiply',
  'mix-blend-screen',
  'mix-blend-overlay',
  'mix-blend-darken',
  'mix-blend-lighten',
  'mix-blend-color-dodge',
  'mix-blend-color-burn',
  'mix-blend-hard-light',
  'mix-blend-soft-light',
  'mix-blend-difference',
  'mix-blend-exclusion',
  'mix-blend-hue',
  'mix-blend-saturation',
  'mix-blend-color',
  'mix-blend-luminosity',
  'mix-blend-plus-darker',
  'mix-blend-plus-lighter',

  // Background Blend Mode
  'bg-blend-normal',
  'bg-blend-multiply',
  'bg-blend-screen',
  'bg-blend-overlay',
  'bg-blend-darken',
  'bg-blend-lighten',
  'bg-blend-color-dodge',
  'bg-blend-color-burn',
  'bg-blend-hard-light',
  'bg-blend-soft-light',
  'bg-blend-difference',
  'bg-blend-exclusion',
  'bg-blend-hue',
  'bg-blend-saturation',
  'bg-blend-color',
  'bg-blend-luminosity',

  // ===== FILTERS =====
  // Blur
  ...blurScale.map((s) => (s === '' ? 'blur' : `blur-${s}`)),

  // Brightness
  ...[
    '0',
    '50',
    '75',
    '90',
    '95',
    '100',
    '105',
    '110',
    '125',
    '150',
    '200',
  ].map((v) => `brightness-${v}`),

  // Contrast
  ...['0', '50', '75', '100', '125', '150', '200'].map((v) => `contrast-${v}`),

  // Drop Shadow
  ...['sm', '', 'md', 'lg', 'xl', '2xl', 'none'].map((s) =>
    s === '' ? 'drop-shadow' : `drop-shadow-${s}`,
  ),

  // Grayscale
  'grayscale-0',
  'grayscale',

  // Hue Rotate
  ...['0', '15', '30', '60', '90', '180'].flatMap((v) => [
    `hue-rotate-${v}`,
    `-hue-rotate-${v}`,
  ]),

  // Invert
  'invert-0',
  'invert',

  // Saturate
  ...['0', '50', '100', '150', '200'].map((v) => `saturate-${v}`),

  // Sepia
  'sepia-0',
  'sepia',

  // Backdrop Blur
  ...blurScale.map((s) => (s === '' ? 'backdrop-blur' : `backdrop-blur-${s}`)),

  // Backdrop Brightness
  ...[
    '0',
    '50',
    '75',
    '90',
    '95',
    '100',
    '105',
    '110',
    '125',
    '150',
    '200',
  ].map((v) => `backdrop-brightness-${v}`),

  // Backdrop Contrast
  ...['0', '50', '75', '100', '125', '150', '200'].map(
    (v) => `backdrop-contrast-${v}`,
  ),

  // Backdrop Grayscale
  'backdrop-grayscale-0',
  'backdrop-grayscale',

  // Backdrop Hue Rotate
  ...['0', '15', '30', '60', '90', '180'].flatMap((v) => [
    `backdrop-hue-rotate-${v}`,
    `-backdrop-hue-rotate-${v}`,
  ]),

  // Backdrop Invert
  'backdrop-invert-0',
  'backdrop-invert',

  // Backdrop Opacity
  ...opacityScale.map((o) => `backdrop-opacity-${o}`),

  // Backdrop Saturate
  ...['0', '50', '100', '150', '200'].map((v) => `backdrop-saturate-${v}`),

  // Backdrop Sepia
  'backdrop-sepia-0',
  'backdrop-sepia',

  // ===== TABLES =====
  // Border Collapse
  'border-collapse',
  'border-separate',

  // Border Spacing
  ...withPrefix('border-spacing', spacingScale),
  ...withPrefix('border-spacing-x', spacingScale),
  ...withPrefix('border-spacing-y', spacingScale),

  // Table Layout
  'table-auto',
  'table-fixed',

  // Caption Side
  'caption-top',
  'caption-bottom',

  // ===== TRANSITIONS & ANIMATION =====
  // Transition Property
  'transition-none',
  'transition-all',
  'transition',
  'transition-colors',
  'transition-opacity',
  'transition-shadow',
  'transition-transform',

  // Transition Duration
  ...['0', '75', '100', '150', '200', '300', '500', '700', '1000'].map(
    (v) => `duration-${v}`,
  ),

  // Transition Timing Function
  'ease-linear',
  'ease-in',
  'ease-out',
  'ease-in-out',

  // Transition Delay
  ...['0', '75', '100', '150', '200', '300', '500', '700', '1000'].map(
    (v) => `delay-${v}`,
  ),

  // Animation
  'animate-none',
  'animate-spin',
  'animate-ping',
  'animate-pulse',
  'animate-bounce',

  // ===== TRANSFORMS =====
  // Scale
  ...['0', '50', '75', '90', '95', '100', '105', '110', '125', '150'].flatMap(
    (v) => [`scale-${v}`, `scale-x-${v}`, `scale-y-${v}`],
  ),

  // Rotate
  ...['0', '1', '2', '3', '6', '12', '45', '90', '180'].flatMap((v) => [
    `rotate-${v}`,
    `-rotate-${v}`,
  ]),

  // Translate
  ...withPrefix(
    'translate-x',
    [...spacingScale, ...spacingScaleWithFractions, 'full'],
    true,
  ),
  ...withPrefix(
    'translate-y',
    [...spacingScale, ...spacingScaleWithFractions, 'full'],
    true,
  ),

  // Skew
  ...['0', '1', '2', '3', '6', '12'].flatMap((v) => [
    `skew-x-${v}`,
    `skew-y-${v}`,
    `-skew-x-${v}`,
    `-skew-y-${v}`,
  ]),

  // Transform Origin
  'origin-center',
  'origin-top',
  'origin-top-right',
  'origin-right',
  'origin-bottom-right',
  'origin-bottom',
  'origin-bottom-left',
  'origin-left',
  'origin-top-left',

  // Transform
  'transform',
  'transform-cpu',
  'transform-gpu',
  'transform-none',

  // ===== INTERACTIVITY =====
  // Accent Color
  'accent-auto',
  ...colors.map((c) => `accent-${c}`),

  // Appearance
  'appearance-none',
  'appearance-auto',

  // Cursor
  'cursor-auto',
  'cursor-default',
  'cursor-pointer',
  'cursor-wait',
  'cursor-text',
  'cursor-move',
  'cursor-help',
  'cursor-not-allowed',
  'cursor-none',
  'cursor-context-menu',
  'cursor-progress',
  'cursor-cell',
  'cursor-crosshair',
  'cursor-vertical-text',
  'cursor-alias',
  'cursor-copy',
  'cursor-no-drop',
  'cursor-grab',
  'cursor-grabbing',
  'cursor-all-scroll',
  'cursor-col-resize',
  'cursor-row-resize',
  'cursor-n-resize',
  'cursor-e-resize',
  'cursor-s-resize',
  'cursor-w-resize',
  'cursor-ne-resize',
  'cursor-nw-resize',
  'cursor-se-resize',
  'cursor-sw-resize',
  'cursor-ew-resize',
  'cursor-ns-resize',
  'cursor-nesw-resize',
  'cursor-nwse-resize',
  'cursor-zoom-in',
  'cursor-zoom-out',

  // Caret Color
  ...colors.map((c) => `caret-${c}`),

  // Pointer Events
  'pointer-events-none',
  'pointer-events-auto',

  // Resize
  'resize-none',
  'resize-y',
  'resize-x',
  'resize',

  // Scroll Behavior
  'scroll-auto',
  'scroll-smooth',

  // Scroll Margin
  ...withPrefix('scroll-m', spacingScale, true),
  ...withPrefix('scroll-mx', spacingScale, true),
  ...withPrefix('scroll-my', spacingScale, true),
  ...withPrefix('scroll-ms', spacingScale, true),
  ...withPrefix('scroll-me', spacingScale, true),
  ...withPrefix('scroll-mt', spacingScale, true),
  ...withPrefix('scroll-mr', spacingScale, true),
  ...withPrefix('scroll-mb', spacingScale, true),
  ...withPrefix('scroll-ml', spacingScale, true),

  // Scroll Padding
  ...withPrefix('scroll-p', spacingScale),
  ...withPrefix('scroll-px', spacingScale),
  ...withPrefix('scroll-py', spacingScale),
  ...withPrefix('scroll-ps', spacingScale),
  ...withPrefix('scroll-pe', spacingScale),
  ...withPrefix('scroll-pt', spacingScale),
  ...withPrefix('scroll-pr', spacingScale),
  ...withPrefix('scroll-pb', spacingScale),
  ...withPrefix('scroll-pl', spacingScale),

  // Scroll Snap Align
  'snap-start',
  'snap-end',
  'snap-center',
  'snap-align-none',

  // Scroll Snap Stop
  'snap-normal',
  'snap-always',

  // Scroll Snap Type
  'snap-none',
  'snap-x',
  'snap-y',
  'snap-both',
  'snap-mandatory',
  'snap-proximity',

  // Touch Action
  'touch-auto',
  'touch-none',
  'touch-pan-x',
  'touch-pan-left',
  'touch-pan-right',
  'touch-pan-y',
  'touch-pan-up',
  'touch-pan-down',
  'touch-pinch-zoom',
  'touch-manipulation',

  // User Select
  'select-none',
  'select-text',
  'select-all',
  'select-auto',

  // Will Change
  'will-change-auto',
  'will-change-scroll',
  'will-change-contents',
  'will-change-transform',

  // ===== SVG =====
  // Fill
  'fill-none',
  ...colors.map((c) => `fill-${c}`),

  // Stroke
  'stroke-none',
  ...colors.map((c) => `stroke-${c}`),

  // Stroke Width
  'stroke-0',
  'stroke-1',
  'stroke-2',

  // ===== ACCESSIBILITY =====
  // Screen Readers
  'sr-only',
  'not-sr-only',

  // Forced Color Adjust
  'forced-color-adjust-auto',
  'forced-color-adjust-none',
];

const defaultScreens = {
  sm: '640px',
  md: '768px',
  lg: '1024px',
  xl: '1280px',
  '2xl': '1536px',
};

function main() {
  console.log('Generating Tailwind data...');

  mkdirSync(dataDir, { recursive: true });

  const uniqueClasses = [...new Set(classes)];
  const classesContent = `export const classes = [\n  '${uniqueClasses.join("',\n  '")}',\n];\n`;
  writeFileSync(resolve(dataDir, 'classes.ts'), classesContent);
  console.log(`Generated ${uniqueClasses.length} classes`);

  const screensContent = `export const screens: Record<string, string> = ${JSON.stringify(defaultScreens, null, 2)};\n`;
  writeFileSync(resolve(dataDir, 'screens.ts'), screensContent);
  console.log('Generated screens.ts');

  console.log('Done!');
}

main();
