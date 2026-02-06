interface SectionProps {
  title?: string;
  description?: string;
  children: React.ReactNode;
  border?: boolean;
}

export function Section({
  title,
  description,
  children,
  border = true,
}: SectionProps) {
  return (
    <section className={border ? "border-t" : undefined}>
      {title && (
        <div className="px-6 pt-16 pb-6 sm:pt-20 sm:pb-8 lg:px-10">
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
