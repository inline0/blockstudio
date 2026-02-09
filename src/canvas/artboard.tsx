interface Page {
  title: string;
  url: string;
  slug: string;
  name: string;
}

interface ArtboardProps {
  page: Page;
}

const ARTBOARD_WIDTH = 1440;
const ARTBOARD_HEIGHT = 900;

export const Artboard = ({ page }: ArtboardProps): JSX.Element => {
  const cleanUrl = page.url.replace(/[?&]blockstudio-canvas\b/, '');
  const separator = cleanUrl.includes('?') ? '&' : '?';
  const iframeUrl = `${cleanUrl}${separator}blockstudio-canvas-frame`;

  return (
    <div
      style={{
        width: ARTBOARD_WIDTH,
        height: ARTBOARD_HEIGHT,
        overflow: 'hidden',
        background: '#fff',
        boxShadow: '0 2px 8px rgba(0,0,0,0.3)',
      }}
    >
      <iframe
        src={iframeUrl}
        title={page.title}
        style={{
          width: ARTBOARD_WIDTH,
          height: ARTBOARD_HEIGHT,
          border: 'none',
          pointerEvents: 'none',
          display: 'block',
        }}
      />
    </div>
  );
};
