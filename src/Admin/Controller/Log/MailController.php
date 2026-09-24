<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Controller\Log;

use JooosiMail\Admin\Controller\AdminRouteAuthorization;
use JooosiMail\Admin\Mail\TestEmailSender;
use JooosiMail\Admin\ReadModel\Log\LogQuery;
use JooosiMail\Admin\ReadModel\Log\MailLogReadModel;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Mail\Resend\Exception\InvalidMailLogPayloadException;
use JooosiMail\Mail\Resend\Exception\MailLogNotFoundException;
use JooosiMail\Mail\Resend\Exception\MailResendException;
use JooosiMail\Mail\Resend\ManualMailResendService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves mail log data for the admin UI.
 *
 * @since 0.1.0
 */
#[Controller(namespace: 'jooosi-mail/v1', prefix: 'admin/logs/mail')]
final class MailController
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly MailLogReadModel $mailLogReadModel,
        private readonly LogQueryNormalizer $logQueryNormalizer,
        private readonly TestEmailSender $testEmailSender,
        private readonly ManualMailResendService $manualMailResendService,
    ) {
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $query = $this->logQueryNormalizer->normalize(
            $request,
            ['id', 'subject', 'status', 'dateTime', 'connection'],
            ['statuses', 'connectionIds'],
        );
        $page = $this->mailLogReadModel->search(LogQuery::fromArray($query));

        return new WP_REST_Response([
            'items' => $page->items(),
            'pagination' => $page->pagination(),
            'filters' => $page->filters(),
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<mail_log_id>\d+)', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $mailLogId = max(1, (int) $request->get_param('mail_log_id'));
        $mailLog = $this->mailLogReadModel->find($mailLogId);

        return new WP_REST_Response([
            'item' => $mailLog,
        ]);
    }

    /**
     * Submits a retained email as a new delivery while preserving its source log.
     *
     * @since 1.0.9
     */
    #[Route(path: '/(?P<mail_log_id>\d+)/resend', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function resend(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $mailLogId = max(1, (int) $request->get_param('mail_log_id'));

        try {
            $result = $this->manualMailResendService->resend($mailLogId, get_current_user_id());
        } catch (MailLogNotFoundException) {
            return new WP_Error(
                'jooosi_mail_log_not_found',
                __('The original email log was not found.', 'jooosi-mail'),
                ['status' => 404],
            );
        } catch (InvalidMailLogPayloadException) {
            return new WP_Error(
                'jooosi_mail_log_payload_unavailable',
                __('This email log does not contain a reusable message payload.', 'jooosi-mail'),
                ['status' => 422],
            );
        } catch (MailResendException) {
            return new WP_Error(
                'jooosi_mail_resend_error',
                __('The email could not be submitted again.', 'jooosi-mail'),
                ['status' => 500],
            );
        }

        if (! $result->accepted) {
            return new WP_Error(
                'jooosi_mail_resend_failed',
                __('The email could not be resent.', 'jooosi-mail'),
                [
                    'status' => 500,
                    'mailLogId' => $result->mailLogId,
                ],
            );
        }

        return new WP_REST_Response([
            'submitted' => true,
            'originalMailLogId' => $mailLogId,
            'mailLogId' => $result->mailLogId,
            'message' => __('The email was submitted again successfully.', 'jooosi-mail'),
        ]);
    }

    /**
     * @since 0.1.0
     */
    #[Route(path: '/test', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function sendTest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $to = sanitize_email((string) $request->get_param('to'));

        if ($to === '' || ! is_email($to)) {
            return new WP_Error(
                'jooosi_mail_invalid_test_email',
                __('Enter a valid recipient email address.', 'jooosi-mail'),
                ['status' => 400],
            );
        }

        $subject = (string) $request->get_param('subject');
        $connectionId = max(0, (int) $request->get_param('connectionId'));
        $result = $this->testEmailSender->send($to, $subject, $connectionId);

        if (! $result->sent) {
            return new WP_Error(
                'jooosi_mail_test_email_failed',
                $result->errorMessage ?? __('The test email failed.', 'jooosi-mail'),
                ['status' => 500],
            );
        }

        return new WP_REST_Response([
            'sent' => true,
            'message' => __('The test email was queued or sent successfully.', 'jooosi-mail'),
        ]);
    }
}
