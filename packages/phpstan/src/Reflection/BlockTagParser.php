<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Reflection;

/**
 * Extracts <block> and <bs:> tags from template content.
 *
 * Ported from Blockstudio\Block_Tags::parse_single_block_tag() for use
 * at PHPStan analysis time. Handles quoted attributes, self-closing tags,
 * and nested containers without regex on the tag structure itself.
 */
final class BlockTagParser
{
    /**
     * @return list<array{name: string, attributes: array<string, string>, line: int}>
     */
    public function extractTags(string $content): array
    {
        $tags = [];
        $len = strlen($content);
        $pos = 0;

        while ($pos < $len) {
            $bsPos = strpos($content, '<bs:', $pos);
            $blockPos = strpos($content, '<block ', $pos);
            if ($blockPos === false) {
                $blockPos = strpos($content, '<block/', $pos);
            }

            if ($bsPos === false && $blockPos === false) {
                break;
            }

            if ($bsPos !== false && ($blockPos === false || $bsPos < $blockPos)) {
                $tag = $this->parseBsTag($content, $bsPos, $len);
                if ($tag !== null) {
                    $tag['line'] = $this->lineAt($content, $bsPos);
                    $tags[] = $tag;
                    $pos = $bsPos + 1;
                } else {
                    $pos = $bsPos + 1;
                }
            } else {
                $tag = $this->parseBlockTag($content, (int) $blockPos, $len);
                if ($tag !== null) {
                    $tag['line'] = $this->lineAt($content, (int) $blockPos);
                    $tags[] = $tag;
                    $pos = (int) $blockPos + 1;
                } else {
                    $pos = (int) $blockPos + 1;
                }
            }
        }

        return $tags;
    }

    /**
     * @return array{name: string, attributes: array<string, string>}|null
     */
    private function parseBsTag(string $content, int $pos, int $len): ?array
    {
        $nameStart = $pos + 4;
        $nameEnd = $nameStart;

        while ($nameEnd < $len && preg_match('/[a-z0-9-]/', $content[$nameEnd])) {
            $nameEnd++;
        }

        $tagName = substr($content, $nameStart, $nameEnd - $nameStart);
        if ($tagName === '' || !str_contains($tagName, '-')) {
            return null;
        }

        $blockName = substr_replace($tagName, '/', (int) strpos($tagName, '-'), 1);
        $gt = $this->findClosingAngle($content, $nameEnd, $len);
        if ($gt === null) {
            return null;
        }

        $isSelfClosing = ($content[$gt - 1] === '/');
        $attrEnd = $isSelfClosing ? $gt - 1 : $gt;
        $attrString = trim(substr($content, $nameEnd, $attrEnd - $nameEnd));

        return [
            'name' => $blockName,
            'attributes' => $this->parseAttributes($attrString),
        ];
    }

    /**
     * @return array{name: string, attributes: array<string, string>}|null
     */
    private function parseBlockTag(string $content, int $pos, int $len): ?array
    {
        $attrStart = $pos + 6;
        $gt = $this->findClosingAngle($content, $attrStart, $len);
        if ($gt === null) {
            return null;
        }

        $isSelfClosing = ($content[$gt - 1] === '/');
        $attrEnd = $isSelfClosing ? $gt - 1 : $gt;
        $attrString = trim(substr($content, $attrStart, $attrEnd - $attrStart));
        $attrs = $this->parseAttributes($attrString);

        $blockName = $attrs['name'] ?? '';
        unset($attrs['name']);

        if ($blockName === '') {
            return null;
        }

        return [
            'name' => $blockName,
            'attributes' => $attrs,
        ];
    }

    private function findClosingAngle(string $content, int $from, int $len): ?int
    {
        $inDouble = false;
        $inSingle = false;

        for ($i = $from; $i < $len; $i++) {
            $ch = $content[$i];
            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
            } elseif ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($ch === '>' && !$inDouble && !$inSingle) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $attrString): array
    {
        if ($attrString === '') {
            return [];
        }

        $attrs = [];
        $pattern = '/([a-zA-Z_][\w-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/';

        if (preg_match_all($pattern, $attrString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1];
                if (isset($match[2]) && $match[2] !== '') {
                    $attrs[$key] = $match[2];
                } elseif (isset($match[3]) && $match[3] !== '') {
                    $attrs[$key] = $match[3];
                } elseif (isset($match[4])) {
                    $attrs[$key] = $match[4];
                } else {
                    $attrs[$key] = '';
                }
            }
        }

        return $attrs;
    }

    private function lineAt(string $content, int $pos): int
    {
        return substr_count($content, "\n", 0, $pos) + 1;
    }
}
