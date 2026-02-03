import { blockstudio } from '@/schemas/blockstudio';

export async function GET() {
  return Response.json(blockstudio);
}
