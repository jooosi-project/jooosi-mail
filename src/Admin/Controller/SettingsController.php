<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Controller;

use JooosiMail\Admin\Settings\SettingsPayloadFactory;
use JooosiMail\Admin\Settings\SettingsUpdater;
use JooosiMail\Admin\Settings\SettingsValidationException;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
/**
 * Exposes plugin-wide admin settings.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/settings')]
final class SettingsController
{
    /**
     * @since 1.0.9
     */
    public function __construct(private readonly SettingsPayloadFactory $settingsPayloadFactory, private readonly SettingsUpdater $settingsUpdater)
    {
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [\JooosiMail\Admin\Controller\AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->settingsPayloadFactory->create());
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: ['PUT', 'PATCH'], permissionCallback: [\JooosiMail\Admin\Controller\AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $body = $request->get_json_params();
        $payload = is_array($body) ? $body : [];
        if (array_key_exists('settings', $payload) && !is_array($payload['settings'])) {
            return new WP_Error('jooosi_mail_invalid_settings', 'The settings field must use an object payload.', ['status' => 400]);
        }
        $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : $payload;
        if ($settings === []) {
            return new WP_Error('jooosi_mail_invalid_settings', 'A settings payload is required.', ['status' => 400]);
        }
        try {
            $this->settingsUpdater->update($settings, strtoupper($request->get_method()) === 'PATCH');
        } catch (SettingsValidationException $exception) {
            return new WP_Error($exception->errorCode, $exception->getMessage(), ['status' => 400]);
        }
        return new WP_REST_Response($this->settingsPayloadFactory->create());
    }
}
