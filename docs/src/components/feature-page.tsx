import { Button, CTASection } from "onedocs";
import { Feature } from "./homepage/feature";
import { CodeCard } from "./homepage/code-card";
import { CodeTabs } from "./homepage/code-tabs";
import { SectionIcon } from "./homepage/section";
import type { FeaturePageData } from "../data/features";
import config from "../../onedocs.config";

function FeatureDemo({ feature }: { feature: FeaturePageData["features"][number] }) {
  return Array.isArray(feature.code) ? (
    <CodeTabs items={feature.code} />
  ) : (
    <CodeCard code={feature.code} lang={feature.lang!} />
  );
}

function DetailGrid({ details }: { details: FeaturePageData["details"] }) {
  return (
    <section className="border-t">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 [&>*]:border-b [&>*:nth-last-child(-n+1)]:border-b-0 sm:[&>*:nth-last-child(-n+2)]:border-b-0 lg:[&>*:nth-last-child(-n+4)]:border-b-0">
        {details.map((detail) => (
          <div
            key={detail.title}
            className="flex flex-col gap-y-2 items-start justify-start p-8 transition-colors hover:bg-fd-secondary/20 sm:border-r sm:[&:nth-child(2n)]:border-r-0 lg:[&:nth-child(2n)]:border-r lg:[&:nth-child(4n)]:border-r-0"
          >
            <div className="bg-fd-primary/10 p-2 rounded-lg mb-2">
              <detail.icon className="h-4 w-4 text-fd-primary" />
            </div>
            <h3 className="text-base font-medium text-fd-card-foreground">
              {detail.title}
            </h3>
            <p className="text-sm text-fd-muted-foreground">
              {detail.description}
            </p>
          </div>
        ))}
      </div>
    </section>
  );
}

export async function FeaturePage({ data }: { data: FeaturePageData }) {
  const [spotlight, ...rest] = data.features;
  const detailsFirst = data.details.slice(0, 8);
  const detailsRest = data.details.slice(8);

  return (
    <div className="w-full self-stretch">
      <div className="relative overflow-hidden">
        <div className="absolute inset-0 pointer-events-none hidden dark:block bg-gradient-to-b from-transparent via-fd-primary/3 to-transparent" />
        <svg className="absolute inset-0 w-full h-full pointer-events-none opacity-2" aria-hidden="true">
          <filter id="grain">
            <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="4" stitchTiles="stitch" />
          </filter>
          <rect width="100%" height="100%" filter="url(#grain)" />
        </svg>

        <section className="relative px-6 pt-16 pb-10 sm:pt-20 sm:pb-12 lg:px-16 xl:px-20">
          <SectionIcon>
            <data.icon />
          </SectionIcon>
          <h1 className="text-3xl font-semibold tracking-tight text-fd-foreground sm:text-4xl">
            {data.title}
          </h1>
          <p className="mt-3 max-w-2xl text-fd-muted-foreground text-balance">
            {data.description}
          </p>
        </section>

        {spotlight && (
          <section className="relative px-6 pb-12 sm:pb-16 lg:px-16 xl:px-20">
            <Feature
            headline={spotlight.headline}
            description={spotlight.description}
            cta={
              <Button href={spotlight.ctaHref} className="w-max">
                {spotlight.ctaLabel} &rarr;
              </Button>
            }
            demo={<FeatureDemo feature={spotlight} />}
          />
          </section>
        )}
      </div>

      {detailsFirst.length > 0 && <DetailGrid details={detailsFirst} />}

      {rest.length > 0 && (
        <section className="border-t px-6 pt-12 pb-12 sm:pt-16 sm:pb-16 lg:px-16 xl:px-20">
          <div className="flex flex-col gap-4">
            {rest.map((feature, i) => (
              <Feature
                key={i}
                headline={feature.headline}
                description={feature.description}
                cta={
                  <Button href={feature.ctaHref} className="w-max">
                    {feature.ctaLabel} &rarr;
                  </Button>
                }
                demo={<FeatureDemo feature={feature} />}
              />
            ))}
          </div>
        </section>
      )}

      {detailsRest.length > 0 && <DetailGrid details={detailsRest} />}

      <div className="border-t">
        <CTASection
          title="Ready to build?"
          description="Create your first block in under a minute."
          cta={{ label: "Get Started", href: data.docsHref }}
        />
      </div>

      <footer className="border-t px-6 py-4 flex items-center justify-between gap-4">
        <p className="text-sm text-fd-muted-foreground">
          &copy; {new Date().getFullYear()} {config.title}
        </p>
      </footer>
    </div>
  );
}
