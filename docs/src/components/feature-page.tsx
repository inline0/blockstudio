import { Button, CTASection } from "onedocs";
import { Feature } from "./homepage/feature";
import { CodeCard } from "./homepage/code-card";
import { CodeTabs } from "./homepage/code-tabs";
import type { FeaturePageData } from "../data/features";
import config from "../../onedocs.config";

export async function FeaturePage({ data }: { data: FeaturePageData }) {
  return (
    <div className="w-full self-stretch">
      <section className="px-6 pt-16 pb-10 sm:pt-20 sm:pb-12 lg:px-16 xl:px-20">
        <h1 className="text-3xl font-semibold tracking-tight text-fd-foreground sm:text-4xl">
          {data.title}
        </h1>
        <p className="mt-3 max-w-2xl text-fd-muted-foreground text-balance">
          {data.description}
        </p>
      </section>

      <section className="flex flex-col gap-10 px-6 pb-16 sm:pb-20 lg:px-16 xl:px-20">
        {data.features.map((feature, i) => (
          <Feature
            key={i}
            headline={feature.headline}
            description={feature.description}
            cta={
              <Button href={feature.ctaHref} className="w-max">
                {feature.ctaLabel} &rarr;
              </Button>
            }
            demo={
              Array.isArray(feature.code) ? (
                <CodeTabs items={feature.code} />
              ) : (
                <CodeCard code={feature.code} lang={feature.lang!} />
              )
            }
          />
        ))}
      </section>

      {data.details.length > 0 && (
        <section className="border-t px-6 pt-16 pb-16 sm:pt-20 sm:pb-20 lg:px-16 xl:px-20">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
            {data.details.map((detail) => (
              <div
                key={detail.title}
                className="flex flex-col gap-2 text-sm/7"
              >
                <div className="flex items-start gap-3 text-fd-foreground">
                  <div className="flex items-center h-[1lh]">
                    <detail.icon className="h-3.5 w-3.5 text-fd-primary" />
                  </div>
                  <h3 className="font-semibold">{detail.title}</h3>
                </div>
                <p className="text-fd-muted-foreground text-pretty">
                  {detail.description}
                </p>
              </div>
            ))}
          </div>
        </section>
      )}

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
