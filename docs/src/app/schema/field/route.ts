import { field } from '@/schemas/field';

export async function GET() {
  return Response.json(field);
}
