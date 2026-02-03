import { HomePage, CTASection } from "onedocs";
import config from "../../onedocs.config";

export default function Home() {
  return (
    <HomePage config={config} packageName="blockstudio">
      <CTASection
        title="Ready to build?"
        description="Get started with Blockstudio in minutes."
        cta={{ label: "Read the Docs", href: "/docs" }}
      />
    </HomePage>
  );
}
