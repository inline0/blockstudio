<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Brace expansion utility.
 *
 * Port of: packages/tailwindcss/src/utils/brace-expansion.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 */
const NUMERICAL_RANGE_PATTERN = '/^(-?\d+)\.\.(-?\d+)(?:\.\.(-?\d+))?$/';
/**
 * Expand a brace pattern into an array of strings.
 *
 * @param string $pattern
 * @return string[]
 * @throws \Exception
 */
function expand(string $pattern): array
{
    $index = strpos($pattern, '{');
    if ($index === \false) {
        return [$pattern];
    }
    $result = [];
    $pre = substr($pattern, 0, $index);
    $rest = substr($pattern, $index);
    // Find the matching closing brace
    $depth = 0;
    $endIndex = -1;
    $len = strlen($rest);
    for ($i = 0; $i < $len; $i++) {
        $char = $rest[$i];
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                $endIndex = $i;
                break;
            }
        }
    }
    if ($endIndex === -1) {
        throw new \Exception("The pattern `{$pattern}` is not balanced.");
    }
    $inside = substr($rest, 1, $endIndex - 1);
    $post = substr($rest, $endIndex + 1);
    if (isSequence($inside)) {
        $parts = expandSequence($inside);
    } else {
        $parts = segment($inside, ',');
    }
    $parts = array_merge(...array_map(fn($part) => expand($part), $parts));
    $expandedTail = expand($post);
    foreach ($expandedTail as $tail) {
        foreach ($parts as $part) {
            $result[] = $pre . $part . $tail;
        }
    }
    return $result;
}
/**
 * @param string $str
 * @return bool
 */
function isSequence(string $str): bool
{
    return (bool) preg_match(NUMERICAL_RANGE_PATTERN, $str);
}
/**
 * Expands a sequence string like "01..20" (optionally with a step).
 *
 * @param string $seq
 * @return string[]
 * @throws \Exception
 */
function expandSequence(string $seq): array
{
    if (!preg_match(NUMERICAL_RANGE_PATTERN, $seq, $seqMatch)) {
        return [$seq];
    }
    $start = $seqMatch[1];
    $end = $seqMatch[2];
    $stepStr = $seqMatch[3] ?? null;
    $step = $stepStr !== null ? (int) $stepStr : null;
    $result = [];
    if (preg_match('/^-?\d+$/', $start) && preg_match('/^-?\d+$/', $end)) {
        $startNum = (int) $start;
        $endNum = (int) $end;
        if ($step === null) {
            $step = $startNum <= $endNum ? 1 : -1;
        }
        if ($step === 0) {
            throw new \Exception('Step cannot be zero in sequence expansion.');
        }
        $increasing = $startNum < $endNum;
        if ($increasing && $step < 0) {
            $step = -$step;
        }
        if (!$increasing && $step > 0) {
            $step = -$step;
        }
        for ($i = $startNum; $increasing ? $i <= $endNum : $i >= $endNum; $i += $step) {
            $result[] = (string) $i;
        }
    }
    return $result;
}
