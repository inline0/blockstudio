import { RootProvider } from "fumadocs-ui/provider/next";
import { FontHead } from "onedocs";
import { createMetadata } from "onedocs/metadata";
import config from "../../onedocs.config";
import "./globals.css";

export const metadata = createMetadata(config, {
  baseUrl: "https://blockstudio.dev",
});

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <FontHead />
      </head>
      <body className="antialiased" suppressHydrationWarning>
        <RootProvider>{children}</RootProvider>
      </body>
    </html>
  );
}
