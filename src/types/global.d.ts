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

// Declare prettier modules (v3)
declare module 'prettier/plugins/postcss' {
  const parser: unknown;
  export default parser;
}

declare module 'prettier/standalone' {
  const prettier: {
    format: (source: string, options: unknown) => Promise<string>;
  };
  export default prettier;
}

declare module 'prettier/plugins/html' {
  const parser: unknown;
  export default parser;
}

declare module 'prettier/plugins/babel' {
  const parser: unknown;
  export default parser;
}

export {};
