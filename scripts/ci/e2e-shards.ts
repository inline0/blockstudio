export function parseShard(value: string) {
  const match = /^([1-9]\d*)\/([1-9]\d*)$/.exec(value);
  if (!match) throw new Error('Expected E2E shard in the form current/total.');
  const current = Number(match[1]);
  const total = Number(match[2]);
  if (!Number.isSafeInteger(total) || current > total)
    throw new Error('Invalid E2E shard.');
  return { current, total };
}

export function partitionFiles(
  files: string[],
  seconds: Record<string, number>,
  total: number,
) {
  if (!Number.isSafeInteger(total) || total < 1 || total > files.length)
    throw new Error('Each E2E shard must contain at least one file.');
  if (new Set(files).size !== files.length)
    throw new Error('Duplicate E2E file.');
  const known = Object.values(seconds)
    .filter((value) => Number.isFinite(value) && value > 0)
    .sort((a, b) => a - b);
  const fallback = known[Math.floor(known.length / 2)] ?? 1;
  const weight = (file: string) =>
    Number.isFinite(seconds[file]) && seconds[file] > 0
      ? seconds[file]
      : fallback;
  const shards = Array.from({ length: total }, () => ({
    files: [] as string[],
    seconds: 0,
  }));
  for (const file of [...files].sort(
    (a, b) => weight(b) - weight(a) || a.localeCompare(b),
  )) {
    const target = shards.reduce((best, shard) =>
      shard.seconds < best.seconds ? shard : best,
    );
    target.files.push(file);
    target.seconds += weight(file);
  }
  for (const shard of shards) shard.files.sort();
  return shards;
}
