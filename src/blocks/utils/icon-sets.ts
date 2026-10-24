// Sub set names are the file name suffixes in includes/icons: <set>-<subSet>.json.
export const iconData: Record<string, string[]> = {
  octicons: [],
  'vscode-icons': [],
  'remix-icons': [
    'arrows',
    'buildings',
    'business',
    'communication',
    'design',
    'development',
    'device',
    'document',
    'editor',
    'finance',
    'food',
    'game-sports',
    'health-medical',
    'logos',
    'map',
    'media',
    'others',
    'system',
    'user-faces',
    'weather',
  ],
  'tabler-icons': ['outline', 'filled'],
  'simple-icons': [],
  'flat-color-icons': [],
  'bootstrap-icons': [],
  'material-design-icons': ['two-tone', 'outlined', 'filled', 'round', 'sharp'],
  'box-icons': ['logos', 'solid', 'regular'],
  'ion-icons': [],
  heroicons: ['solid', 'outline'],
  'feather-icons': [],
  'fontawesome-free': ['brands', 'solid', 'regular'],
  'grommet-icons': [],
  'css-gg': [],
};

export const getDefaultSubSet = (
  set: string,
  subSets?: string[] | string,
): string => {
  const available = iconData[set] ?? [];
  const wanted = ([] as string[])
    .concat(subSets ?? [])
    .find((subSet) => available.includes(subSet));

  return (
    wanted ?? (available.includes('solid') ? 'solid' : (available[0] ?? ''))
  );
};
