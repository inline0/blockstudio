const C_LIKE_CHARACTERS = new Set([
  'c', 'C',
  '\u0441', '\u0421',
  '\u023c', '\u023b',
  '\u2184', '\u2183',
  '\u1d04', '\u1d9c',
  '\u217d', '\u216d',
  'ç', 'Ç', 'ć', 'Ć', 'č', 'Č', 'ĉ', 'Ĉ', 'ċ', 'Ċ',
]);

const isCLikeKey = (key: string, code?: string): boolean => {
  if (code === 'KeyC') return true;
  if (!key || key.length !== 1) return false;
  return C_LIKE_CHARACTERS.has(key);
};

let cachedIsMac: boolean | null = null;

const isMac = (): boolean => {
  if (cachedIsMac !== null) return cachedIsMac;
  cachedIsMac =
    navigator.platform?.toUpperCase().indexOf('MAC') >= 0 ||
    /Mac|iPhone|iPad|iPod/.test(navigator.userAgent);
  return cachedIsMac;
};

const isModifierHeld = (e: KeyboardEvent): boolean => {
  return isMac() ? e.metaKey : e.ctrlKey;
};

const isActivationKeyDown = (e: KeyboardEvent): boolean => {
  return isCLikeKey(e.key, e.code) && isModifierHeld(e);
};

const isActivationKeyUp = (e: KeyboardEvent): boolean => {
  return isCLikeKey(e.key, e.code) || (isMac() ? e.key === 'Meta' : e.key === 'Control');
};

const isKeyboardEventTriggeredByInput = (event: KeyboardEvent): boolean => {
  const target = event.target as Element | null;
  if (!target) return false;
  if ((target as HTMLElement).isContentEditable) return true;
  const tagName = target.tagName?.toLowerCase();
  if (tagName === 'input' || tagName === 'textarea' || tagName === 'select') return true;
  const role = target.getAttribute('role');
  if (role === 'textbox' || role === 'searchbox') return true;
  return false;
};

export { isActivationKeyDown, isActivationKeyUp, isKeyboardEventTriggeredByInput, isMac };
