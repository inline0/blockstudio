type Attributes = Record<string, unknown>;

const object = (value: unknown): value is Attributes =>
  value !== null && typeof value === 'object' && !Array.isArray(value);

export const normalizeImportedAttributes = (
  parsed: Attributes,
  raw: Attributes,
  definitions: unknown,
): Attributes => {
  if (!object(definitions)) return parsed;
  if ('blockstudio' in raw && !object(raw.blockstudio)) return parsed;
  const settings = object(raw.blockstudio) ? raw.blockstudio : {};
  if ('attributes' in settings && !object(settings.attributes)) return parsed;
  const explicit = object(settings.attributes) ? settings.attributes : {};
  const inherited = object(parsed.blockstudio) ? parsed.blockstudio : {};
  const values = {
    ...(object(inherited.attributes) ? inherited.attributes : {}),
  };
  const result = { ...parsed };
  let changed = false;

  for (const [key, value] of Object.entries(raw)) {
    if (!Object.prototype.hasOwnProperty.call(definitions, key)) continue;
    const definition = definitions[key];
    if (
      !object(definition) ||
      !definition.blockstudio ||
      typeof definition.field !== 'string'
    )
      continue;
    if (Object.prototype.hasOwnProperty.call(explicit, key)) continue;

    // Core has already checked ordinary attributes. Recover scalar selections
    // from the original comment, not the default object core substituted.
    const option = ['select', 'radio', 'checkbox'].includes(definition.field);
    const scalar =
      value === null || ['string', 'number', 'boolean'].includes(typeof value);
    const adopted = option && scalar ? value : parsed[key];
    if (adopted === undefined) continue;
    values[key] = adopted;
    delete result[key];
    changed = true;
  }

  if (!changed) return parsed;
  result.blockstudio = {
    ...inherited,
    ...settings,
    attributes: { ...values, ...explicit },
  };
  return result;
};
