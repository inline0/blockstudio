import type { ReactNode } from "react";

interface SectionProps {
  icon?: ReactNode;
  title?: string;
  description?: string;
  children: React.ReactNode;
  border?: boolean;
}

export function SectionIcon({ children }: { children: ReactNode }) {
  return (
    <div className="mb-4 flex size-10 items-center justify-center rounded-lg border bg-fd-secondary text-fd-primary [&_svg]:size-5">
      {children}
    </div>
  );
}

export function Section({
  icon,
  title,
  description,
  children,
  border = true,
}: SectionProps) {
  return (
    <section className={`pb-16 sm:pb-20 ${border ? "border-t" : ""}`}>
      {title && (
        <div className="px-6 pt-16 pb-6 sm:pt-20 sm:pb-8 lg:px-10">
          {icon}
          <h2 className="text-2xl font-semibold tracking-tight text-fd-foreground sm:text-3xl">
            {title}
          </h2>
          {description && (
            <p className="mt-3 text-fd-muted-foreground max-w-2xl text-pretty">
              {description}
            </p>
          )}
        </div>
      )}
      {children}
    </section>
  );
}
