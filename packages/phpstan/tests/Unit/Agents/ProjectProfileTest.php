<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Agents;

use Blockstudio\PHPStan\Agents\Command;
use Blockstudio\PHPStan\Agents\Feature;
use Blockstudio\PHPStan\Agents\ProjectProfile;
use Blockstudio\PHPStan\Agents\Surface;
use PHPUnit\Framework\TestCase;

final class ProjectProfileTest extends TestCase
{
    use ProjectFixture;

    public function testThemeIsDerivedFromItsHeaderAndFiles(): void
    {
        $profile = ProjectProfile::discover($this->theme());

        self::assertSame('WordPress theme', $profile->kind());
        self::assertSame('Acme', $profile->name());
        self::assertSame('extreme-theme', $profile->preset());
        self::assertSame('blockstudio.json', $profile->configurationPath());
        self::assertSame(['.'], $profile->roots());
        self::assertSame(['fixtures/**'], $profile->excludes());
        self::assertSame(['acme'], $profile->namespaces());
        self::assertSame(['PHP', 'Twig'], $profile->templateLanguages());

        $surfaces = $this->surfaces($profile);

        self::assertSame(2, $surfaces['Blocks']->count);
        self::assertSame(['blocks'], $surfaces['Blocks']->directories);
        self::assertSame(1, $surfaces['Pages']->count);
        self::assertSame(1, $surfaces['Page collections']->count);
        self::assertSame(1, $surfaces['Patterns']->count);
        self::assertSame(1, $surfaces['Reusable fields']->count);
        self::assertSame(1, $surfaces['Database schemas']->count);
        self::assertSame(1, $surfaces['Block extensions']->count);
        self::assertArrayNotHasKey('RPC endpoints', $surfaces);
    }

    public function testPluginIsDerivedFromItsHeaderAndFiles(): void
    {
        $profile = ProjectProfile::discover($this->plugin());

        self::assertSame('WordPress plugin', $profile->kind());
        self::assertSame('Acme Blocks', $profile->name());
        self::assertSame('base', $profile->preset());

        $surfaces = $this->surfaces($profile);

        self::assertSame(1, $surfaces['Blocks']->count);
        self::assertArrayNotHasKey('Pages', $surfaces);
        self::assertSame([], $profile->features());
    }

    public function testAProjectWithoutConfigurationFallsBackToDefaults(): void
    {
        $root = $this->temporaryDirectory('bare');
        $this->json($root . '/blocks/hero/block.json', ['name' => 'acme/hero']);

        $profile = ProjectProfile::discover($root);

        self::assertSame('Blockstudio project', $profile->kind());
        self::assertNull($profile->configurationPath());
        self::assertSame('base', $profile->preset());
        self::assertSame([], $profile->features());
    }

    public function testEnabledFeaturesAreReadFromConfiguration(): void
    {
        $features = $this->features(
            ProjectProfile::discover($this->theme())
        );

        self::assertSame(
            [
                'Block tags',
                'Bundled UI',
                'Tailwind CSS',
                'Editor parity',
                'Runtime cache',
                'Performance',
                'Theme defaults',
                'Commit gate',
            ],
            array_keys($features)
        );
        self::assertStringContainsString(
            'The `acme` prefix resolves through `theme-components`, `bsui`',
            $features['Block tags']->details[1]
        );
        self::assertStringContainsString(
            'profile is `speed`',
            $features['Performance']->details[0]
        );
        self::assertStringContainsString(
            'earlyServe `true`, invalidate `graph`, ttl `3600`',
            $features['Performance']->details[1]
        );
        self::assertStringContainsString(
            '`account`',
            $features['Performance']->details[2]
        );
    }

    public function testDisabledFeaturesAreLeftOut(): void
    {
        $features = $this->features(ProjectProfile::discover($this->theme([
            'tailwind' => ['enabled' => false],
            'blockTags' => ['enabled' => false, 'prefixes' => ['acme' => ['x']]],
            'cache' => ['enabled' => true],
        ])));

        self::assertSame(['Runtime cache'], array_keys($features));
    }

    public function testCommandsFollowTheProjectRatherThanTheProduct(): void
    {
        $theme = array_map(
            static fn(Command $command): string => $command->command,
            ProjectProfile::discover($this->theme())->commands()
        );
        $plugin = array_map(
            static fn(Command $command): string => $command->command,
            ProjectProfile::discover($this->plugin())->commands()
        );

        self::assertContains('vendor/bin/blockstudio-githooks sync', $theme);
        self::assertContains('wp bs prerender status', $theme);
        self::assertContains('wp bs tailwind compile', $theme);
        self::assertContains('wp bs db schemas', $theme);
        self::assertContains('wp bs fields list', $theme);

        self::assertSame(
            [
                'vendor/bin/blockstudio-phpstan -- --no-progress',
                'vendor/bin/blockstudio-agents',
                'wp bs blocks list',
            ],
            $plugin
        );
    }

    public function testAConfigurationOutsideTheRootStaysMachineIndependent(): void
    {
        $root = $this->theme();
        $configuration = dirname($root) . '/' . basename($root) . '-analysis.json';
        $this->json($configuration, ['phpstan' => ['preset' => 'theme']]);

        $profile = ProjectProfile::discover($root, $configuration);

        self::assertSame(
            '../' . basename($configuration),
            $profile->configurationPath()
        );
        self::assertSame('theme', $profile->preset());

        @unlink($configuration);
    }

    public function testAMissingRootIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProjectProfile::discover(
            $this->temporaryDirectory('missing') . '/nowhere'
        );
    }

    /**
     * @return array<string, Surface>
     */
    private function surfaces(ProjectProfile $profile): array
    {
        $surfaces = [];
        foreach ($profile->surfaces() as $surface) {
            $surfaces[$surface->label] = $surface;
        }

        return $surfaces;
    }

    /**
     * @return array<string, Feature>
     */
    private function features(ProjectProfile $profile): array
    {
        $features = [];
        foreach ($profile->features() as $feature) {
            $features[$feature->label] = $feature;
        }

        return $features;
    }
}
