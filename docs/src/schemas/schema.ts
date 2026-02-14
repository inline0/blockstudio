export const schema = async (extensions = false) => {
  const schema = extensions
    ? {}
    : await fetch(
        'https://raw.githubusercontent.com/WordPress/gutenberg/trunk/schemas/json/block.json',
      );

  const conditions = (block = false) => {
    return {
      conditions: {
        type: 'array',
        description: block
          ? 'Conditional logic detailing if the block is allowed in the current editor context.'
          : 'Conditional logic detailing when the field should be displayed in the editor.',
        items: {
          type: 'array',
          items: {
            type: 'object',
            required: ['operator'],
            properties: {
              id: {
                type: 'string',
                description:
                  'ID of the field whose value will be used for the conditionally render it.',
              },
              operator: {
                type: 'string',
                description: 'How the values should be compared.',
                enum: [
                  '==',
                  '!=',
                  'includes',
                  '!includes',
                  'empty',
                  '!empty',
                  '<',
                  '>',
                  '<=',
                  '>=',
                ],
              },
              type: {
                type: 'string',
                description: 'Condition type.',
              },
              value: {
                type: ['string', 'number', 'boolean'],
                description: 'Value that will be compared.',
              },
            },
          },
        },
      },
    };
  };

  const desc = {
    clearable: 'Whether the palette should have a clearing button or not.',
    disableCustomColors: 'Whether to allow custom color or not.',
    isShiftStepEnabled:
      'If true, enables mouse drag gesture to increment/decrement the number value. Holding SHIFT while dragging will increase the value by the shiftStep.',
    max: 'The maximum value length.',
    min: 'Minimum value length.',
    options: 'Options to choose from.',
    returnFormat: 'Specifies the return format value.',
    shiftStep:
      'Amount to increment by when the SHIFT key is held down. This shift value is a multiplier to the step value. For example, if the step value is 5, and shiftStep is 10, each jump would increment/decrement by 50.',
    step: 'Amount by which the value is changed when incrementing/decrementing. It is also a factor in validation as value must be a multiple of step (offset by min, if specified) to be valid. Accepts the special string value any that voids the validation constraint and causes stepping actions to increment/decrement by 1.',
  };

  const def = (type: any = 'string') => {
    return {
      default: {
        type,
        description:
          'Default value that should be applied when first adding the block.',
      },
      fallback: {
        type,
        description:
          'Fallback value that that will display when field value is empty.',
      },
    };
  };

  const populate = (fetch = false) => {
    return {
      populate: {
        type: 'object',
        properties: {
          ...(fetch
            ? {
                fetch: {
                  type: 'boolean',
                  description:
                    'If true, search value will be used to search through data. Only works with "query" type.',
                },
              }
            : {}),
          function: {
            type: 'string',
            description: 'The function that should be executed.',
          },
          type: {
            type: 'string',
            enum: ['query', 'function', 'custom', 'fetch'],
          },
          query: {
            type: 'string',
            enum: ['posts', 'users', 'terms'],
            description: 'Type of query that should be used to fetch data.',
          },
          arguments: {
            type: ['object', 'array'],
            description: 'Query or fetch arguments.',
            properties: {
              urlSearch: {
                type: 'string',
                description: 'Search URL when using the "fetch" type.',
              },
            },
          },
          custom: {
            type: 'string',
            description: 'Custom data ID.',
          },
          position: {
            type: 'string',
            description:
              'How the data should be positioned in regards to the default options.',
            enum: ['before', 'after'],
            default: 'after',
          },
          returnFormat: {
            type: 'object',
            description: 'Format of the returning data when using objects.',
            properties: {
              value: {
                type: 'string',
              },
              label: {
                type: 'string',
              },
            },
          },
        },
      },
    };
  };

  const populateColor = () => {
    const pop = populate();

    return {
      populate: {
        type: 'object',
        properties: {
          function: {
            ...pop.populate.properties.function,
          },
          type: {
            ...pop.populate.properties.type,
            enum: ['function', 'custom'],
          },
          custom: {
            ...pop.populate.properties.custom,
          },
          position: {
            ...pop.populate.properties.position,
          },
        },
      },
    };
  };

  const innerBlocks = {
    innerBlocks: {
      type: 'array',
      items: {
        $ref: '#/definitions/Block',
      },
    },
  };

  const options = (ib = false) => {
    return {
      options: {
        type: 'array',
        items: {
          type: 'object',
          properties: {
            label: {
              type: 'string',
            },
            value: {
              type: ['string', 'number'],
            },
            ...(ib ? innerBlocks : {}),
          },
          required: ['value'],
        },
        description: desc.options,
      },
      returnFormat: {
        type: 'string',
        description: desc.returnFormat,
        enum: ['value', 'label', 'both'],
        default: 'value',
      },
    };
  };

  const optionsColor = {
    options: {
      type: 'array',
      ...def('array'),
      items: {
        type: 'object',
        properties: {
          name: {
            type: 'string',
          },
          value: {
            type: 'string',
          },
          slug: {
            type: 'string',
          },
        },
        description: desc.options,
      },
    },
  };

  const optionsText = {
    ...def(),
    max: { type: 'number', description: desc.max },
    min: { type: 'number', description: desc.min },
  };

  const attributes = {
    example: 'attributes',
    description: 'Renders data attribute inputs.',
    properties: {
      type: { const: 'attributes' },
      ...def('array'),
      link: {
        type: 'boolean',
        description: 'Enables link selection from dropdown.',
      },
      media: {
        type: 'boolean',
        description: 'Enables media selection from dropdown.',
      },
    },
  };

  const checkbox = {
    example: 'option-multiple',
    description: 'Renders a set of checkbox inputs.',
    properties: {
      type: { const: 'checkbox' },
      ...def(['array', 'string', 'number']),
      ...options(),
      ...populate(),
    },
  };

  const classes = {
    example: 'single',
    description: 'Renders a field to select CSS classes.',
    properties: {
      type: { const: 'classes' },
      ...def(['string']),
      tailwind: {
        type: 'boolean',
        description: 'Whether to enable Tailwind classes for this input field.',
      },
    },
  };

  const code = {
    example: 'single',
    description: 'Renders a code editor.',
    properties: {
      type: { const: 'code' },
      ...def(),
      autoCompletion: {
        type: 'boolean',
        description: 'Whether to enable autocompletion or not.',
      },
      foldGutter: {
        type: 'boolean',
        description: 'Whether to show the fold gutter or not.',
      },
      height: {
        type: 'string',
        description: 'The height of the editor.',
      },

      language: {
        type: 'string',
        description: 'The language to use for syntax highlighting.',
        enum: ['css', 'html', 'javascript', 'json', 'twig'],
      },
      lineNumbers: {
        type: 'boolean',
        description: 'Whether to display line numbers or not.',
      },
      maxHeight: {
        type: 'string',
        description: 'The maximum height of the editor.',
      },
      minHeight: {
        type: 'string',
        description: 'The minimum height of the editor.',
      },
      popout: {
        type: 'boolean',
        description:
          'Whether to show a button that opens the editor in a popup window.',
      },
    },
  };

  const color = {
    example: 'option',
    description: 'Renders a color palette and color picker.',
    properties: {
      type: { const: 'color' },
      ...optionsColor,
      ...populateColor(),
      clearable: {
        type: 'boolean',
        description: desc.clearable,
      },
      disableCustomColors: {
        type: 'boolean',
        description: desc.disableCustomColors,
      },
    },
  };

  const date = {
    example: 'single',
    description: 'Renders a date picker.',
    properties: {
      type: { const: 'date' },
      ...def(),
      startOfWeek: {
        type: 'number',
        description:
          'The day that the week should start on. 0 for Sunday, 1 for Monday, etc.',
      },
    },
  };

  const datetime = {
    example: 'single',
    description: 'Renders a date and time picker.',
    properties: {
      type: { const: 'datetime' },
      ...def(),
      is12Hour: {
        type: 'boolean',
        description:
          'Whether we use a 12-hour clock. With a 12-hour clock, an AM/PM widget is displayed and the time format is assumed to be MM-DD-YYYY (as opposed to the default format DD-MM-YYYY).',
      },
    },
  };

  const files = {
    example: 'files',
    description:
      'Renders a button to the media library. Picked items can be reordered inline.',
    properties: {
      type: { const: 'files' },
      ...def(['array', 'object', 'number']),
      addToGallery: {
        type: 'boolean',
        description:
          'If true, the gallery media modal opens directly in the media library where the user can add additional images. If false the gallery media modal opens in the edit mode where the user can edit existing images, by reordering them, remove them, or change their attributes. Only applies if gallery === true.',
      },
      allowedTypes: {
        type: ['array', 'string'],
        description:
          "Array with the types of the media to upload/select from the media library. Each type is a string that can contain the general mime type e.g: 'image', 'audio', 'text', or the complete mime type e.g: 'audio/mpeg', 'image/gif'. If allowedTypes is unset all mime types should be allowed.",
      },
      gallery: {
        type: 'boolean',
        description:
          'If true, the component will initiate all the states required to represent a gallery. By default, the media modal opens in the gallery edit frame, but that can be changed using the addToGalleryflag.',
      },
      max: {
        type: 'number',
        description: 'Maximum amount of files that can be added.',
      },
      min: {
        type: 'number',
        description: 'Minimum amount of files that can be added.',
      },
      multiple: {
        type: 'boolean',
        description: 'Whether to allow multiple selections or not.',
      },
      size: {
        type: 'boolean',
        description: 'Adds a media size dropdown to the field.',
      },
      textMediaButton: {
        type: 'string',
        description: 'Media button text.',
      },
      title: {
        type: 'string',
        description: 'Title displayed in the media modal.',
      },
      returnFormat: {
        type: 'string',
        description: desc.returnFormat,
        enum: ['object', 'id', 'url'],
        default: 'object',
      },
      returnSize: {
        type: 'string',
        description:
          'The media size to return when using the URL return format.',
      },
    },
  };

  const gradient = {
    example: 'option',
    description: 'Renders a gradient palette and gradient picker',
    properties: {
      type: { const: 'gradient' },
      ...optionsColor,
      ...populateColor(),
      clearable: {
        type: 'boolean',
        description: desc.clearable,
      },
      disableCustomGradients: {
        type: 'boolean',
        description: desc.disableCustomColors,
      },
    },
  };

  const icon = {
    example: 'icon',
    description: 'Renders an SVG icon from an icon set.',
    properties: {
      ...def('object'),
      type: { const: 'icon' },
      sets: {
        type: ['array', 'string'],
        description: 'Which icon set to include. Leave empty to include all.',
      },
      subSets: {
        type: ['array', 'string'],
        description:
          'Which sub icon set to include. Leave empty to include all.',
      },
      returnFormat: {
        type: 'string',
        description: 'The format to return the icon in.',
        enum: ['object', 'element'],
      },
    },
  };

  const link = {
    example: 'link',
    description: 'Renders a link control to choose internal or external links.',
    properties: {
      type: { const: 'link' },
      ...def('object'),
      hasRichPreviews: {
        type: 'boolean',
        description:
          'Whether rich previews should be shown when adding an URL.',
      },
      noDirectEntry: {
        type: 'boolean',
        description:
          'Whether to allow turning a URL-like search query directly into a link.',
      },
      noURLSuggestion: {
        type: 'boolean',
        description:
          'Whether to add a fallback suggestion which treats the search query as a URL.',
      },
      opensInNewTab: {
        type: 'boolean',
        description: 'Adds a toggle control to the link modal.',
      },
      showSuggestions: {
        type: 'boolean',
        description: 'Whether to present suggestions when typing the URL.',
      },
      textButton: {
        type: 'string',
        description:
          'Custom text that should be displayed inside the link button.',
      },
      withCreateSuggestion: {
        type: 'boolean',
        description: 'Whether to allow creation of link value from suggestion.',
      },
    },
  };

  const message = {
    description: 'Renders a message with custom content.',
    properties: {
      type: { const: 'message' },
      ...def('string'),
      value: {
        type: 'string',
        description:
          'The message to display. Block and attribute data is available in bracket syntax, e.g.: `{block.title}` or `{attributes.text}`',
      },
    },
  };

  const number = {
    example: 'single',
    description: 'Renders a number input.',
    properties: {
      type: { const: 'number' },
      ...def('number'),
      dragDirection: {
        type: 'string',
        description:
          'Determines the drag axis to increment/decrement the value.',
        enum: ['n', 'e', 's', 'w'],
      },
      dragThreshold: {
        type: 'number',
        description:
          'If isDragEnabled is true, this controls the amount of px to have been dragged before the value changes.',
      },
      hideHTMLArrows: {
        type: 'boolean',
        description: 'If true, the default input HTML arrows will be hidden.',
      },
      isShiftStepEnabled: {
        type: 'boolean',
        description: desc.isShiftStepEnabled,
      },
      max: { type: 'number', description: desc.max },
      min: { type: 'number', description: desc.min },
      required: {
        type: 'boolean',
        description:
          'If true enforces a valid number within the control’s min/max range. If false allows an empty string as a valid value.',
      },
      shiftStep: {
        type: 'number',
        description: desc.shiftStep,
      },
      step: {
        type: 'number',
        description: desc.step,
      },
    },
  };

  const radio = {
    example: 'option',
    description: 'Renders a set of radio inputs.',
    properties: {
      type: { const: 'radio' },
      ...def(['string', 'number']),
      ...options(true),
      ...populate(),
    },
  };

  const range = {
    example: 'single',
    description:
      'Renders a range input to set a numerical value between two points.',
    properties: {
      type: { const: 'range' },
      ...def('number'),
      allowReset: {
        type: 'boolean',
        description:
          'If this property is true, a button to reset the slider is rendered.',
      },
      initialPosition: {
        type: 'number',
        description:
          'The slider starting position, used when no value is passed. The initialPosition will be clamped between the provided min and max prop values.',
      },
      isShiftStepEnabled: {
        type: 'boolean',
        description: desc.isShiftStepEnabled,
      },
      marks: {
        type: 'array',
        items: {
          type: 'object',
          properties: { label: { type: 'string' }, value: { type: 'number' } },
        },
        description:
          'Renders a visual representation of step ticks. Custom mark indicators can be provided by an Array.',
      },
      max: { type: 'number', description: desc.max },
      min: { type: 'number', description: desc.min },
      railColor: {
        type: 'string',
        description:
          'CSS color string to customize the rail element’s background.',
      },
      resetFallbackValue: {
        type: 'number',
        description:
          'The value to revert to if the Reset button is clicked (enabled by allowReset)',
      },
      separatorType: {
        type: 'string',
        description:
          'Define if separator line under/above control row should be disabled or full width. By default it is placed below excluding underline the control icon.',
        enum: ['none', 'fullWidth', 'topFullWidth'],
      },
      shiftStep: { type: 'number', description: desc.shiftStep },
      showTooltip: {
        type: 'boolean',
        description:
          'Forcing the Tooltip UI to show or hide. This is overridden to false when step is set to the special string value any.',
      },
      step: { type: 'number', description: desc.step },
      trackColor: {
        type: 'string',
        description:
          'CSS color string to customize the track element’s background.',
      },
      withInputField: {
        type: 'boolean',
        description:
          'Determines if the input number field will render next to the RangeControl. This is overridden to false when step is set to the special string value any.',
      },
    },
  };

  const select = {
    example: ['option', 'option-multiple'],
    description:
      'Renders a select input with support for single or multiple selections.',
    properties: {
      type: { const: 'select' },
      ...def(['array', 'string', 'number']),
      multiple: {
        type: 'boolean',
        description:
          'If true, multiple options can be selected. "stylisedUi" will be automatically enabled.',
      },
      ...options(true),
      ...populate(true),
      stylisedUi: {
        type: 'boolean',
        description:
          'Renders a stylised version of a select with the ability to search through items.',
      },
      allowNull: {
        type: ['boolean', 'string'],
        description:
          'Allows the user to select an empty choice. If true, the label will be empty, otherwise the option will render the specified string.',
      },
      allowReset: {
        type: 'boolean',
        description:
          'If this property is true, a button to reset the select is rendered.',
      },
    },
  };

  const text = {
    example: 'single',
    description: 'Renders a single line text input.',
    properties: {
      type: { const: 'text' },
      ...optionsText,
    },
  };

  const textArea = {
    example: 'single',
    description: 'Renders a textarea input.',
    properties: {
      type: { const: 'textarea' },
      ...optionsText,
      rows: {
        type: 'number',
        description: 'The number of rows the textarea should contain.',
      },
    },
  };

  const toggle = {
    example: 'toggle',
    description: 'Renders a true/false toggle.',
    properties: {
      type: { const: 'toggle' },
      ...def('boolean'),
    },
  };

  const richtext = {
    example: 'single',
    description: 'Attribute field for RichText fields.',
    properties: {
      type: { const: 'richtext' },
      ...def(),
    },
  };

  const unit = {
    example: 'single',
    description: 'Renders a number input with a unit dropdown.',
    properties: {
      type: { const: 'unit' },
      ...def(),
      disableUnits: {
        type: 'boolean',
        description: 'If true, the unit select field is hidden.',
      },
      isPressEnterToChange: {
        type: 'boolean',
        description:
          'If true, the ENTER key press is required in order to trigger an onChange. If enabled, a change is also triggered when tabbing away.',
      },
      isResetValueOnUnitChange: {
        type: 'boolean',
        description:
          'If true, and the selected unit provides a default value, this value is set when changing units.',
      },
      isUnitSelectTabbable: {
        type: 'boolean',
        description: 'Determines if the unit select field is tabbable.',
      },
      units: {
        type: 'array',
        ...def('array'),
        items: {
          type: 'object',
          properties: {
            default: {
              type: 'number',
            },
            label: {
              type: 'string',
            },
            value: {
              type: 'string',
            },
          },
        },
      },
    },
  };

  const wysiwyg = {
    example: 'single',
    description: 'Renders a WYSIWYG editor.',
    properties: {
      type: { const: 'wysiwyg' },
      ...def(),
      toolbar: {
        type: 'object',
        description: 'The toolbar configuration for the editor.',
        properties: {
          tags: {
            type: 'object',
            description: 'Which HTML tags are allowed.',
            properties: {
              headings: {
                type: 'array',
                description: 'Which HTML headings levels are allowed.',
              },
            },
          },
          formats: {
            type: 'object',
            description: 'Which text formats are allowed.',
            properties: {
              bold: {
                type: 'boolean',
                description: 'Whether bold text format is allowed.',
              },
              italic: {
                type: 'boolean',
                description: 'Whether italic text format is allowed.',
              },
              orderedList: {
                type: 'boolean',
                description: 'Whether ordered list text format is allowed.',
              },
              strikethrough: {
                type: 'boolean',
                description: 'Whether strikethrough text format is allowed.',
              },
              underline: {
                type: 'boolean',
                description: 'Whether underline text format is allowed.',
              },
              unorderedList: {
                type: 'boolean',
                description: 'Whether unordered list text format is allowed.',
              },
              textAlign: {
                type: 'object',
                description: 'Which text alignment formats are allowed.',
                properties: {
                  alignments: {
                    type: 'array',
                    description: 'Which text alignments are allowed.',
                    items: {
                      type: 'string',
                      enum: ['left', 'center', 'right', 'justify'],
                    },
                  },
                },
              },
            },
          },
        },
      },
    },
  };

  // @ts-ignore
  function properties(g = true, r = true, t = true, attribute = false) {
    return {
      attributes: {
        type: 'array',
        description: g
          ? 'Custom attributes that will be applied to the block.'
          : t
            ? 'Accepts all other field types besides another tabs.'
            : 'Accepts all other field types besides another group.',
        items: attribute
          ? {
              $ref: '#/definitions/Attribute',
            }
          : {
              type: 'object',
              required: g || t ? ['type'] : ['type', 'id'],
              properties: {
                id: {
                  type: 'string',
                  description:
                    'A unique identifier for the field, which will be used get the value inside block templates. Must be unique within the current context.',
                },
                key: {
                  type: 'string',
                  description:
                    'Another identifier that can be used to uniquely identify fields across different contexts. (inside repeaters etc.)',
                },
                type: {
                  type: 'string',
                  anyOf: [
                    {
                      enum: [
                        'attributes',
                        'checkbox',
                        'classes',
                        'code',
                        'color',
                        'date',
                        'datetime',
                        'files',
                        'gradient',
                        g ? 'group' : false,
                        'icon',
                        'link',
                        'message',
                        'number',
                        'radio',
                        'range',
                        'select',
                        'tabs',
                        'text',
                        'textarea',
                        'toggle',
                        'repeater',
                        'richtext',
                        'unit',
                        'wysiwyg',
                      ].filter(Boolean),
                    },
                    {
                      pattern: '^custom/.+$',
                      description:
                        "Custom field reference in format 'custom/{name}'.",
                    },
                  ],
                },
                label: {
                  type: 'string',
                  description: 'The label for the field.',
                },
                description: {
                  type: 'string',
                  description:
                    'The description for the field. Will be displayed underneath the field.',
                },
                help: {
                  type: 'string',
                  description:
                    'The help text for the field. Will be displayed in a tooltip next to the label.',
                },
                hidden: {
                  type: 'boolean',
                  description:
                    'Whether to hide the field UI in the editor. This is handy when using the variations API.',
                },
                storage: {
                  type: 'object',
                  description:
                    'Configure where the field value should be stored.',
                  properties: {
                    type: {
                      type: ['string', 'array'],
                      description:
                        'Storage location(s). Can be a single type or array of types.',
                      enum: ['block', 'postMeta', 'option'],
                    },
                    postMetaKey: {
                      type: 'string',
                      description:
                        'Custom meta key for post meta storage. Defaults to {block_name}_{field_id}.',
                    },
                    optionKey: {
                      type: 'string',
                      description:
                        'Custom option key for options storage. Defaults to {block_name}_{field_id}.',
                    },
                  },
                },
                ...(extensions
                  ? {
                      set: {
                        type: 'array',
                        items: {
                          type: 'object',
                          properties: {
                            attribute: {
                              type: 'string',
                            },
                            value: {
                              type: 'string',
                            },
                          },
                          required: ['attribute', 'value'],
                          additionalProperties: false,
                        },
                      },
                    }
                  : {}),
                switch: {
                  type: 'boolean',
                  description:
                    'Display a toggle that can disable the field. Shows an eye icon in the field label. Defaults to false.',
                  default: false,
                },
                idStructure: {
                  type: 'string',
                  description:
                    'ID pattern for expanded custom fields. Use {id} as placeholder for the original field ID.',
                  default: '{id}',
                  example: 'hero_{id}',
                },
                overrides: {
                  type: 'object',
                  description:
                    'Per-field property overrides for custom fields. Keys are original field IDs from the field definition.',
                  additionalProperties: {
                    type: 'object',
                  },
                },
                ...conditions(),
                ...(attribute
                  ? {
                      attributes: {
                        type: 'array',
                        items: {
                          $ref: '#/definitions/Attribute',
                        },
                      },
                    }
                  : {}),
              },
              anyOf: [
                { ...attributes },
                { ...checkbox },
                { ...classes },
                { ...code },
                { ...color },
                { ...date },
                { ...datetime },
                { ...files },
                { ...gradient },
                g ? group() : false,
                { ...icon },
                { ...link },
                { ...message },
                { ...number },
                { ...radio },
                { ...range },
                { ...select },
                t ? tabs() : false,
                { ...text },
                { ...textArea },
                { ...toggle },
                r ? repeater() : false,
                { ...richtext },
                { ...unit },
                { ...wysiwyg },
                {
                  properties: {
                    type: {
                      pattern: '^custom/.+$',
                      description:
                        'References a reusable custom field definition.',
                    },
                  },
                  required: ['type'],
                },
              ].filter(Boolean),
            },
      },
      ...(g && r && t
        ? {
            ...conditions(false),
            blockEditor: {
              type: 'object',
              description:
                'Block specific options for rendering inside the Block Editor.',
              properties: {
                disableLoading: {
                  type: 'boolean',
                  description:
                    'Whether the block should should load inside the Block Editor or show a placeholder.',
                },
              },
            },
            editor: {
              type: 'object',
              description: 'Block specific options for the editor.',
              properties: {
                assets: {
                  type: 'array',
                  description:
                    'List of WordPress script or style handles that should be added to the preview.',
                  items: {
                    type: 'string',
                  },
                },
              },
            },
            interactivity: {
              oneOf: [
                { type: 'boolean' },
                {
                  type: 'object',
                  properties: {
                    enqueue: {
                      type: 'boolean',
                      description: 'Enqueue the Interactivity API.',
                    },
                  },
                },
              ],
              description:
                'Enable the WordPress Interactivity API for this block.',
            },
            ...(extensions
              ? {
                  extend: {
                    type: 'boolean',
                    description:
                      'Whether the configuration is a block extension.',
                  },
                  group: {
                    type: 'string',
                    description: 'Inspector control groups.',
                    enum: ['settings', 'styles', 'advanced'],
                  },
                }
              : {}),
            icon: {
              type: 'string',
              description: 'Custom SVG icon to be displayed inside the editor.',
            },
            innerBlocks: {
              type: 'string',
              description:
                'HTML content that will be rendered as the inner blocks.',
            },
            override: {
              type: 'boolean',
              description: 'Whether this block should overwrite another block.',
            },
            refreshOn: {
              type: 'array',
              description:
                'When the block should refresh. This is useful when the block relies on external data like custom fields.',
              items: {
                type: 'string',
              },
            },
            transforms: {
              type: 'object',
              description: 'Custom block transforms.',
              properties: {
                from: {
                  type: 'array',
                  items: {
                    type: 'object',
                    properties: {
                      type: {
                        type: 'string',
                        enum: ['block', 'enter', 'prefix'],
                      },
                      blocks: {
                        type: 'array',
                        items: {
                          type: 'string',
                        },
                      },
                      regExp: {
                        type: 'string',
                      },
                      prefix: {
                        type: 'string',
                      },
                    },
                    required: ['type'],
                    additionalProperties: false,
                  },
                },
              },
              required: ['from'],
              additionalProperties: false,
            },
          }
        : {}),
    };
  }

  // @ts-ignore
  const group = () => {
    return {
      description:
        'Renders multiple fields in an (optionally) collapsible container.',
      properties: {
        type: { const: 'group' },
        title: {
          type: 'string',
          description:
            'Title text. It shows even when the component is closed.',
        },
        opened: {
          type: 'boolean',
          description:
            'When set to true, the component will remain open regardless of the initialOpen prop and the',
        },
        initialOpen: {
          type: 'boolean',
          description: 'Whether or not the panel will start open.',
        },
        scrollAfterOpen: {
          type: 'boolean',
          description: 'Scrolls the content into view when visible.',
        },
        class: {
          type: 'string',
          description: 'Custom CSS class that will be applied to the group.',
        },
        style: {
          type: 'object',
          description: 'Custom CSS styles that will be applied to the group.',
        },
        ...properties(false, true, true),
      },
    };
  };

  // @ts-ignore
  const repeater = () => {
    return {
      description: 'Renders a set of fields that can be repeated.',
      properties: {
        type: { const: 'repeater' },
        min: { type: 'number', description: 'Minimum amount of rows.' },
        max: { type: 'number', description: 'Maximum amount of rows.' },
        textButton: { type: 'string', description: 'Text for the add button.' },
        textMinimized: {
          type: ['string', 'object'],
          description: 'Text that will be displayed when rows are minimized.',
          properties: {
            id: {
              type: 'string',
              description:
                'ID of the attribute which should be used as the text.',
            },
            fallback: {
              type: 'string',
              description: 'Fallback text if the attribute is not set.',
            },
            prefix: {
              type: 'string',
              description: 'Prefix for the text.',
            },
            suffix: {
              type: 'string',
              description: 'Suffix for the text.',
            },
          },
        },
        textRemove: {
          type: ['string', 'boolean'],
          description: 'Text to display in alert when removing repeater row.',
        },
        ...properties(false, false, true, true),
      },
    };
  };

  // @ts-ignore
  const tabs = () => {
    return {
      description: 'Renders a tabbed interface for grouping fields.',
      properties: {
        type: { const: 'tabs' },
        tabs: {
          type: 'array',
          description: 'The tabs to display.',
          items: {
            type: 'object',
            required: ['title'],
            properties: {
              title: {
                type: 'string',
                description: 'The title of the tab.',
              },
              ...properties(true, true, true, true),
            },
          },
        },
      },
    };
  };

  const data = extensions ? {} : await (schema as Response).json();
  if (!extensions) {
    delete data.properties.properties;
  }

  return {
    ...data,
    title: 'JSON schema for Blockstudio',
    properties: {
      ...data.properties,
      blockstudio: {
        type: ['object', 'boolean'],
        description: 'Blockstudio specific settings.',
        properties: {
          ...properties(),
        },
        additionalProperties: false,
      },
    },
    definitions: {
      Attribute: {
        type: 'object',
        ...properties(false, false, true).attributes.items,
      },
      Block: {
        type: 'object',
        properties: {
          name: {
            type: 'string',
          },
          innerBlocks: {
            type: 'array',
            items: {
              $ref: '#/definitions/Block',
            },
          },
          attributes: {
            type: 'object',
            additionalProperties: true,
          },
        },
        required: ['name'],
      },
    },
  };
};
