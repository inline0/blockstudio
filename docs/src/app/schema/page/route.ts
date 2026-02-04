import { page } from '@/schemas/page';

export async function GET() {
  return Response.json(page);
}
