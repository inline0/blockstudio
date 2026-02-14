export const page = {
  title: 'JSON schema for Blockstudio page definitions',
  $schema: 'http://json-schema.org/draft-04/schema#',
  type: 'object',
  required: ['name'],
  properties: {
    name: {
      type: 'string',
      description:
        'Unique identifier for the page. Used internally to track and reference the page definition.',
      example: 'about',
    },
    title: {
      type: 'string',
      description:
        'The title of the WordPress page/post. Defaults to a human-readable version of the name if not specified.',
      example: 'About Us',
    },
    slug: {
      type: 'string',
      description:
        'The URL slug for the page. Defaults to the name if not specified.',
      example: 'about-us',
    },
    postType: {
      type: 'string',
      default: 'page',
      description:
        'The WordPress post type to create. Can be any registered post type.',
      example: 'page',
    },
    postStatus: {
      type: 'string',
      default: 'draft',
      description:
        'The initial status for newly created posts. Does not affect existing posts.',
      enum: ['publish', 'draft', 'pending', 'private'],
      example: 'publish',
    },
    postId: {
      type: 'integer',
      description:
        'Pin the page to a specific WordPress post ID. Uses import_id during creation to request this ID. If the ID is already taken by an unrelated post, WordPress silently auto-assigns a new ID.',
      example: 42,
    },
    blockEditingMode: {
      type: 'string',
      enum: ['default', 'contentOnly', 'disabled'],
      description:
        "Controls how blocks can be edited. 'default' allows full editing, 'contentOnly' only allows text editing, 'disabled' prevents all editing.",
      example: 'disabled',
    },
    templateLock: {
      type: ['string', 'boolean'],
      default: 'all',
      description:
        "Controls how users can modify the block structure. 'all' prevents all modifications, 'insert' prevents adding/removing blocks, false allows full editing.",
      enum: ['all', 'insert', 'contentOnly', false],
      example: 'all',
    },
    templateFor: {
      type: ['string', 'null'],
      default: null,
      description:
        "When specified, this page's block structure becomes the default template for the specified post type. Any new posts of that type will start with this template.",
      example: 'product',
    },
    sync: {
      type: 'boolean',
      default: true,
      description:
        'Whether to automatically sync the page content when the template file changes. Set to false to create the page once and prevent future automatic updates.',
      example: true,
    },
  },
  additionalProperties: true,
};
