import config from "../../onedocs.config";

export function SiteFooter() {
  return (
    <footer className="relative mx-auto w-full max-w-(--fd-layout-width)">
      <div className="border-x border-t px-6 py-4 flex items-center justify-between gap-4">
        <p className="text-sm text-fd-muted-foreground">
          &copy; {new Date().getFullYear()} {config.title}
        </p>
      </div>
    </footer>
  );
}
