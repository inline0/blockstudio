import { CTASection } from "onedocs";
import { SiteLayout } from "@/components/site-layout";
import { HeroLeft } from "@/components/homepage/hero-left";
import { HeroBlocks } from "@/components/hero-blocks";
import { HowItWorks } from "@/components/homepage/how-it-works";
import { TemplateLanguages } from "@/components/homepage/template-languages";
import { Pages } from "@/components/homepage/pages";
import { Patterns } from "@/components/homepage/patterns";
import { HtmlParser } from "@/components/homepage/html-parser";
import { Extensions } from "@/components/homepage/beyond-blocks";
import { FieldTypes } from "@/components/homepage/field-types";
import { AssetProcessing } from "@/components/homepage/asset-processing";
import { TailwindCSS } from "@/components/homepage/tailwind";
import { DeveloperExperience } from "@/components/homepage/developer-experience";
import { AiContext } from "@/components/homepage/ai-context";
import { Composition } from "@/components/homepage/composition";
import { FAQ } from "@/components/homepage/faq";
import config from "../../onedocs.config";

const { homepage } = config;
const features = homepage?.features ?? [];

export default function Home() {
  return (
    <SiteLayout>
      <main className="flex-1 flex flex-col min-h-[calc(100vh-var(--fd-nav-height))]">
        <div className="flex-1 flex flex-col relative mx-auto w-full max-w-(--fd-layout-width)">
          <div className="absolute inset-0 border-x pointer-events-none" />
          <div className="relative">
            <section id="hero">
              <div className="grid grid-cols-1 lg:grid-cols-4">
                <div className="lg:col-span-2 px-6 py-8 lg:px-16 lg:py-12 xl:px-20 xl:py-16">
                  <HeroLeft />
                </div>
                <div className="lg:col-span-2 hidden lg:block">
                  <div className="flex h-full items-center px-6 py-8 lg:px-16 lg:py-12 xl:px-20 xl:py-16">
                    <HeroBlocks />
                  </div>
                </div>
              </div>
            </section>

            {features.length > 0 && (
              <section id="features">
                <div className="border-y">
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 [&>*]:border-b [&>*:nth-last-child(-n+1)]:border-b-0 sm:[&>*:nth-last-child(-n+2)]:border-b-0 lg:[&>*:nth-last-child(-n+4)]:border-b-0">
                    {features.map((feature) => (
                      <div
                        key={feature.title}
                        className="flex flex-col gap-y-2 items-start justify-start p-8 transition-colors hover:bg-fd-secondary/20 sm:border-r sm:[&:nth-child(2n)]:border-r-0 lg:[&:nth-child(2n)]:border-r lg:[&:nth-child(4n)]:border-r-0"
                      >
                        {feature.icon && (
                          <div className="bg-fd-primary/10 p-2 rounded-lg mb-2">
                            {feature.icon}
                          </div>
                        )}
                        <h3 className="text-base font-medium text-fd-card-foreground">
                          {feature.title}
                        </h3>
                        <p className="text-sm text-fd-muted-foreground">
                          {feature.description}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>
              </section>
            )}
          </div>

          <div className="flex-1 flex items-center justify-center">
            <div className="w-full self-stretch border-b">
              <HowItWorks />
              <Composition />
              <TemplateLanguages />
              <Pages />
              <Patterns />
              <HtmlParser />
              <Extensions />
              <FieldTypes />
              <AssetProcessing />
              <TailwindCSS />
              <DeveloperExperience />
              <AiContext />
              <FAQ />
              <div className="border-t">
                <CTASection
                  title="Ready to build?"
                  description="Create your first block in under a minute."
                  cta={{ label: "Get Started", href: "/docs/getting-started" }}
                />
              </div>
            </div>
          </div>
        </div>

        <footer className="relative mx-auto w-full max-w-(--fd-layout-width)">
          <div className="border-x border-t px-6 py-4 flex items-center justify-between gap-4">
            <p className="text-sm text-fd-muted-foreground">
              &copy; {new Date().getFullYear()} {config.title}
            </p>
          </div>
        </footer>
      </main>
    </SiteLayout>
  );
}
