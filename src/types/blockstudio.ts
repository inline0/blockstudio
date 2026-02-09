// To parse this data:
//
//   import { Convert, Blockstudio } from "./file";
//
//   const blockstudio = Convert.toBlockstudio(json);
//
// These functions will throw an error if the JSON doesn't
// match the expected interface, even if the JSON is valid.

export interface Blockstudio {
  /**
   * Settings related to AI-powered context generation.
   */
  ai?: AI;
  /**
   * Settings related to asset management.
   */
  assets?: Assets;
  /**
   * Settings related to Gutenberg.
   */
  blockEditor?: BlockEditor;
  /**
   * Settings related to the editor.
   */
  editor?: Editor;
  /**
   * Add block library.
   */
  library?: boolean;
  /**
   * Settings related to Tailwind.
   */
  tailwind?: Tailwind;
  /**
   * Settings related to developer tooling.
   */
  tooling?: Tooling;
  /**
   * Settings related to allowed users with access to the settings and editor.
   */
  users?: Users;
  [property: string]: any;
}

/**
 * Settings related to AI-powered context generation.
 */
export interface AI {
  /**
   * Enables the automatic creation of a comprehensive context file for use with large
   * language model (LLM) tools (e.g., Cursor). This file compiles current installation data:
   * all available block definitions and paths, Blockstudio-specific settings, relevant block
   * schemas, and combined Blockstudio documentation—providing a ready-to-use resource for
   * prompt engineering and AI code development.
   */
  enableContextGeneration?: boolean;
  [property: string]: any;
}

/**
 * Settings related to asset management.
 */
export interface Assets {
  /**
   * Enqueue assets in frontend and editor.
   */
  enqueue?: boolean;
  /**
   * Settings related to asset minification.
   */
  minify?: Minify;
  /**
   * Settings related to asset processing.
   */
  process?: Process;
  [property: string]: any;
}

/**
 * Settings related to asset minification.
 */
export interface Minify {
  /**
   * Minify CSS.
   */
  css?: boolean;
  /**
   * Minify JS.
   */
  js?: boolean;
  [property: string]: any;
}

/**
 * Settings related to asset processing.
 */
export interface Process {
  /**
   * Process SCSS in .css files.
   */
  scss?: boolean;
  /**
   * Process .scss files to CSS.
   */
  scssFiles?: boolean;
  [property: string]: any;
}

/**
 * Settings related to Gutenberg.
 */
export interface BlockEditor {
  /**
   * Stylesheets whose CSS classes should be available for choice in the class field.
   */
  cssClasses?: any[];
  /**
   * Stylesheets whose CSS variables should be available for autocompletion in the code field.
   */
  cssVariables?: any[];
  /**
   * Disable loading of blocks inside the Block Editor.
   */
  disableLoading?: boolean;
  [property: string]: any;
}

/**
 * Settings related to the editor.
 */
export interface Editor {
  /**
   * Additional asset IDs to be enqueued.
   */
  assets?: any[];
  /**
   * Format code upon saving.
   */
  formatOnSave?: boolean;
  /**
   * Additional markup to be added to the end of the editor.
   */
  markup?: boolean | string;
  [property: string]: any;
}

/**
 * Settings related to Tailwind.
 */
export interface Tailwind {
  /**
   * Tailwind CSS configuration using v4 CSS-first syntax.
   */
  config?: string;
  /**
   * Enable Tailwind.
   */
  enabled?: boolean;
  [property: string]: any;
}

/**
 * Settings related to developer tooling.
 */
export interface Tooling {
  /**
   * Settings related to the canvas.
   */
  canvas?: ToolingCanvas;
  /**
   * Settings related to the frontend devtools inspector.
   */
  devtools?: ToolingDevtools;
  [property: string]: any;
}

/**
 * Settings related to the canvas.
 */
export interface ToolingCanvas {
  /**
   * Enable the canvas.
   */
  enabled?: boolean;
  [property: string]: any;
}

/**
 * Settings related to the frontend devtools inspector.
 */
export interface ToolingDevtools {
  /**
   * Enable the frontend devtools inspector.
   */
  enabled?: boolean;
  [property: string]: any;
}

/**
 * Settings related to allowed users with access to the settings and editor.
 */
export interface Users {
  /**
   * List of user IDs with access to the settings and editor.
   */
  ids?: number[];
  /**
   * List of user roles with access to the settings and editor.
   */
  roles?: string[];
  [property: string]: any;
}

