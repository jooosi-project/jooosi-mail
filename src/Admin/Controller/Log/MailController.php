<?php

declare (strict_types=1);
namespace JooosiMail\Admin\Controller\Log;

use DateTimeImmutable;
use DateTimeInterface;
use JooosiMail\Admin\Controller\AdminRouteAuthorization;
use JooosiMail\Admin\Mail\ComposedEmailSender;
use JooosiMail\Admin\Mail\TestEmailSender;
use JooosiMail\Admin\ReadModel\Log\LogQuery;
use JooosiMail\Admin\ReadModel\Log\MailLogReadModel;
use JooosiMail\Discovery\Attribute\Controller;
use JooosiMail\Discovery\Attribute\Route;
use JooosiMail\Mail\Connection\ConnectionRepository;
use JooosiMail\Mail\Resend\Exception\InvalidMailLogPayloadException;
use JooosiMail\Mail\Resend\Exception\MailLogNotFoundException;
use JooosiMail\Mail\Resend\Exception\MailResendException;
use JooosiMail\Mail\Resend\ManualMailResendService;
use JooosiMail\Mail\ValueObject\MailAddress;
use JooosiMail\Mail\ValueObject\MailRequest;
use Throwable;
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
    public function __construct(private readonly MailLogReadModel $mailLogReadModel, private readonly \JooosiMail\Admin\Controller\Log\LogQueryNormalizer $logQueryNormalizer, private readonly TestEmailSender $testEmailSender, private readonly ManualMailResendService $manualMailResendService, private readonly ComposedEmailSender $composedEmailSender, private readonly ConnectionRepository $connectionRepository)
    {
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $query = $this->logQueryNormalizer->normalize($request, ['id', 'subject', 'status', 'dateTime', 'connection'], ['statuses', 'connectionIds']);
        $page = $this->mailLogReadModel->search(LogQuery::fromArray($query));
        return new WP_REST_Response(['items' => $page->items(), 'pagination' => $page->pagination(), 'filters' => $page->filters()]);
    }
    /**
     * Returns matching WordPress users for the compose recipient autocomplete.
     *
     * @since 1.0.12
     */
    #[Route(path: '/recipients', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function recipients(WP_REST_Request $request): WP_REST_Response
    {
        $searchValue = $request->get_param('search');
        $search = is_string($searchValue) ? trim(sanitize_text_field($searchValue)) : '';
        if (strlen($search) < 2) {
            return new WP_REST_Response(['users' => []]);
        }
        $users = get_users(['search' => '*' . $search . '*', 'search_columns' => ['user_email', 'user_login', 'user_nicename', 'display_name'], 'orderby' => 'display_name', 'order' => 'ASC', 'number' => 8, 'fields' => ['ID', 'display_name', 'user_email']]);
        $suggestions = [];
        foreach ($users as $user) {
            $email = sanitize_email((string) $user->user_email);
            if ($email === '' || !is_email($email)) {
                continue;
            }
            $name = sanitize_text_field((string) $user->display_name);
            $suggestions[] = ['id' => (int) $user->ID, 'name' => $name !== '' ? $name : $email, 'email' => $email, 'avatarUrl' => esc_url_raw(get_avatar_url((int) $user->ID, ['size' => 64]))];
        }
        return new WP_REST_Response(['users' => $suggestions]);
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '/(?P<mail_log_id>\d+)', methods: 'GET', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $mailLogId = max(1, (int) $request->get_param('mail_log_id'));
        $mailLog = $this->mailLogReadModel->find($mailLogId);
        return new WP_REST_Response(['item' => $mailLog]);
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
            return new WP_Error('jooosi_mail_log_not_found', __('The original email log was not found.', 'jooosi-mail'), ['status' => 404]);
        } catch (InvalidMailLogPayloadException) {
            return new WP_Error('jooosi_mail_log_payload_unavailable', __('This email log does not contain a reusable message payload.', 'jooosi-mail'), ['status' => 422]);
        } catch (MailResendException) {
            return new WP_Error('jooosi_mail_resend_error', __('The email could not be submitted again.', 'jooosi-mail'), ['status' => 500]);
        }
        if (!$result->accepted) {
            return new WP_Error('jooosi_mail_resend_failed', __('The email could not be resent.', 'jooosi-mail'), ['status' => 500, 'mailLogId' => $result->mailLogId]);
        }
        return new WP_REST_Response(['submitted' => \true, 'originalMailLogId' => $mailLogId, 'mailLogId' => $result->mailLogId, 'message' => __('The email was submitted again successfully.', 'jooosi-mail')]);
    }
    /**
     * @since 0.1.0
     */
    #[Route(path: '/test', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function sendTest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $to = sanitize_email((string) $request->get_param('to'));
        if ($to === '' || !is_email($to)) {
            return new WP_Error('jooosi_mail_invalid_test_email', __('Enter a valid recipient email address.', 'jooosi-mail'), ['status' => 400]);
        }
        $subject = (string) $request->get_param('subject');
        $connectionId = max(0, (int) $request->get_param('connectionId'));
        $result = $this->testEmailSender->send($to, $subject, $connectionId);
        if (!$result->sent) {
            return new WP_Error('jooosi_mail_test_email_failed', $result->errorMessage ?? __('The test email failed.', 'jooosi-mail'), ['status' => 500]);
        }
        return new WP_REST_Response(['sent' => \true, 'message' => __('The test email was queued or sent successfully.', 'jooosi-mail')]);
    }
    /**
     * Submits a manually composed HTML email with a plain-text alternative.
     *
     * @since 1.0.12
     */
    #[Route(path: '/compose', methods: 'POST', permissionCallback: [AdminRouteAuthorization::class, 'authorizeAdmin'])]
    public function compose(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $to = $this->normalizeRecipients($request->get_param('to'), __('To', 'jooosi-mail'), \true);
        if ($to instanceof WP_Error) {
            return $to;
        }
        $cc = $this->normalizeRecipients($request->get_param('cc'), __('Cc', 'jooosi-mail'), \false);
        if ($cc instanceof WP_Error) {
            return $cc;
        }
        $bcc = $this->normalizeRecipients($request->get_param('bcc'), __('Bcc', 'jooosi-mail'), \false);
        if ($bcc instanceof WP_Error) {
            return $bcc;
        }
        $subject = sanitize_text_field((string) $request->get_param('subject'));
        if ($subject === '') {
            return new WP_Error('jooosi_mail_compose_subject_required', __('Enter a subject for the email.', 'jooosi-mail'), ['status' => 400]);
        }
        $htmlBody = wp_kses_post((string) $request->get_param('htmlBody'));
        $plainTextBody = preg_replace('~<br\s*/?>|</(?:p|div|h[1-6]|li|blockquote)>|</(?:ul|ol)>~i', "\n", $htmlBody) ?? $htmlBody;
        $textBody = html_entity_decode(wp_strip_all_tags($plainTextBody), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $textBody = trim(sanitize_textarea_field($textBody));
        if (trim(wp_strip_all_tags($htmlBody)) === '') {
            return new WP_Error('jooosi_mail_compose_body_required', __('Enter a message for the email.', 'jooosi-mail'), ['status' => 400]);
        }
        $replyTo = $this->normalizeRecipients($request->get_param('replyTo'), __('Reply-To', 'jooosi-mail'), \false);
        if ($replyTo instanceof WP_Error) {
            return $replyTo;
        }
        if (count($replyTo) > 1) {
            return new WP_Error('jooosi_mail_compose_invalid_reply_to', __('Enter one Reply-To email address.', 'jooosi-mail'), ['status' => 400]);
        }
        $metadata = ['jooosi_mail_default_from_applied' => \true];
        $rawConnectionId = $request->get_param('connectionId');
        if ($rawConnectionId !== null && $rawConnectionId !== '') {
            if (!is_numeric($rawConnectionId) || (int) $rawConnectionId < 1) {
                return new WP_Error('jooosi_mail_compose_invalid_connection', __('Choose an enabled connection profile.', 'jooosi-mail'), ['status' => 400]);
            }
            $connectionId = (int) $rawConnectionId;
            $connection = $this->connectionRepository->find($connectionId);
            if ($connection === null || !$connection->enabled) {
                return new WP_Error('jooosi_mail_compose_invalid_connection', __('Choose an enabled connection profile.', 'jooosi-mail'), ['status' => 400]);
            }
            $metadata['preferred_connection_id'] = $connectionId;
        }
        $headers = [];
        $sendAt = sanitize_text_field((string) $request->get_param('sendAt'));
        if ($sendAt !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/i', $sendAt) !== 1) {
                return new WP_Error('jooosi_mail_compose_invalid_send_time', __('Choose a valid future send time.', 'jooosi-mail'), ['status' => 400]);
            }
            try {
                $scheduledDate = new DateTimeImmutable($sendAt);
            } catch (Throwable) {
                return new WP_Error('jooosi_mail_compose_invalid_send_time', __('Choose a valid future send time.', 'jooosi-mail'), ['status' => 400]);
            }
            if ($scheduledDate->getTimestamp() <= time()) {
                return new WP_Error('jooosi_mail_compose_invalid_send_time', __('Choose a future send time.', 'jooosi-mail'), ['status' => 400]);
            }
            $headers['X-Schedule-Time'] = $scheduledDate->format(DateTimeInterface::ATOM);
        }
        $mailRequest = new MailRequest(from: [new MailAddress((string) get_option('admin_email'), (string) get_option('blogname'))], to: $to, cc: $cc, bcc: $bcc, replyTo: $replyTo, subject: $subject, textBody: $textBody, htmlBody: $htmlBody, attachments: [], headers: $headers, source: 'admin_compose', metadata: $metadata);
        try {
            $result = $this->composedEmailSender->send($mailRequest, get_current_user_id());
        } catch (Throwable) {
            return new WP_Error('jooosi_mail_compose_failed', __('The email could not be submitted.', 'jooosi-mail'), ['status' => 500]);
        }
        if (!$result->accepted) {
            return new WP_Error('jooosi_mail_compose_failed', $result->error ?? __('The email could not be submitted.', 'jooosi-mail'), ['status' => 500, 'mailLogId' => $result->mailLogId]);
        }
        return new WP_REST_Response(['submitted' => \true, 'mailLogId' => $result->mailLogId, 'message' => $sendAt !== '' ? __('The email was scheduled successfully.', 'jooosi-mail') : __('The email was submitted successfully.', 'jooosi-mail')]);
    }
    /**
     * @return list<MailAddress>|WP_Error
     *
     * @since 1.0.12
     */
    private function normalizeRecipients(mixed $value, string $label, bool $required): array|WP_Error
    {
        if (is_string($value)) {
            $values = [$value];
        } elseif (is_array($value)) {
            $values = $value;
        } elseif ($value === null && !$required) {
            $values = [];
        } else {
            return new WP_Error('jooosi_mail_compose_invalid_recipients', sprintf(__('Enter valid email addresses in the %s field.', 'jooosi-mail'), $label), ['status' => 400]);
        }
        $addresses = [];
        foreach ($values as $valuePart) {
            if (!is_string($valuePart)) {
                return new WP_Error('jooosi_mail_compose_invalid_recipients', sprintf(__('Enter valid email addresses in the %s field.', 'jooosi-mail'), $label), ['status' => 400]);
            }
            foreach (preg_split('/[,;\r\n]+/', $valuePart) ?: [] as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }
                $email = sanitize_email($candidate);
                if ($email === '' || !is_email($email)) {
                    return new WP_Error('jooosi_mail_compose_invalid_recipients', sprintf(__('Enter valid email addresses in the %s field.', 'jooosi-mail'), $label), ['status' => 400]);
                }
                $addresses[] = new MailAddress($email);
            }
        }
        if ($required && $addresses === []) {
            return new WP_Error('jooosi_mail_compose_recipient_required', sprintf(__('Enter at least one email address in the %s field.', 'jooosi-mail'), $label), ['status' => 400]);
        }
        return $addresses;
    }
}
