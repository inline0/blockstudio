<?php

/**
 * Plugin class.
 *
 * @version 1.6
 * @date   17/05/2025
 */
class BlockstudioPlugin
{
    static string $prefix = '';
    static string $name = '';
    static string $store_url = '';
    static ?int $item_id = null;
    static string $file = '';

    /**
     * Init.
     *
     * @param  $prefix
     * @param  $name
     * @param  $store_url
     * @param  $item_id
     * @param  $file
     */
    public static function init($prefix, $name, $store_url, $item_id, $file)
    {
        self::$prefix = $prefix;
        self::$name = $name;
        self::$store_url = $store_url;
        self::$item_id = $item_id;
        self::$file = $file;

        $name = str_replace('_', '', $prefix);
        add_action('admin_init', [__CLASS__, 'updater'], 0);
        add_action('admin_init', [__CLASS__, 'option']);
        add_action('admin_init', [__CLASS__, 'constant']);
        add_action('plugins_loaded', function () use ($name) {
            add_action("wp_ajax_{$name}LicenseQuery", [
                __CLASS__,
                'fabrikatLicenseQuery',
            ]);
            add_action("wp_ajax_{$name}SettingsQuery", [
                __CLASS__,
                'fabrikatSettingsQuery',
            ]);
        });
    }

    /**
     * Get data.
     *
     * @return array
     */
    static function getData(): array
    {
        return [
            'name' => str_replace('_', '', self::$prefix),
            'pluginUrl' => plugins_url('/', self::$file),
            'shopUrl' => self::$store_url,
            'productId' => self::$item_id,
            'currentUrl' => home_url(),
            'licenseStatus' => self::getStatus(),
            'licenseCode' => self::getKey(),
        ];
    }

    /**
     * Get status.
     *
     * @return string
     */
    static function getStatus(): string
    {
        return trim(get_option(self::$prefix . 'license_status'));
    }

    /**
     * Get key.
     *
     * @return string
     */
    static function getKey(): string
    {
        return trim(get_option(self::$prefix . 'license_key'));
    }

    /**
     * Get key const.
     *
     * @return string
     */
    static function getKeyConst(): string
    {
        return trim(get_option(self::$prefix . 'license_key_const'));
    }

    /**
     * Plugin updater.
     */
    static function updater()
    {
        $pluginData = get_plugin_data(self::$file);
        $pluginVersion = $pluginData['Version'];

        new EDD_SL_Plugin_Updater(self::$store_url, self::$file, [
            'version' => $pluginVersion,
            'license' => self::getKey(),
            'item_id' => self::$item_id,
            'author' => 'Fabrikat',
            'url' => home_url(),
            'beta' => false,
        ]);
    }

    /**
     * License option.
     */
    static function option()
    {
        register_setting(
            self::$prefix . 'license',
            self::$prefix . 'license_key',
            [__CLASS__, 'sanitize']
        );
    }

    /**
     * Sanitize license.
     *
     * @param  $new
     *
     * @return mixed
     */
    static function sanitize($new)
    {
        $old = self::getKey();
        if ($old && $old != $new) {
            delete_option(self::$prefix . 'license_status');
        }

        return $new;
    }

    /**
     * Check license.
     *
     * @param  $license
     * @param  $action
     *
     * @return mixed
     */
    static function checkLicense($license, $action)
    {
        $api_params = [
            'edd_action' => $action,
            'license' => $license,
            'item_name' => urlencode(self::$name),
            'url' => home_url(),
        ];

        $response = wp_remote_post(self::$store_url, [
            'timeout' => 15,
            'sslverify' => false,
            'body' => $api_params,
        ]);

        $body = wp_remote_retrieve_body($response);
        $licenseData = json_decode($body);

        if (
            !is_wp_error($response) ||
            200 === wp_remote_retrieve_response_code($response)
        ) {
            if (
                $action === 'activate_license' &&
                $licenseData->license === 'valid'
            ) {
                update_option(
                    self::$prefix . 'license_status',
                    $licenseData->license
                );
                update_option(self::$prefix . 'license_key', $license);
            } elseif (
                $action === 'deactivate_license' ||
                $licenseData->license === 'invalid'
            ) {
                update_option(
                    self::$prefix . 'license_status',
                    $licenseData->license
                );
                delete_option(self::$prefix . 'license_key');
            }
        }

        return $licenseData;
    }

    /**
     * Get constant.
     */
    static function constant()
    {
        $var = str_replace('-', '_', strtoupper(self::$prefix . 'license'));
        $const = defined($var) ? constant($var) : false;

        if (
            !$const ||
            !defined($var) ||
            empty(constant($var)) ||
            !is_string(constant($var))
        ) {
            return;
        }

        if ($const !== self::getKey()) {
            if (self::getStatus() === 'valid') {
                self::checkLicense(self::getKey(), 'deactivate_license');
            }
            if (
                ($const !== self::getKeyConst() &&
                    self::getStatus() !== 'valid') ||
                ($const === self::getKeyConst() &&
                    self::getStatus() !== 'invalid')
            ) {
                update_option(self::$prefix . 'license_key_const', $const);
                self::checkLicense($const, 'activate_license');
            }
        }
    }

    /**
     * License query.
     */
    static function fabrikatLicenseQuery()
    {
        if (wp_verify_nonce($_POST['nonce'], 'ajax-nonce')) {
            $license = $_POST['license']
                ? sanitize_text_field($_POST['license'])
                : false;
            $action = $_POST['type'];

            $licenseData = self::checkLicense(
                $action === 'activate_license' ? $license : self::getKey(),
                $action
            );
            wp_send_json($licenseData);

            die();
        }
    }

    /**
     * Settings query.
     */
    static function fabrikatSettingsQuery()
    {
        if (wp_verify_nonce($_POST['nonce'], 'ajax-nonce')) {
            $settings = $_POST['settings'];

            delete_option(self::$prefix . 'settings');
            update_option(
                self::$prefix . 'settings',
                json_decode(stripslashes(html_entity_decode($settings)))
            );

            wp_send_json(['success' => true]);

            die();
        }
    }

    /**
     * Vite dev.
     */
    static function fabrikatViteDev($handle, $port)
    {
        wp_enqueue_script(
            'vite-client',
            "http://localhost:$port/@vite/client",
            [],
            null,
            true
        );
        wp_enqueue_script(
            $handle,
            "http://localhost:$port/src/main.tsx",
            [],
            null,
            true
        );
        add_filter(
            'script_loader_tag',
            function ($tag, $h, $source) use ($handle) {
                if ($h === 'vite-client' || $h === $handle) {
                    return '<script src="' .
                        $source .
                        '" type="module" ></script>';
                }

                return $tag;
            },
            10,
            3
        );
    }

    static function fabrikatViteDevReactRefresh($handle, $port)
    {
        add_action("fabrikat/$handle/vite", function () use ($port) {
            ?>
          <script type="module">
            import RefreshRuntime
              from "http://localhost:<?php echo $port; ?>/@react-refresh";

            RefreshRuntime.injectIntoGlobalHook(window);
            window.$RefreshReg$ = () => {
            };
            window.$RefreshSig$ = () => (type) => type;
            window.__vite_plugin_react_preamble_installed__ = true;
          </script>
			<?php
        });
    }
}
