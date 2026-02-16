import { HeroBlocks } from '@/components/hero-blocks';
import { AiContext } from '@/components/homepage/ai-context';
import { AssetProcessing } from '@/components/homepage/asset-processing';
import { Extensions } from '@/components/homepage/beyond-blocks';
import { Composition } from '@/components/homepage/composition';
import { DevTools } from '@/components/homepage/dev-tools';
import { DeveloperExperience } from '@/components/homepage/developer-experience';
import { FAQ } from '@/components/homepage/faq';
import { FieldTypes } from '@/components/homepage/field-types';
import { HeroLeft } from '@/components/homepage/hero-left';
import { HowItWorks } from '@/components/homepage/how-it-works';
import { HtmlParser } from '@/components/homepage/html-parser';
import { Pages } from '@/components/homepage/pages';
import { Patterns } from '@/components/homepage/patterns';
import { TailwindCSS } from '@/components/homepage/tailwind';
import { TemplateLanguages } from '@/components/homepage/template-languages';
import { PlusSection } from '@/components/plus-section';
import { SiteFooter } from '@/components/site-footer';
import { SiteLayout } from '@/components/site-layout';
import config from '../../onedocs.config';

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

          <section id="catchphrase" className="border-b">
            <div className="px-6 py-16 lg:px-16 xl:px-20 lg:py-24 flex flex-col items-center">
              <p
                className="text-2xl sm:text-3xl lg:text-4xl font-medium leading-snug text-balance text-center max-w-5xl bg-clip-text text-transparent"
                style={{
                  backgroundImage:
                    'linear-gradient(135deg, var(--color-fd-foreground) 0%, var(--color-fd-primary) 50%, var(--color-fd-foreground) 100%)',
                }}
              >
                Blockstudio deeply extends the core way of building blocks. JSON
                definitions, PHP templates, scoped assets. Nothing more than
                files, ready for the agents that will build what&apos;s next.
              </p>
            </div>
          </section>

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
              <DevTools />
              <AiContext />
              <FAQ />
              <div className="border-t">
                <PlusSection />
              </div>
            </div>
          </div>
        </div>

        <SiteFooter />
      </main>
    </SiteLayout>
  );
}
