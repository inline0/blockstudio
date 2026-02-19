'use client';

import { useRef, useState } from 'react';
import { Play } from 'lucide-react';

export function ClickToPlayVideo({
  src,
  poster,
}: {
  src: string;
  poster?: string;
}) {
  const [playing, setPlaying] = useState(false);
  const videoRef = useRef<HTMLVideoElement>(null);

  return (
    <div
      className="relative cursor-pointer"
      onClick={() => {
        if (!playing) {
          setPlaying(true);
          setTimeout(() => videoRef.current?.play(), 0);
        }
      }}
    >
      <video
        ref={videoRef}
        className="w-full"
        muted
        loop
        playsInline
        preload="none"
        poster={poster}
        src={src}
      />
      {!playing && (
        <div className="absolute inset-0 flex items-center justify-center bg-black/5 dark:bg-black/20 transition-opacity">
          <div className="flex items-center justify-center size-16 rounded-full bg-fd-foreground/90 text-fd-background backdrop-blur-sm">
            <Play className="size-6 ml-0.5" fill="currentColor" />
          </div>
        </div>
      )}
    </div>
  );
}
