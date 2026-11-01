<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\Diagnostic;
use Blockstudio\PHPStan\Theme\JavaScriptAnalyzer;
use Blockstudio\PHPStan\Theme\ProjectScanner;
use PHPUnit\Framework\TestCase;

final class JavaScriptComponentContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blockstudio-component-contract-' . uniqid();
        mkdir($this->root . '/blocks/header', 0777, true);
        mkdir($this->root . '/components', 0777, true);
        $this->root = realpath($this->root) ?: $this->root;
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function test_tsx_jsx_attributes_and_attribute_objects_satisfy_selectors(): void
    {
        $this->script([
            '[data-drawer]',
            '[data-drawer-toggle="menu"]',
            '[data-theme-toggle]',
            '[data-sticky-cta]',
            '[data-legacy-island]',
        ]);
        $this->write('components/site-header.tsx', <<<'TSX'
            import type { ReactNode } from "react"

            type Props = { label: string; children?: ReactNode }

            export function SiteHeader({ label, children }: Props) {
              const open = false as boolean
              return (
                <header className="site-header" id="top">
                  <button data-drawer-toggle="menu" aria-expanded={open}>
                    {label}
                  </button>
                  <nav data-drawer hidden>{children}</nav>
                </header>
              )
            }
            TSX);
        $this->write('components/theme-toggle.jsx', <<<'JSX'
            export function ThemeToggle() {
              return <button type="button" data-theme-toggle="">Theme</button>
            }
            JSX);
        $this->write('components/sticky-cta.tsx', <<<'TSX'
            const attributes: Record<string, string> = { "data-sticky-cta": "" }

            export const StickyCta = () => <aside {...attributes}>Buy</aside>
            TSX);
        $this->write('components/legacy.tsx', <<<'TSX'
            export const Legacy = () => <div data-legacy-island={ ></div>
            TSX);

        $this->assertSame([], $this->domContractMessages());
    }

    public function test_missing_component_token_is_still_reported(): void
    {
        $this->script(['[data-drawer]', '[data-missing-token]']);
        $this->write('components/site-header.tsx', <<<'TSX'
            export const SiteHeader = () => (
              <nav data-drawer className="data-missing-token">menu</nav>
            )
            TSX);

        $this->assertSame(
            ['Selector token "[data-missing-token]" does not exist in an analysed template.'],
            $this->domContractMessages()
        );
    }

    public function test_excluded_components_do_not_satisfy_selectors(): void
    {
        $this->script(['[data-drawer]']);
        mkdir($this->root . '/fixtures', 0777, true);
        $this->write('fixtures/header.tsx', <<<'TSX'
            export const Header = () => <nav data-drawer>menu</nav>
            TSX);

        $this->assertSame(
            ['Selector token "[data-drawer]" does not exist in an analysed template.'],
            $this->domContractMessages(['fixtures/**'])
        );
    }

    /**
     * @param list<string> $selectors
     */
    private function script(array $selectors): void
    {
        $lines = array_map(
            static fn(string $selector): string => sprintf(
                "  root.querySelectorAll('%s');",
                $selector
            ),
            $selectors
        );
        $this->write(
            'blocks/header/script.js',
            "const root = document.querySelector('header');\nif (root) {\n"
                . implode("\n", $lines)
                . "\n}\n"
        );
    }

    /**
     * @param list<string> $excludes
     * @return list<string>
     */
    private function domContractMessages(array $excludes = []): array
    {
        $diagnostics = (new JavaScriptAnalyzer())->analyse(
            new ProjectScanner($this->root, [$this->root], $excludes)
        );

        return array_values(array_map(
            static fn(Diagnostic $diagnostic): string => $diagnostic->message,
            array_filter(
                $diagnostics,
                static fn(Diagnostic $diagnostic): bool
                    => $diagnostic->identifier === 'blockstudio.javascript.domContract'
            )
        ));
    }

    private function write(string $path, string $contents): void
    {
        file_put_contents($this->root . '/' . $path, $contents);
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . '/' . $item;
            if (is_dir($child)) {
                $this->remove($child);
            } else {
                unlink($child);
            }
        }

        rmdir($path);
    }
}
