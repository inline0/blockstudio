import {
  LayoutTemplate,
  Check,
} from "lucide-react";

const includes = [
  "150+ site templates",
  "AI system instructions",
  "Lifetime updates",
  "Private Discord community",
];

const templates = [
  { name: "Agency", pages: 12 },
  { name: "SaaS Landing", pages: 8 },
  { name: "Portfolio", pages: 6 },
  { name: "Blog", pages: 9 },
  { name: "Documentation", pages: 5 },
];

function PlusGraphic() {
  return (
    <div className="relative flex h-full min-h-[320px] items-center justify-center overflow-hidden rounded-2xl border border-[oklch(0.85_0.18_85/0.15)] bg-[oklch(0.85_0.18_85/0.03)]">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,oklch(0.85_0.18_85/0.08),transparent_70%)]" />

      <div className="relative flex w-full flex-col gap-2 p-6 sm:p-8">
        <div className="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-[oklch(0.85_0.18_85/0.6)]">
          <LayoutTemplate className="size-3.5" />
          Templates
        </div>

        {templates.map((t, i) => (
          <div
            key={t.name}
            className="flex items-center justify-between rounded-lg border px-4 py-3 transition-colors"
            style={{
              borderColor: i === 0 ? `oklch(0.85 0.18 85 / 0.3)` : `oklch(0.85 0.18 85 / 0.1)`,
              backgroundColor: i === 0 ? `oklch(0.85 0.18 85 / 0.06)` : `oklch(0.85 0.18 85 / 0.02)`,
              opacity: 1 - i * 0.12,
            }}
          >
            <span className="text-sm font-medium text-fd-foreground">{t.name}</span>
            <span className="text-xs text-fd-muted-foreground">{t.pages} pages</span>
          </div>
        ))}

        <div className="mt-1 flex items-center gap-1.5 self-end text-xs text-[oklch(0.85_0.18_85/0.5)]">
          <span>+145 more</span>
        </div>
      </div>
    </div>
  );
}

export function PlusSection() {
  return (
    <section className="px-6 py-16 sm:py-20 lg:px-16 xl:px-20">
      <div className="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-12 xl:gap-16">
        <div className="flex flex-col justify-center gap-6">
          <span className="inline-flex w-fit items-center rounded-full border border-[oklch(0.85_0.18_85/0.4)] bg-[oklch(0.85_0.18_85/0.1)] px-3 py-1 text-xs font-semibold uppercase tracking-tight text-[oklch(0.85_0.18_85)]">
            Plus
          </span>

          <h2 className="text-2xl font-semibold tracking-tight text-fd-foreground sm:text-3xl">
            The official extension kit
          </h2>

          <p className="max-w-md text-fd-muted-foreground text-pretty">
            Premium site templates, AI system instructions, and a private
            Discord community. One-time purchase, lifetime updates.
          </p>

          <ul className="flex flex-col gap-2.5">
            {includes.map((item) => (
              <li key={item} className="flex items-center gap-2.5 text-sm text-fd-muted-foreground">
                <Check className="size-3.5 shrink-0 text-[oklch(0.85_0.18_85)]" />
                {item}
              </li>
            ))}
          </ul>

          <a
            href="https://plus.blockstudio.dev"
            target="_blank"
            rel="noopener noreferrer"
            className="mt-2 inline-flex w-fit items-center rounded-lg bg-[oklch(0.85_0.18_85)] px-5 py-2.5 text-sm font-semibold text-black transition-colors hover:bg-[oklch(0.8_0.18_85)]"
          >
            Get Plus &rarr;
          </a>
        </div>

        <PlusGraphic />
      </div>
    </section>
  );
}
