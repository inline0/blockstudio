<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP;

/**
 * Feature Flags
 *
 * Port of: packages/tailwindcss/src/feature-flags.ts
 *
 * @port-deviation:env TypeScript reads from process.env for feature flags.
 * PHP uses constants since PHP's environment handling is different.
 *
 * Controls experimental/preview features.
 * In the original TypeScript, this checks environment variables.
 */
const ENABLE_CONTAINER_SIZE_UTILITY = \true;
