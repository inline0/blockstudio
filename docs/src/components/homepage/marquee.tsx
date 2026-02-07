"use client";

import type { ComponentPropsWithoutRef } from "react";

interface MarqueeProps extends ComponentPropsWithoutRef<"div"> {
  reverse?: boolean;
  pauseOnHover?: boolean;
  children: React.ReactNode;
  repeat?: number;
}

export function Marquee({
  className = "",
  reverse = false,
  pauseOnHover = false,
  children,
  repeat = 4,
  ...props
}: MarqueeProps) {
  return (
    <div
      {...props}
      className={`group flex flex-row [gap:var(--gap)] overflow-hidden p-2 [--duration:240s] [--gap:1rem] ${className}`}
    >
      {Array(repeat)
        .fill(0)
        .map((_, i) => (
          <div
            key={i}
            className={`flex shrink-0 flex-row justify-around [gap:var(--gap)] animate-marquee ${
              pauseOnHover ? "group-hover:[animation-play-state:paused]" : ""
            } ${reverse ? "[animation-direction:reverse]" : ""}`}
          >
            {children}
          </div>
        ))}
    </div>
  );
}
