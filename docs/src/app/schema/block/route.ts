import { schema } from '@/schemas/schema';

export async function GET() {
  const json = await schema();
  return Response.json(json);
}
