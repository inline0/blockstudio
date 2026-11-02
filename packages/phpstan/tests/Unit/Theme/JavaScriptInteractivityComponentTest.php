<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\Diagnostic;
use Blockstudio\PHPStan\Theme\JavaScriptAnalyzer;
use Blockstudio\PHPStan\Theme\ProjectScanner;
use PHPUnit\Framework\TestCase;

final class JavaScriptInteractivityComponentTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blockstudio-interactivity-component-' . uniqid();
        mkdir($this->root . '/blocks/faq', 0777, true);
        mkdir($this->root . '/blocks/tabs', 0777, true);
        mkdir($this->root . '/components', 0777, true);
        $this->root = realpath($this->root) ?: $this->root;
        $this->store('faq', 'acme/faq', <<<'JS'
              state: {
                get expanded() {
                  return getContext().open ? "true" : "false"
                },
              },
              actions: {
                toggle() {
                  getContext().open = !getContext().open
                },
              },
              callbacks: {
                init() {
                  getElement()
                },
              },
            JS);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function test_component_namespaces_and_directives_satisfy_the_store(): void
    {
        $this->store('tabs', 'acme/tabs', <<<'JS'
              actions: {
                select() {
                  getElement()
                },
              },
            JS);
        $this->write('components/faq.tsx', <<<'TSX'
            type Props = { readonly question: string; readonly id: string }

            const panel: Record<string, string> = { "data-wp-init": "callbacks.init" }

            export function Faq({ question, id }: Props) {
              return (
                <div
                  data-wp-interactive="acme/faq"
                  data-wp-context={JSON.stringify({ open: false })}
                  data-wp-class--is-open={"context.open"}
                >
                  <button
                    type="button"
                    data-wp-on--click="actions.toggle"
                    data-wp-bind--aria-expanded={`state.expanded`}
                    aria-controls={`${id}-panel`}
                  >
                    {question}
                  </button>
                  <div {...panel} id={`${id}-panel`} />
                  <nav data-wp-interactive='{"namespace":"acme/tabs"}'>
                    <button type="button" data-wp-on--click="actions.select" />
                  </nav>
                  <button type="button" data-wp-on--click="acme/tabs::actions.select" />
                </div>
              )
            }
            TSX);

        $this->assertSame([], $this->interactivityMessages());
    }

    public function test_component_directives_report_missing_paths_and_orphans(): void
    {
        $this->write('components/faq.jsx', <<<'JSX'
            export const Faq = () => (
              <div data-wp-interactive="acme/faq">
                <button
                  data-wp-on--click="actions.missing"
                  data-wp-bind--hidden="state.absent"
                  data-wp-on--keydown="state.expanded"
                />
              </div>
            )
            export const Ghost = () => <div data-wp-interactive="acme/ghost" />
            JSX);

        $this->assertSame(
            [
                'blockstudio.interactivity.handler: Interactivity directive references missing actions handler "missing". (components/faq.jsx:4)',
                'blockstudio.interactivity.binding: data-wp-bind--hidden references missing store path "state.absent". (components/faq.jsx:5)',
                'blockstudio.interactivity.handler: data-wp-on--keydown must reference actions.* or callbacks.*. (components/faq.jsx:6)',
                'blockstudio.interactivity.orphan: Template namespace "acme/ghost" has no matching store registration. (components/faq.jsx:10)',
            ],
            $this->interactivityMessages()
        );
    }

    public function test_store_mutating_a_component_binding_is_reported(): void
    {
        $this->store('faq', 'acme/faq', <<<'JS'
              actions: {
                toggle() {
                  getElement().ref.setAttribute("aria-expanded", "true")
                },
              },
            JS);
        $this->write('components/faq.tsx', <<<'TSX'
            export const Faq = () => (
              <div data-wp-interactive="acme/faq">
                <button data-wp-on--click="actions.toggle" data-wp-bind--aria-expanded="state.open" />
              </div>
            )
            TSX);

        $this->assertSame(
            [
                'blockstudio.interactivity.derivedState: Store code mutates bound attribute "aria-expanded"; expose it through derived state instead. (blocks/faq/script.js:6)',
                'blockstudio.interactivity.binding: data-wp-bind--aria-expanded references missing store path "state.open". (components/faq.tsx:3)',
            ],
            $this->interactivityMessages()
        );
    }

    public function test_bundled_ui_namespaces_are_not_orphans(): void
    {
        $this->write('components/select.tsx', <<<'TSX'
            export const Select = () => (
              <div data-wp-interactive="acme/faq" data-wp-init="callbacks.init">
                <div data-wp-interactive="bsui/select">
                  <button type="button" data-wp-on--click="actions.toggleOpen" />
                </div>
              </div>
            )
            TSX);

        $this->assertSame([], $this->interactivityMessages());
    }

    public function test_unparsable_component_falls_back_to_a_text_scan(): void
    {
        $this->write('components/faq.tsx', <<<'TSX'
            export const Faq = () => <div data-wp-interactive="acme/faq" data-wp-on--click="actions.toggle" title={ ></div>
            TSX);

        $this->assertSame([], $this->interactivityMessages());
    }

    public function test_excluded_components_do_not_reference_stores(): void
    {
        mkdir($this->root . '/fixtures', 0777, true);
        $this->write('fixtures/faq.tsx', <<<'TSX'
            export const Faq = () => <div data-wp-interactive="acme/faq" />
            TSX);

        $this->assertSame(
            ['blockstudio.interactivity.orphan: Store namespace "acme/faq" is not referenced by an analysed template. (blocks/faq/script.js:3)'],
            $this->interactivityMessages(['fixtures/**'])
        );
    }

    private function store(string $block, string $namespace, string $body): void
    {
        $this->write(
            'blocks/' . $block . '/script.js',
            "import { store, getContext, getElement } from \"@wordpress/interactivity\"\n\n"
                . 'store("' . $namespace . "\", {\n"
                . $body
                . "\n})\n"
        );
    }

    /**
     * @param list<string> $excludes
     * @return list<string>
     */
    private function interactivityMessages(array $excludes = []): array
    {
        $diagnostics = (new JavaScriptAnalyzer())->analyse(
            new ProjectScanner($this->root, [$this->root], $excludes)
        );

        return array_values(array_map(
            fn(Diagnostic $diagnostic): string => sprintf(
                '%s: %s (%s:%d)',
                $diagnostic->identifier,
                $diagnostic->message,
                substr($diagnostic->file, strlen($this->root) + 1),
                $diagnostic->line
            ),
            array_filter(
                $diagnostics,
                static fn(Diagnostic $diagnostic): bool
                    => str_starts_with($diagnostic->identifier, 'blockstudio.interactivity.')
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
