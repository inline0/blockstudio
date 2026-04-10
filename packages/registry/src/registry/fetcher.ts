import { registrySchema, type Registry } from "../config/schema.js";

const cache = new Map<string, Registry>();

export function clearCache(): void {
  cache.clear();
}

export async function fetchRegistry(
  url: string,
  headers?: Record<string, string>,
): Promise<Registry> {
  const cached = cache.get(url);
  if (cached) return cached;

  let response: Response;
  try {
    response = await fetch(url, {
      headers: headers && Object.keys(headers).length > 0 ? headers : undefined,
    });
  } catch {
    throw new Error(`Could not reach registry at ${url}. Are you online?`);
  }

  if (!response.ok) {
    throw new Error(
      `Registry returned ${response.status} for ${url}`,
    );
  }

  let json: unknown;
  try {
    json = await response.json();
  } catch {
    throw new Error(`Invalid JSON from registry at ${url}`);
  }

  const result = registrySchema.safeParse(json);
  if (!result.success) {
    const issues = result.error.issues
      .map((i) => `  ${i.path.join(".")}: ${i.message}`)
      .join("\n");
    throw new Error(`Invalid registry at ${url}:\n${issues}`);
  }

  cache.set(url, result.data);
  return result.data;
}
