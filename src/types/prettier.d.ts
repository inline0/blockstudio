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
