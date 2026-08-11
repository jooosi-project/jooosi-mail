<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Menu;

use JooosiMail\Bootstrap\Paths;
use JooosiMail\Discovery\Attribute\Hook;
use JooosiMail\Discovery\Attribute\Service;

use function Nabasa\VitePlus\assets;

/**
 * Registers the Jooosi Mail admin menu and admin app shell.
 *
 * @since 0.1.0
 */
#[Service]
final class AdminMenu
{
    /**
     * @var string
     */
    private const PAGE_SLUG = 'jooosi-mail';

    /**
     * @var string
     */
    private const ASSET_HANDLE = 'jooosi-mail-admin';

    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly Paths $paths,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Hook(name: 'admin_menu', kind: 'action', acceptedArgs: 0)]
    public function register(): void
    {
        add_menu_page(
            __('Jooosi Mail', 'jooosi-mail'),
            __('Jooosi Mail', 'jooosi-mail'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            'data:image/svg+xml;base64,' . base64_encode(file_get_contents($this->paths->rootDir . '/jooosi-mail.svg')),
            100,
        );
    }

    /**
     * @since 0.1.0
     */
    #[Hook(name: 'admin_enqueue_scripts', kind: 'action', acceptedArgs: 1)]
    public function enqueue(string $hookSuffix): void
    {
        if ('toplevel_page_' . self::PAGE_SLUG !== $hookSuffix) {
            return;
        }

        assets( $this->paths->rootDir . '/assets/dist' )
            ->enqueue(
                'resources/App.tsx',
                [
                    'handle' => self::ASSET_HANDLE,
                    'in_footer' => true,
                ],
            );

        wp_add_inline_script(
            self::ASSET_HANDLE,
            'window.jooosiMailAdmin = ' . wp_json_encode([
                'apiRoot' => trailingslashit((string) rest_url('jooosi-mail/v1/admin')),
                'nonce' => wp_create_nonce('wp_rest'),
                'pluginVersion' => JOOOSI_MAIL_VERSION,
            ]) . ';',
            'before',
        );
    }

    /**
     * @since 0.1.0
     */
    public function render(): void
    {
        echo '<div id="jooosi-mail-app"></div>';
    }
}
