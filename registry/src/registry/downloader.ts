import type { ResolvedBlock } from "./resolver.js";

export type DownloadedFile = {
  path: string;
  content: string;
};

export type DownloadedBlock = {
  block: ResolvedBlock;
  files: DownloadedFile[];
};

export async function downloadBlock(
  block: ResolvedBlock,
): Promise<DownloadedBlock> {
  const files: DownloadedFile[] = [];
  const fetchOpts = block.headers && Object.keys(block.headers).length > 0
    ? { headers: block.headers }
    : undefined;

  for (const file of block.files) {
    const url = `${block.registryBaseUrl}/${block.name}/${file}`;

    let response: Response;
    try {
      response = await fetch(url, fetchOpts);
    } catch {
      throw new Error(`Could not fetch ${url}. Are you online?`);
    }

    if (!response.ok) {
      throw new Error(`Failed to download ${url} (${response.status})`);
    }

    files.push({
      path: file,
      content: await response.text(),
    });
  }

  return { block, files };
}

export async function downloadBlocks(
  blocks: ResolvedBlock[],
): Promise<DownloadedBlock[]> {
  return Promise.all(blocks.map(downloadBlock));
}
