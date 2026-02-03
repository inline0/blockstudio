import { generateRobots } from "onedocs/seo";

const baseUrl = "https://blockstudio.dev";

export default function robots() {
  return generateRobots({ baseUrl });
}
