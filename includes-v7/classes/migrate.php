<?php

namespace Blockstudio;

/**
 * Migrate class.
 *
 * @date   30/08/2023
 * @since  5.2.0
 */
class Migrate
{
    private $currentVersion;
    private string $newVersion;
    private array $migrations;

    /**
     * Construct.
     *
     * @date   30/08/2023
     * @since  5.2.0
     */
    function __construct()
    {
        $this->currentVersion = get_option('blockstudio_version');
        $this->newVersion = BLOCKSTUDIO_VERSION;
        $this->migrations = [
            '5.2.0' => [$this, 'migrateTo520'],
        ];
    }

    /**
     * Check if migration should run.
     *
     * @date   30/08/2023
     * @since  5.2.0
     */
    public function checkAndUpdate()
    {
        if (!$this->currentVersion) {
            $this->handleFirstActivation();
        } else {
            $this->handleUpdates();
        }
    }

    /**
     * Handle the very first migration.
     *
     * @date   30/08/2023
     * @since  5.2.0
     */
    public function handleFirstActivation()
    {
        $legacyOption = get_option('blockstudio_options');

        if ($legacyOption) {
            $this->runMigrations();
        }

        add_option('blockstudio_version', $this->newVersion);
    }

    /**
     * Handle updates.
     *
     * @date   30/08/2023
     * @since  5.2.0
     */
    private function handleUpdates()
    {
        if (version_compare($this->currentVersion, $this->newVersion, '<')) {
            $this->runMigrations();
            update_option('blockstudio_version', $this->newVersion);
        }
    }

    /**
     * Run other migrations.
     *
     * @date   30/08/2023
     * @since  5.2.0
     */
    private function runMigrations()
    {
        foreach ($this->migrations as $version => $callable) {
            if (version_compare($this->currentVersion, $version, '<')) {
                call_user_func($callable);
            }
        }
    }

    /**
     * Migrate to 5.2.0.
     *
     * @date   30/08/2023
     * @since  5.2.0
     */
    private function migrateTo520()
    {
        $oldOptions = get_option('blockstudio_options');

        if ($oldOptions) {
            $newSettings = [];

            if (isset($oldOptions->formatOnSave) && $oldOptions->formatOnSave) {
                $newSettings['editor']['formatOnSave'] = true;
            }

            if (
                isset($oldOptions->processorScss) &&
                $oldOptions->processorScss
            ) {
                $newSettings['assets']['minify']['css'] = true;
                $newSettings['assets']['process']['scss'] = true;
            }

            if (
                isset($oldOptions->processorEsbuild) &&
                $oldOptions->processorEsbuild
            ) {
                $newSettings['assets']['minify']['js'] = true;
            }

            update_option('blockstudio_settings', $newSettings);
            delete_option('blockstudio_options');
        }
    }
}

register_activation_hook(BLOCKSTUDIO_DIR . '/blockstudio.php', function () {
    $migrator = new Migrate();
    $migrator->handleFirstActivation();
});

add_action('plugins_loaded', function () {
    $storedVersion = get_option('blockstudio_version');
    if (BLOCKSTUDIO_VERSION !== $storedVersion) {
        $migrator = new Migrate();
        $migrator->checkAndUpdate();
    }
});
