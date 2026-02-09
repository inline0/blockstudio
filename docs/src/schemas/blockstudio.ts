export const blockstudio = {
  title: "JSON schema for Blockstudio settings",
  $schema: "http://json-schema.org/draft-04/schema#",
  type: "object",
  properties: {
    users: {
      type: "object",
      description:
        "Settings related to allowed users with access to the settings and editor.",
      properties: {
        ids: {
          id: "userIds",
          type: "array",
          default: [],
          items: {
            type: "integer",
          },
          description:
            "List of user IDs with access to the settings and editor.",
          descriptionFilter:
            "This filter allows you to enable the editor for specific user IDs.",
          help: "Comma separated list of user IDs. Example: 1,2,3",
          example: [1],
        },
        roles: {
          id: "userRoles",
          type: "array",
          default: [],
          items: {
            type: "string",
          },
          description:
            "List of user roles with access to the settings and editor.",
          descriptionFilter:
            "This filter allows you to enable the editor for specific user roles.",
          help: "Comma separated list of user roles. Example: administrator,editor",
          example: ["administrator", "editor"],
        },
      },
    },
    assets: {
      type: "object",
      description: "Settings related to asset management.",
      properties: {
        enqueue: {
          type: "boolean",
          default: true,
          description: "Enqueue assets in frontend and editor.",
          descriptionFilter:
            "This filter allows you to enable/disable the enqueueing of assets in frontend and editor.",
          example: false,
        },
        minify: {
          type: "object",
          description: "Settings related to asset minification.",
          properties: {
            css: {
              type: "boolean",
              default: false,
              description: "Minify CSS.",
              descriptionFilter:
                "This filter allows you to enable/disable the minification of CSS.",
              example: true,
            },
            js: {
              type: "boolean",
              default: false,
              description: "Minify JS.",
              descriptionFilter:
                "This filter allows you to enable/disable the minification of JS.",
              example: true,
            },
          },
        },
        process: {
          type: "object",
          description: "Settings related to asset processing.",
          properties: {
            scss: {
              type: "boolean",
              default: false,
              description: "Process SCSS in .css files.",
              descriptionFilter:
                "This filter allows you to enable/disable the processing of SCSS in .css files.",
              example: true,
            },
            scssFiles: {
              type: "boolean",
              default: true,
              description: "Process .scss files to CSS.",
              descriptionFilter:
                "This filter allows you to enable/disable the processing of .scss files to CSS.",
              example: true,
            },
          },
        },
      },
      additionalProperties: true,
    },
    tailwind: {
      type: "object",
      description: "Settings related to Tailwind.",
      properties: {
        enabled: {
          type: "boolean",
          default: false,
          description: "Enable Tailwind.",
          descriptionFilter:
            "This filter allows you to enable/disable Tailwind.",
          example: true,
        },
        config: {
          type: "string",
          default: "",
          description:
            "Tailwind CSS configuration using v4 CSS-first syntax.",
          descriptionFilter:
            "This filter allows you to add a custom Tailwind CSS configuration.",
          element: "textarea",
          example: "@theme { --color-primary: pink; }",
        },
      },
      additionalProperties: true,
    },
    editor: {
      type: "object",
      description: "Settings related to the editor.",
      properties: {
        formatOnSave: {
          type: "boolean",
          default: false,
          description: "Format code upon saving.",
          descriptionFilter:
            "This filter allows you to enable/disable the formatting of code upon saving.",
          example: true,
        },
        assets: {
          id: "editorAssets",
          type: "array",
          default: [],
          description: "Additional asset IDs to be enqueued.",
          descriptionFilter:
            "This filter allows you to enqueue additional assets in the editor.",
          example: ["my-stylesheet", "another-stylesheet"],
        },
        markup: {
          type: ["string", "boolean"],
          element: "textarea",
          default: "",
          description:
            "Additional markup to be added to the end of the editor.",
          descriptionFilter:
            "This filter allows you to add additional markup to the end of the editor.",
          example: "<style>body { background: black; }</style>",
        },
      },
      additionalProperties: true,
    },
    blockEditor: {
      type: "object",
      description: "Settings related to Gutenberg.",
      properties: {
        disableLoading: {
          type: "boolean",
          default: false,
          description: "Disable loading of blocks inside the Block Editor.",
          descriptionFilter:
            "This filter allows you to disable the loading of blocks inside the Block Editor.",
          example: true,
        },
        cssClasses: {
          id: "blockEditorCssClasses",
          type: "array",
          default: [],
          description:
            "Stylesheets whose CSS classes should be available for choice in the class field.",
          descriptionFilter:
            "This filter allows you to add stylesheets whose classes should be available for choice in the class field.",
          example: ["my-stylesheet", "another-stylesheet"],
        },
        cssVariables: {
          id: "blockEditorCssVariables",
          type: "array",
          default: [],
          description:
            "Stylesheets whose CSS variables should be available for autocompletion in the code field.",
          descriptionFilter:
            "This filter allows you to add stylesheets whose CSS variables should be available for autocompletion in the code field.",
          example: ["my-stylesheet", "another-stylesheet"],
        },
      },
      additionalProperties: true,
    },
    library: {
      type: "boolean",
      default: false,
      description: "Add block library.",
      descriptionFilter: "This filter allows you to enable the block library.",
      example: true,
    },
    ai: {
      id: "aiLlmMd",
      type: "object",
      description: "Settings related to AI-powered context generation.",
      properties: {
        enableContextGeneration: {
          type: "boolean",
          default: false,
          description:
            "Enables the automatic creation of a comprehensive context file for use with large language model (LLM) tools (e.g., Cursor). This file compiles current installation data: all available block definitions and paths, Blockstudio-specific settings, relevant block schemas, and combined Blockstudio documentation, providing a ready-to-use resource for prompt engineering and AI code development.",
          descriptionFilter:
            "This filter allows you to enable or disable context file generation for LLM tool integration. When enabled, the context file assembles up-to-date block data, Blockstudio settings of the current install, all relevant schemas, and Blockstudio documentation into a single source for use with AI development tools.",
          example: true,
        },
      },
      additionalProperties: true,
    },
    tooling: {
      type: "object",
      description: "Settings related to developer tooling.",
      properties: {
        devtools: {
          type: "object",
          description: "Settings related to the frontend devtools inspector.",
          properties: {
            enabled: {
              type: "boolean",
              default: false,
              description: "Enable the frontend devtools inspector.",
              descriptionFilter:
                "This filter allows you to enable/disable the frontend devtools inspector.",
              example: false,
            },
          },
        },
        canvas: {
          type: "object",
          description: "Settings related to the canvas.",
          properties: {
            enabled: {
              type: "boolean",
              default: false,
              description: "Enable the canvas.",
              descriptionFilter:
                "This filter allows you to enable/disable the canvas.",
              example: false,
            },
            adminBar: {
              type: "boolean",
              default: true,
              description:
                "Show the WordPress admin bar when viewing the canvas.",
              descriptionFilter:
                "This filter allows you to show/hide the WordPress admin bar on the canvas.",
              example: true,
            },
          },
        },
      },
      additionalProperties: true,
    },
  },
  additionalProperties: true,
};
