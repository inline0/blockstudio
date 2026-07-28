<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Agents;

use Blockstudio\PHPStan\Agents\ContractDocument;
use Blockstudio\PHPStan\Agents\PresetContract;
use Blockstudio\PHPStan\Agents\ProjectProfile;
use PHPUnit\Framework\TestCase;

final class ContractDocumentTest extends TestCase
{
    use ProjectFixture;

    public function testAThemeContractDescribesWhatTheThemeAuthors(): void
    {
        $document = $this->render($this->theme());

        self::assertStringStartsWith(ContractDocument::MARKER, $document);
        self::assertStringContainsString(
            '# Blockstudio contract for Acme',
            $document
        );
        self::assertStringContainsString('- Kind: WordPress theme `Acme`.', $document);
        self::assertStringContainsString('- Blocks: 2 in `blocks`.', $document);
        self::assertStringContainsString('- Pages: 1 in `pages`.', $document);
        self::assertStringContainsString(
            'A page is a `page.json` beside a template in `pages`.',
            $document
        );
        self::assertStringContainsString(
            'rather than the default `blockstudio` folder',
            $document
        );
        self::assertStringContainsString('`<bs:namespace-slug />`', $document);
        self::assertStringContainsString(
            '`vendor/bin/blockstudio-githooks sync`',
            $document
        );
        self::assertStringNotContainsString(
            'This is a plugin',
            $document
        );
    }

    public function testAPluginContractDescribesConsumingBlockstudio(): void
    {
        $document = $this->render($this->plugin());

        self::assertStringContainsString(
            '- Kind: WordPress plugin `Acme Blocks`.',
            $document
        );
        self::assertStringContainsString(
            'This is a plugin, so nothing is discovered from the active theme.',
            $document
        );
        self::assertStringNotContainsString('A page is a `page.json`', $document);
        self::assertStringNotContainsString('## Enabled features', $document);
    }

    public function testTheCorrectnessSectionFollowsTheSelectedPreset(): void
    {
        $extreme = $this->render($this->theme());
        $base = $this->render($this->plugin());

        self::assertStringContainsString(
            'Analysis runs the `extreme-theme` preset.',
            $extreme
        );
        self::assertStringContainsString('The `theme` layer adds:', $extreme);
        self::assertStringContainsString(
            'The `extreme-theme` layer adds:',
            $extreme
        );
        self::assertStringContainsString('Project PHP must not shell out', $extreme);
        self::assertStringContainsString('PHPStan runs at level `max`.', $extreme);

        self::assertStringContainsString(
            'Analysis runs the `base` preset.',
            $base
        );
        self::assertStringContainsString('The `base` layer enforces:', $base);
        self::assertStringNotContainsString('layer adds:', $base);
        self::assertStringNotContainsString('must not shell out', $base);
        self::assertStringNotContainsString('PHPStan runs at level', $base);
    }

    public function testNotesArePlacedInTheAuthorOwnedRegion(): void
    {
        $document = (new ContractDocument())->render(
            ProjectProfile::discover($this->theme()),
            PresetContract::forPreset('extreme-theme'),
            'Deploy with the release script.'
        );

        self::assertStringContainsString(
            ContractDocument::NOTES_START
            . "\nDeploy with the release script.\n"
            . ContractDocument::NOTES_END,
            $document
        );
    }

    private function render(string $root): string
    {
        $profile = ProjectProfile::discover($root);

        return (new ContractDocument())->render(
            $profile,
            PresetContract::forPreset($profile->preset())
        );
    }
}
