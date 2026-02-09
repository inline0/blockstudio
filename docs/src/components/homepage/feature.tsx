import type { ReactNode } from 'react';

interface FeatureProps {
  headline: ReactNode;
  description: ReactNode;
  cta?: ReactNode;
  demo: ReactNode;
}

export function Feature({ headline, description, cta, demo }: FeatureProps) {
  return (
    <div className="group grid grid-flow-dense grid-cols-1 gap-2 rounded-2xl bg-fd-secondary/50 p-2 lg:grid-cols-2">
      <div className="flex flex-col justify-between gap-6 p-6 sm:p-8 lg:group-even:col-start-2">
        <div>
          <h3 className="text-xl font-medium text-fd-foreground sm:text-2xl">
            {headline}
          </h3>
          <div className="mt-3 flex flex-col gap-4 text-fd-muted-foreground text-pretty">
            {description}
          </div>
        </div>
        {cta}
      </div>
      <div className="overflow-hidden rounded-md lg:group-even:col-start-1 lg:group-even:row-start-1">
        {demo}
      </div>
    </div>
  );
}
