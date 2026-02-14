import { Check } from "lucide-react";

const includes = [
  "150+ site templates",
  "AI system instructions",
  "Lifetime updates",
  "Private Discord community",
];

function PlusGraphic() {
  return (
    <div className="relative flex h-full min-h-[320px] overflow-hidden rounded-2xl border border-[oklch(0.85_0.18_85/0.08)] bg-[oklch(0.85_0.18_85/0.02)]">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_70%_20%,oklch(0.85_0.18_85/0.05),transparent_60%)]" />

      <div className="relative grid w-full grid-cols-2 gap-2.5 p-5 sm:p-6">
        <div className="col-span-2 flex flex-col gap-3 rounded-xl border border-[oklch(0.85_0.18_85/0.03)] bg-[oklch(0.85_0.18_85/0.005)] p-4">
          <div className="flex items-center gap-2">
            <div className="size-2.5 rounded-full bg-[oklch(0.85_0.18_85/0.3)]" />
            <span className="font-mono text-xs text-[oklch(0.85_0.18_85)]">block.json</span>
          </div>
          <div className="flex flex-col gap-1.5 font-mono text-xs leading-relaxed">
            <span className="text-fd-muted-foreground/60">{"{"}</span>
            <span className="pl-4 text-fd-muted-foreground/60">
              &quot;name&quot;: <span className="text-[oklch(0.85_0.18_85)]">&quot;starter/hero&quot;</span>,
            </span>
            <span className="pl-4 text-fd-muted-foreground/60">
              &quot;title&quot;: <span className="text-[oklch(0.85_0.18_85)]">&quot;Hero&quot;</span>,
            </span>
            <span className="pl-4 text-fd-muted-foreground/60">&quot;blockstudio&quot;: {"{ ... }"}</span>
            <span className="text-fd-muted-foreground/60">{"}"}</span>
          </div>
        </div>

        <div className="flex flex-col gap-3 rounded-xl border border-[oklch(0.85_0.18_85/0.02)] bg-[oklch(0.85_0.18_85/0.003)] p-4">
          <div className="flex items-center gap-2">
            <div className="size-2.5 rounded-full bg-[oklch(0.85_0.18_85/0.2)]" />
            <span className="font-mono text-xs text-[oklch(0.85_0.18_85)]">index.php</span>
          </div>
          <div className="flex flex-col gap-1">
            <div className="h-2 w-3/4 rounded-full bg-[oklch(0.85_0.18_85/0.05)]" />
            <div className="h-2 w-full rounded-full bg-[oklch(0.85_0.18_85/0.04)]" />
            <div className="h-2 w-1/2 rounded-full bg-[oklch(0.85_0.18_85/0.03)]" />
          </div>
        </div>

        <div className="flex flex-col gap-3 rounded-xl border border-[oklch(0.85_0.18_85/0.02)] bg-[oklch(0.85_0.18_85/0.003)] p-4">
          <div className="flex items-center gap-2">
            <div className="size-2.5 rounded-full bg-[oklch(0.85_0.18_85/0.2)]" />
            <span className="font-mono text-xs text-[oklch(0.85_0.18_85)]">style.css</span>
          </div>
          <div className="flex flex-col gap-1">
            <div className="h-2 w-full rounded-full bg-[oklch(0.85_0.18_85/0.05)]" />
            <div className="h-2 w-2/3 rounded-full bg-[oklch(0.85_0.18_85/0.04)]" />
            <div className="h-2 w-5/6 rounded-full bg-[oklch(0.85_0.18_85/0.03)]" />
          </div>
        </div>

        <div className="col-span-2 flex items-center justify-between rounded-xl border border-[oklch(0.85_0.18_85/0.03)] bg-[oklch(0.85_0.18_85/0.005)] px-4 py-3">
          <span className="text-xs font-medium text-[oklch(0.85_0.18_85)]">150+ templates included</span>
          <span className="rounded-full bg-[oklch(0.85_0.18_85/0.08)] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-[oklch(0.85_0.18_85)]">Plus</span>
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
