import { schema } from "./schema";

export const extend = async () => {
  const jsonData = await schema();

  return {
    title: "JSON schema for Blockstudio extends",
    $schema: "http://json-schema.org/draft-04/schema#",
    type: "object",
    properties: {
      $schema: {
        type: "string",
      },
      name: {
        type: ["string", "array"],
        description: "The name of the block to extend.",
      },
      blockstudio: {
        ...jsonData.properties.blockstudio,
        properties: {
          extend: {
            type: "object",
            description: "The extend block definition.",
            properties: {
              priority: {
                type: "number",
                description:
                  "In what spot the extension should be rendered in the sidebar.",
              },
            },
          },
          ...jsonData.properties.blockstudio.properties,
        },
      },
    },
    definitions: {
      ...jsonData.definitions,
    },
  };
};
