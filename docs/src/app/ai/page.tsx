import type { Metadata } from "next";
import { AiPage } from "@/components/ai-page";

export const metadata: Metadata = {
  title: "Build with AI",
  description:
    "Blockstudio's file-based architecture is built for AI coding agents. Describe a block, get a block.",
};

export default function Page() {
  return <AiPage />;
}
