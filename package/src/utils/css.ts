import {
  css as emotionCss,
  CSSObject,
  Interpolation,
  Theme,
} from '@emotion/react';

const addImportantToEveryRule = (styleObject: CSSObject): CSSObject => {
  const importantStyleObject = {} as CSSObject;

  for (const property in styleObject) {
    if (
      typeof styleObject[property] === 'string' ||
      typeof styleObject[property] === 'number'
    ) {
      if (property === 'content') {
        importantStyleObject[property] = styleObject[property];
      } else {
        importantStyleObject[property] = `${styleObject[property]} !important`;
      }
    } else if (
      typeof styleObject[property] === 'object' &&
      styleObject[property] !== null
    ) {
      importantStyleObject[property] = addImportantToEveryRule(
        styleObject[property] as CSSObject
      );
    }
  }

  return importantStyleObject;
};

export const css = (styles: Interpolation<Theme>): Interpolation<Theme> => {
  const newStyles = addImportantToEveryRule(styles as CSSObject);
  return emotionCss(newStyles);
};
