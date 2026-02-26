import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import sharp from "sharp";

const root = resolve(import.meta.dirname, "..");
const logoPath = resolve(root, "public/logo-light.svg");

const width = 1200;
const height = 630;
const bg = "hsl(0, 0%, 7%)";

const logoSvg = readFileSync(logoPath, "utf-8");
const logoInner = logoSvg
  .replace(/<svg[^>]*>/, "")
  .replace("</svg>", "")
  .replace(/fill="#000"/g, 'fill="#fff"')
  .replace(/fill-rule="evenodd"/g, 'fill="#fff" fill-rule="evenodd"');

const fullLogoWidth = 680;
const fullScale = fullLogoWidth / 875;
const fullLogoHeight = 128 * fullScale;
const fullX = (width - fullLogoWidth) / 2;
const fullY = (height - fullLogoHeight) / 2;

const fullComposite = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">
  <rect width="${width}" height="${height}" fill="${bg}"/>
  <g transform="translate(${fullX}, ${fullY}) scale(${fullScale})">
    ${logoInner}
  </g>
</svg>`;

const iconSize = fullLogoHeight;
const iconScale = iconSize / 128;
const iconX = (width - iconSize) / 2;
const iconY = (height - iconSize) / 2;

const iconComposite = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">
  <rect width="${width}" height="${height}" fill="${bg}"/>
  <g transform="translate(${iconX}, ${iconY}) scale(${iconScale})">
    <path fill="#fff" fill-rule="evenodd" d="M128,0 L128,128 L0,128 L0,0 L128,0 Z M28.5691481,9.44127259 L9.44127259,99.4308519 L99.4308519,118.558727 L118.558727,28.5691481 L28.5691481,9.44127259 Z"/>
    <rect fill="#fff" width="56" height="56" x="36" y="36" transform="rotate(45 64 64)"/>
  </g>
</svg>`;

const og2LogoWidth = 480;
const og2Scale = og2LogoWidth / 875;
const og2LogoHeight = 128 * og2Scale;
const og2X = (width - og2LogoWidth) / 2;
const og2Y = (height - og2LogoHeight) / 2;

const og2Composite = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">
  <rect width="${width}" height="${height}" fill="${bg}"/>
  <g transform="translate(${og2X}, ${og2Y}) scale(${og2Scale})">
    ${logoInner}
  </g>
</svg>`;

const twitterSize = 400;
const twitterIconSize = 150;
const twitterIconScale = twitterIconSize / 128;
const twitterIconX = (twitterSize - twitterIconSize) / 2;
const twitterIconY = (twitterSize - twitterIconSize) / 2;

const twitterLogoComposite = `<svg xmlns="http://www.w3.org/2000/svg" width="${twitterSize}" height="${twitterSize}">
  <rect width="${twitterSize}" height="${twitterSize}" fill="#000"/>
  <g transform="translate(${twitterIconX}, ${twitterIconY}) scale(${twitterIconScale})">
    <path fill="#fff" fill-rule="evenodd" d="M128,0 L128,128 L0,128 L0,0 L128,0 Z M28.5691481,9.44127259 L9.44127259,99.4308519 L99.4308519,118.558727 L118.558727,28.5691481 L28.5691481,9.44127259 Z"/>
    <rect fill="#fff" width="56" height="56" x="36" y="36" transform="rotate(45 64 64)"/>
  </g>
</svg>`;

const twitterCover = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">
  <rect width="${width}" height="${height}" fill="#000"/>
</svg>`;

await Promise.all([
  sharp(Buffer.from(fullComposite)).png().toFile(resolve(root, "public/asset-og.png")),
  sharp(Buffer.from(og2Composite)).png().toFile(resolve(root, "public/asset-og-2.png")),
  sharp(Buffer.from(iconComposite)).png().toFile(resolve(root, "public/asset-og-icon.png")),
  sharp(Buffer.from(twitterLogoComposite)).png().toFile(resolve(root, "public/asset-twitter.png")),
  sharp(Buffer.from(twitterCover)).png().toFile(resolve(root, "public/asset-twitter-cover.png")),
]);

console.log("Generated asset-og.png, asset-og-2.png, asset-og-icon.png, asset-twitter.png, asset-twitter-cover.png");
