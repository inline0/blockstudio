export interface CodeBlock {
  language: string;
  filename: string;
  content: string;
}

export function extractCodeBlocks(text: string): CodeBlock[] {
  const blocks: CodeBlock[] = [];
  const regex = /```(\w*)\s*(?:filename=["']?([^"'\n]+)["']?)?\s*\n([\s\S]*?)```/g;

  let match;
  while ((match = regex.exec(text)) !== null) {
    const language = match[1] || "";
    const filename = match[2] || "";
    const content = match[3].trim();
    blocks.push({ language, filename, content });
  }

  return blocks;
}

export function extractByLanguage(text: string, lang: string): CodeBlock[] {
  return extractCodeBlocks(text).filter(
    (b) => b.language.toLowerCase() === lang.toLowerCase()
  );
}

export function extractByFilename(text: string, pattern: string): CodeBlock[] {
  return extractCodeBlocks(text).filter(
    (b) =>
      b.filename.toLowerCase().includes(pattern.toLowerCase()) ||
      b.filename.toLowerCase().endsWith(pattern.toLowerCase())
  );
}

export function extractFirstJson(text: string): unknown | null {
  const blocks = extractByLanguage(text, "json");
  if (blocks.length === 0) return null;
  try {
    return JSON.parse(blocks[0].content);
  } catch {
    return null;
  }
}

export function extractAllJson(text: string): unknown[] {
  return extractByLanguage(text, "json")
    .map((b) => {
      try {
        return JSON.parse(b.content);
      } catch {
        return null;
      }
    })
    .filter((v): v is unknown => v !== null);
}

export function extractFirstPhp(text: string): string | null {
  const blocks = extractByLanguage(text, "php");
  return blocks.length > 0 ? blocks[0].content : null;
}
