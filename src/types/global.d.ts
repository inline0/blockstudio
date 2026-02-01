// Augment React types for Emotion css prop
import type { Interpolation, Theme } from '@emotion/react';

declare module 'react' {
  interface Attributes {
    css?: Interpolation<Theme>;
  }
}

// Augment htmlparser2 to export DomElement
declare module 'htmlparser2' {
  import type { Element as DomHandlerElement } from 'domhandler';
  export type DomElement = DomHandlerElement;
}

// Declare prettier ESM modules
declare module 'prettier/esm/parser-postcss.mjs' {
  const parser: unknown;
  export default parser;
}

declare module 'prettier/esm/standalone.mjs' {
  const prettier: {
    format: (source: string, options: unknown) => Promise<string>;
  };
  export default prettier;
}

declare module 'prettier/esm/parser-html.mjs' {
  const parser: unknown;
  export default parser;
}

declare module 'prettier/esm/parser-babel.mjs' {
  const parser: unknown;
  export default parser;
}

export {};
