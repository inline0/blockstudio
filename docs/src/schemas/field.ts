export const field = {
  title: 'JSON schema for Blockstudio custom field definitions',
  $schema: 'http://json-schema.org/draft-04/schema#',
  type: 'object',
  required: ['name', 'attributes'],
  properties: {
    name: {
      type: 'string',
      description:
        "Unique identifier for the custom field. Referenced as 'custom/{name}' in block.json.",
      example: 'hero',
    },
    title: {
      type: 'string',
      description: 'Display title for the custom field definition.',
      example: 'Hero Section',
    },
    attributes: {
      type: 'array',
      description:
        'Array of field definitions. Same format as blockstudio.attributes in block.json.',
      items: {
        type: 'object',
        required: ['id', 'type'],
        properties: {
          id: {
            type: 'string',
            description: 'Unique field identifier.',
          },
          type: {
            type: 'string',
            description: 'Field type (text, textarea, toggle, select, etc.).',
          },
          label: {
            type: 'string',
            description: 'Display label for the field.',
          },
          default: {
            description: 'Default value for the field.',
          },
          description: {
            type: 'string',
            description: 'Help text shown below the label.',
          },
        },
      },
    },
  },
  additionalProperties: true,
};
