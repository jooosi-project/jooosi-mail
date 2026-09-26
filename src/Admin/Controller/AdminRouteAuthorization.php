<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Controller;

use WP_Error;
use WP_REST_Request;
/**
 * Shared capability check for admin REST routes.
 *
 * @since 0.1.0
 */
final class AdminRouteAuthorization
{
    /**
     * @since 0.1.0
     */
    public static function authorizeAdmin(WP_REST_Request $request): bool|WP_Error
    {
        if (current_user_can('manage_options')) {
            return \true;
        }
        return new WP_Error('jooosi_mail_admin_forbidden', 'You are not allowed to manage Jooosi Mail.', ['status' => 403]);
    }
}
