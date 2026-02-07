import { HomePage, CTASection } from "onedocs";
import config from "../../onedocs.config";
import { HowItWorks } from "../components/homepage/how-it-works";
import { TemplateLanguages } from "../components/homepage/template-languages";
import { Pages } from "../components/homepage/pages";
import { Patterns } from "../components/homepage/patterns";
import { Extensions } from "../components/homepage/beyond-blocks";
import { FieldTypes } from "../components/homepage/field-types";
import { AssetProcessing } from "../components/homepage/asset-processing";
import { DeveloperExperience } from "../components/homepage/developer-experience";
import { FAQ } from "../components/homepage/faq";

export default function Home() {
  return (
    <HomePage config={config} packageName="blockstudio">
      <div className="w-full self-stretch border-b">
        <HowItWorks />
        <TemplateLanguages />
        <Pages />
        <Patterns />
        <Extensions />
        <FieldTypes />
        <AssetProcessing />
        <DeveloperExperience />
        <FAQ />
        <div className="border-t">
          <CTASection
            title="Ready to build?"
            description="Create your first block in under a minute."
            cta={{ label: "Get Started", href: "/docs/getting-started" }}
          />
        </div>
      </div>
    </HomePage>
  );
}
