const resolveCssVar = (cssVarName: string): string | null => {
  const value = getComputedStyle(document.documentElement)
    .getPropertyValue(cssVarName)
    .trim();

  if (value.startsWith('var(')) {
    return resolveCssVar(value.slice(4, -1));
  }

  return value || null;
};

export const lightenColor = (
  cssVarName: string,
  percentage: number,
): string | null => {
  const color = resolveCssVar(cssVarName);
  if (!color) {
    return null;
  }

  let r: number, g: number, b: number;
  if (color.startsWith('#')) {
    const hex = color.substring(1);
    r = parseInt(hex.slice(0, 2), 16);
    g = parseInt(hex.slice(2, 4), 16);
    b = parseInt(hex.slice(4, 6), 16);
  } else {
    const rgbValues = color.match(/\d+/g);
    if (rgbValues) {
      [r, g, b] = rgbValues.map(Number);
    } else {
      return null;
    }
  }

  r = Math.floor(r + (255 - r) * percentage);
  g = Math.floor(g + (255 - g) * percentage);
  b = Math.floor(b + (255 - b) * percentage);

  return `rgb(${r}, ${g}, ${b})`;
};
