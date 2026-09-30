<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller;

use InvalidArgumentException;
use JooosiMail\Alert\AlertConfigurationService;
use JooosiMail\Alert\AlertDeliveryException;
use JooosiMail\Alert\AlertNotifier;
use JooosiMail\Alert\Channel\AlertChannelRegistry;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Configures and tests mail failure alert channels.
 *
 * @since 1.0.12
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/alerts')]
final class AlertController
{
    /**
     * @since 1.0.12
     */
    public function __construct(
        private readonly AlertConfigurationService $configurationService,
        private readonly AlertChannelRegistry $channelRegistry,
        private readonly AlertNotifier $alertNotifier,
    ) {
    }

    /**
     * @since 1.0.12
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(['channels' => $this->configurationService->publicSettings()]);
    }

    /**
     * @since 1.0.12
     */
    #[Route(path: '/(?P<channel>[a-z][a-z0-9_-]*)', methods: 'PUT', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function save(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $channel = $this->resolveChannel($request);

        if ($channel instanceof WP_Error) {
            return $channel;
        }

        $body = $request->get_json_params();

        try {
            $channels = $this->configurationService->save($channel, is_array($body) ? $body : []);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('jooosi_mail_invalid_alert_settings', $exception->getMessage(), ['status' => 400]);
        } catch (RuntimeException) {
            return new WP_Error('jooosi_mail_alert_settings_unavailable', 'Alert credentials could not be secured. Check that Sodium is available and WordPress salts are configured.', ['status' => 500]);
        }

        return new WP_REST_Response(['channels' => $channels]);
    }

    /**
     * @since 1.0.12
     */
    #[Route(path: '/(?P<channel>[a-z][a-z0-9_-]*)/test', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function sendTest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $channel = $this->resolveChannel($request);

        if ($channel instanceof WP_Error) {
            return $channel;
        }

        try {
            $this->alertNotifier->sendTest($channel);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('jooosi_mail_alert_not_configured', $exception->getMessage(), ['status' => 400]);
        } catch (AlertDeliveryException $exception) {
            return new WP_Error('jooosi_mail_alert_test_failed', $exception->getMessage(), ['status' => 502]);
        } catch (RuntimeException) {
            return new WP_Error('jooosi_mail_alert_test_failed', 'The saved alert credentials could not be read. Save the credentials again and retry.', ['status' => 500]);
        }

        return new WP_REST_Response([
            'sent' => true,
            'message' => sprintf('A test alert was sent to %s.', $this->channelRegistry->get($channel)->displayName()),
        ]);
    }

    /**
     * @since 1.0.12
     */
    #[Route(path: '/(?P<channel>[a-z][a-z0-9_-]*)/disconnect', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function disconnect(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $channel = $this->resolveChannel($request);

        if ($channel instanceof WP_Error) {
            return $channel;
        }

        return new WP_REST_Response([
            'channels' => $this->configurationService->disconnect($channel),
        ]);
    }

    /**
     * @since 1.0.12
     */
    private function resolveChannel(WP_REST_Request $request): string|WP_Error
    {
        $channel = (string) $request->get_param('channel');

        if ($this->channelRegistry->has($channel)) {
            return $channel;
        }

        return new WP_Error('jooosi_mail_unknown_alert_channel', 'The alert channel is not supported.', ['status' => 404]);
    }
}
