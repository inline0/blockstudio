<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Agents;

use Blockstudio\PHPStan\Agents\ContractDocument;
use Blockstudio\PHPStan\Agents\DocumentManager;
use Blockstudio\PHPStan\Agents\PresetContract;
use Blockstudio\PHPStan\Agents\ProjectProfile;
use PHPUnit\Framework\TestCase;

final class DocumentManagerTest extends TestCase
{
    use ProjectFixture;

    public function testWritingIsIdempotent(): void
    {
        $root = $this->theme();
        $path = $root . '/AGENTS.md';
        $manager = new DocumentManager();

        $created = $this->write($manager, $path, $root);
        $repeated = $this->write($manager, $path, $root);

        self::assertSame('created', $created->status);
        self::assertSame('unchanged', $repeated->status);
        self::assertSame($created->contents, (string) file_get_contents($path));
        self::assertStringContainsString(
            ContractDocument::MARKER,
            (string) file_get_contents($path)
        );
    }

    public function testAuthorNotesSurviveRegeneration(): void
    {
        $root = $this->theme();
        $path = $root . '/AGENTS.md';
        $manager = new DocumentManager();
        $this->write($manager, $path, $root);

        file_put_contents($path, str_replace(
            ContractDocument::NOTES_START . "\n",
            ContractDocument::NOTES_START . "\nShip on Fridays.\n",
            (string) file_get_contents($path)
        ));

        $this->json($root . '/blocks/quote/block.json', ['name' => 'acme/quote']);
        $result = $this->write($manager, $path, $root);

        self::assertSame('updated', $result->status);
        self::assertStringContainsString('Ship on Fridays.', $result->contents);
        self::assertStringContainsString(
            '- Blocks: 3 in `blocks`.',
            $result->contents
        );
    }

    public function testAUserOwnedFileIsNeverReplacedWithoutForce(): void
    {
        $root = $this->theme();
        $path = $root . '/AGENTS.md';
        $manager = new DocumentManager();
        file_put_contents($path, "# House rules\n");

        try {
            $this->write($manager, $path, $root);
            self::fail('Expected the manager to refuse a user-owned file.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString(
                'Refusing to overwrite the user-owned file',
                $exception->getMessage()
            );
        }

        self::assertSame("# House rules\n", (string) file_get_contents($path));

        $forced = $this->write($manager, $path, $root, true);

        self::assertSame('updated', $forced->status);
        self::assertStringContainsString(
            ContractDocument::MARKER,
            (string) file_get_contents($path)
        );
        self::assertStringNotContainsString('House rules', $forced->contents);
    }

    private function write(
        DocumentManager $manager,
        string $path,
        string $root,
        bool $force = false
    ): \Blockstudio\PHPStan\Agents\DocumentResult {
        $profile = ProjectProfile::discover($root);

        return $manager->write(
            $path,
            $profile,
            PresetContract::forPreset($profile->preset()),
            $force
        );
    }
}
