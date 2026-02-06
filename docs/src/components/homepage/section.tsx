interface SectionProps {
  title?: string;
  description?: string;
  children: React.ReactNode;
}

export function Section({ title, description, children }: SectionProps) {
  return (
    <section>
      {title && (
        <div className="border-t px-6 pt-12 pb-4">
          <h2 className="text-2xl font-semibold text-fd-foreground sm:text-3xl">
            {title}
          </h2>
          {description && (
            <p className="mt-2 text-fd-muted-foreground max-w-2xl text-balance">
              {description}
            </p>
          )}
        </div>
      )}
      {children}
    </section>
  );
}
