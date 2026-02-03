import { extend } from '@/schemas/extend';

export async function GET() {
  const json = await extend();
  return Response.json(json);
}
