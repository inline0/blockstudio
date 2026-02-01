declare module 'prettier/esm/parser-postcss.mjs' {
  const parser: unknown;
  export default parser;
}

declare module 'prettier/esm/standalone.mjs' {
  const prettier: {
    format: (source: string, options: unknown) => string;
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