// Converts JSON strings to/from your types
// and asserts the results of JSON.parse at runtime
export class Convert {
  public static toBlockstudio(json: string): Blockstudio {
    return cast(JSON.parse(json), r('Blockstudio'));
  }

  public static blockstudioToJson(value: Blockstudio): string {
    return JSON.stringify(uncast(value, r('Blockstudio')), null, 2);
  }
}

function invalidValue(typ: any, val: any, key: any, parent: any = ''): never {
  const prettyTyp = prettyTypeName(typ);
  const parentText = parent ? ` on ${parent}` : '';
  const keyText = key ? ` for key "${key}"` : '';
  throw Error(
    `Invalid value${keyText}${parentText}. Expected ${prettyTyp} but got ${JSON.stringify(val)}`,
  );
}

function prettyTypeName(typ: any): string {
  if (Array.isArray(typ)) {
    if (typ.length === 2 && typ[0] === undefined) {
      return `an optional ${prettyTypeName(typ[1])}`;
    } else {
      return `one of [${typ
        .map((a) => {
          return prettyTypeName(a);
        })
        .join(', ')}]`;
    }
  } else if (typeof typ === 'object' && typ.literal !== undefined) {
    return typ.literal;
  } else {
    return typeof typ;
  }
}

function jsonToJSProps(typ: any): any {
  if (typ.jsonToJS === undefined) {
    const map: any = {};
    typ.props.forEach((p: any) => (map[p.json] = { key: p.js, typ: p.typ }));
    typ.jsonToJS = map;
  }
  return typ.jsonToJS;
}

function jsToJSONProps(typ: any): any {
  if (typ.jsToJSON === undefined) {
    const map: any = {};
    typ.props.forEach((p: any) => (map[p.js] = { key: p.json, typ: p.typ }));
    typ.jsToJSON = map;
  }
  return typ.jsToJSON;
}

function transform(
  val: any,
  typ: any,
  getProps: any,
  key: any = '',
  parent: any = '',
): any {
  function transformPrimitive(typ: string, val: any): any {
    if (typeof typ === typeof val) return val;
    return invalidValue(typ, val, key, parent);
  }

  function transformUnion(typs: any[], val: any): any {
    // val must validate against one typ in typs
    const l = typs.length;
    for (let i = 0; i < l; i++) {
      const typ = typs[i];
      try {
        return transform(val, typ, getProps);
      } catch {}
    }
    return invalidValue(typs, val, key, parent);
  }

  function transformEnum(cases: string[], val: any): any {
    if (cases.indexOf(val) !== -1) return val;
    return invalidValue(
      cases.map((a) => {
        return l(a);
      }),
      val,
      key,
      parent,
    );
  }

  function transformArray(typ: any, val: any): any {
    // val must be an array with no invalid elements
    if (!Array.isArray(val)) return invalidValue(l('array'), val, key, parent);
    return val.map((el) => transform(el, typ, getProps));
  }

  function transformDate(val: any): any {
    if (val === null) {
      return null;
    }
    const d = new Date(val);
    if (isNaN(d.valueOf())) {
      return invalidValue(l('Date'), val, key, parent);
    }
    return d;
  }

  function transformObject(
    props: { [k: string]: any },
    additional: any,
    val: any,
  ): any {
    if (val === null || typeof val !== 'object' || Array.isArray(val)) {
      return invalidValue(l(ref || 'object'), val, key, parent);
    }
    const result: any = {};
    Object.getOwnPropertyNames(props).forEach((key) => {
      const prop = props[key];
      const v = Object.prototype.hasOwnProperty.call(val, key)
        ? val[key]
        : undefined;
      result[prop.key] = transform(v, prop.typ, getProps, key, ref);
    });
    Object.getOwnPropertyNames(val).forEach((key) => {
      if (!Object.prototype.hasOwnProperty.call(props, key)) {
        result[key] = transform(val[key], additional, getProps, key, ref);
      }
    });
    return result;
  }

  if (typ === 'any') return val;
  if (typ === null) {
    if (val === null) return val;
    return invalidValue(typ, val, key, parent);
  }
  if (typ === false) return invalidValue(typ, val, key, parent);
  let ref: any = undefined;
  while (typeof typ === 'object' && typ.ref !== undefined) {
    ref = typ.ref;
    typ = typeMap[typ.ref];
  }
  if (Array.isArray(typ)) return transformEnum(typ, val);
  if (typeof typ === 'object') {
    return typ.hasOwnProperty('unionMembers')
      ? transformUnion(typ.unionMembers, val)
      : typ.hasOwnProperty('arrayItems')
        ? transformArray(typ.arrayItems, val)
        : typ.hasOwnProperty('props')
          ? transformObject(getProps(typ), typ.additional, val)
          : invalidValue(typ, val, key, parent);
  }
  // Numbers can be parsed by Date but shouldn't be.
  if (typ === Date && typeof val !== 'number') return transformDate(val);
  return transformPrimitive(typ, val);
}

function cast<T>(val: any, typ: any): T {
  return transform(val, typ, jsonToJSProps);
}

function uncast<T>(val: T, typ: any): any {
  return transform(val, typ, jsToJSONProps);
}

function l(typ: any) {
  return { literal: typ };
}

function a(typ: any) {
  return { arrayItems: typ };
}

function u(...typs: any[]) {
  return { unionMembers: typs };
}

function o(props: any[], additional: any) {
  return { props, additional };
}

function r(name: string) {
  return { ref: name };
}

const typeMap: any = {
  Blockstudio: o(
    [
      { json: 'ai', js: 'ai', typ: u(undefined, r('AI')) },
      { json: 'assets', js: 'assets', typ: u(undefined, r('Assets')) },
      {
        json: 'blockEditor',
        js: 'blockEditor',
        typ: u(undefined, r('BlockEditor')),
      },
      { json: 'editor', js: 'editor', typ: u(undefined, r('Editor')) },
      { json: 'library', js: 'library', typ: u(undefined, true) },
      { json: 'tailwind', js: 'tailwind', typ: u(undefined, r('Tailwind')) },
      { json: 'tooling', js: 'tooling', typ: u(undefined, r('Tooling')) },
      { json: 'users', js: 'users', typ: u(undefined, r('Users')) },
    ],
    'any',
  ),
  AI: o(
    [
      {
        json: 'enableContextGeneration',
        js: 'enableContextGeneration',
        typ: u(undefined, true),
      },
    ],
    'any',
  ),
  Assets: o(
    [
      { json: 'enqueue', js: 'enqueue', typ: u(undefined, true) },
      { json: 'minify', js: 'minify', typ: u(undefined, r('Minify')) },
      { json: 'process', js: 'process', typ: u(undefined, r('Process')) },
    ],
    'any',
  ),
  Minify: o(
    [
      { json: 'css', js: 'css', typ: u(undefined, true) },
      { json: 'js', js: 'js', typ: u(undefined, true) },
    ],
    'any',
  ),
  Process: o(
    [
      { json: 'scss', js: 'scss', typ: u(undefined, true) },
      { json: 'scssFiles', js: 'scssFiles', typ: u(undefined, true) },
    ],
    'any',
  ),
  BlockEditor: o(
    [
      { json: 'cssClasses', js: 'cssClasses', typ: u(undefined, a('any')) },
      { json: 'cssVariables', js: 'cssVariables', typ: u(undefined, a('any')) },
      { json: 'disableLoading', js: 'disableLoading', typ: u(undefined, true) },
    ],
    'any',
  ),
  Editor: o(
    [
      { json: 'assets', js: 'assets', typ: u(undefined, a('any')) },
      { json: 'formatOnSave', js: 'formatOnSave', typ: u(undefined, true) },
      { json: 'markup', js: 'markup', typ: u(undefined, u(true, '')) },
    ],
    'any',
  ),
  Tailwind: o(
    [
      { json: 'config', js: 'config', typ: u(undefined, '') },
      { json: 'enabled', js: 'enabled', typ: u(undefined, true) },
    ],
    'any',
  ),
  Tooling: o(
    [
      { json: 'canvas', js: 'canvas', typ: u(undefined, r('ToolingCanvas')) },
      {
        json: 'devtools',
        js: 'devtools',
        typ: u(undefined, r('ToolingDevtools')),
      },
    ],
    'any',
  ),
  ToolingCanvas: o(
    [{ json: 'enabled', js: 'enabled', typ: u(undefined, true) }],
    'any',
  ),
  ToolingDevtools: o(
    [{ json: 'enabled', js: 'enabled', typ: u(undefined, true) }],
    'any',
  ),
  Users: o(
    [
      { json: 'ids', js: 'ids', typ: u(undefined, a(0)) },
      { json: 'roles', js: 'roles', typ: u(undefined, a('')) },
    ],
    'any',
  ),
};
