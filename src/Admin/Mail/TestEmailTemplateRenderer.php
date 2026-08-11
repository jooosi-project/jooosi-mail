<?php

declare(strict_types=1);

namespace JooosiMail\Admin\Mail;

use JooosiMail\Discovery\Attribute\Service;

/**
 * Renders the generated Jooosi Mail test message in text and HTML formats.
 *
 * @since 0.1.0
 */
#[Service]
final class TestEmailTemplateRenderer
{
    /**
     * @return array{textBody: string, htmlBody: string}
     *
     * @since 0.1.0
     */
    public function render(
        string $recipientSummary,
        string $connectionLabel,
        string $connectionProfile,
        string $deliveryMode,
        string $routingStrategy,
        string $mailLogLabel,
        string $mailLogUrl,
    ): array {
        $siteName = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $siteUrl = (string) home_url('/');
        $sentAt = wp_date((string) get_option('date_format') . ' ' . (string) get_option('time_format'));
        $details = [
            ['key' => 'connection', 'label' => __('Connection used', 'jooosi-mail'), 'value' => $connectionLabel],
            ['key' => 'profile', 'label' => __('Profile', 'jooosi-mail'), 'value' => $connectionProfile],
            ['key' => 'mode', 'label' => __('Delivery mode', 'jooosi-mail'), 'value' => $deliveryMode],
            ['key' => 'strategy', 'label' => __('Routing strategy', 'jooosi-mail'), 'value' => $routingStrategy],
            ['key' => 'recipient', 'label' => __('Recipient', 'jooosi-mail'), 'value' => $recipientSummary],
            ['key' => 'mail_log', 'label' => __('Mail log', 'jooosi-mail'), 'value' => $mailLogLabel],
            ['key' => 'site', 'label' => __('Site', 'jooosi-mail'), 'value' => $siteName],
            ['key' => 'sent_at', 'label' => __('Sent at', 'jooosi-mail'), 'value' => $sentAt],
        ];

        return [
            'textBody' => $this->renderTextBody($details),
            'htmlBody' => $this->renderHtmlBody($details, $siteName, $siteUrl, $mailLogUrl),
        ];
    }

    /**
     * @param list<array{key: string, label: string, value: string}> $details
     *
     * @since 0.1.0
     */
    private function renderTextBody(array $details): string
    {
        $lines = [
            __('Jooosi Mail test email', 'jooosi-mail'),
            '',
            __('Delivery diagnostic: passed', 'jooosi-mail'),
            __('This generated message confirms that Jooosi Mail rendered both HTML and plain-text alternatives and routed the delivery through your configured connection.', 'jooosi-mail'),
            '',
            __('Delivery details', 'jooosi-mail'),
        ];

        foreach ($details as $detail) {
            $lines[] = sprintf('%s: %s', $detail['label'], $detail['value'] !== '' ? $detail['value'] : '-');
        }

        $lines[] = '';
        $lines[] = __('You can review this delivery from the Jooosi Mail Email Logs page.', 'jooosi-mail');

        return implode("\n", $lines);
    }

    /**
     * @param list<array{key: string, label: string, value: string}> $details
     *
     * @since 0.1.0
     */
    private function renderHtmlBody(array $details, string $siteName, string $siteUrl, string $mailLogUrl): string
    {
        $template = implode('', [
            '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">',
            '<title>%s</title></head>',
            '<body style="margin:0;background:#0b1020;padding:0;font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,Roboto,Arial,sans-serif;color:#1f2937;">',
            '<div style="display:none;overflow:hidden;line-height:1px;opacity:0;max-height:0;max-width:0;">%s</div>',
            '<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="width:100%%;background:#0b1020;">',
            '<tr><td style="padding:42px 16px;">',
            '<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="max-width:720px;margin:0 auto;border-collapse:separate;border-spacing:0;">',
            '<tr><td style="padding:0;">',
            '<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="overflow:hidden;border-radius:24px;background:#ffffff;box-shadow:0 24px 70px rgba(0,0,0,.35);">',
            '<tr><td style="padding:0;background:#111827;background-image:linear-gradient(135deg,#111827,#1d4ed8);">',
            '<table role="presentation" width="100%%" cellspacing="0" cellpadding="0"><tr>',
            '<td style="padding:34px 34px 30px;">',
            '<p style="margin:0 0 18px;"><span style="display:inline-block;border:1px solid rgba(255,255,255,.28);border-radius:999px;background:rgba(255,255,255,.12);padding:7px 11px;color:#dbeafe;font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;">%s</span></p>',
            '<h1 style="margin:0;color:#ffffff;font-size:34px;line-height:1.08;font-weight:800;letter-spacing:-.035em;">%s</h1>',
            '<p style="margin:16px 0 0;max-width:560px;color:#dbeafe;font-size:16px;line-height:1.65;">%s</p>',
            '</td>',
            '</tr></table>',
            '</td></tr>',
            '<tr><td style="padding:0 34px 34px;background:#ffffff;">',
            '<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="margin-top:-20px;border-collapse:separate;border-spacing:0;">',
            '<tr><td style="padding:22px;border:1px solid #dbeafe;border-radius:18px;background:#f8fbff;box-shadow:0 14px 36px rgba(37,99,235,.14);">',
            '<p style="margin:0 0 8px;color:#2563eb;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">%s</p>',
            '<p style="margin:0;color:#0f172a;font-size:22px;line-height:1.3;font-weight:750;">%s</p>',
            '<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="margin-top:18px;"><tr>',
            '<td style="padding:0 8px 0 0;">%s</td>',
            '<td style="padding:0 8px;">%s</td>',
            '<td style="padding:0 0 0 8px;">%s</td>',
            '</tr></table>',
            '</td></tr></table>',
            '<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="margin-top:24px;">',
            '<tr><td style="padding:0 0 12px;color:#0f172a;font-size:16px;font-weight:750;">%s</td></tr>',
            '<tr><td style="border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;background:#ffffff;">%s</td></tr>',
            '</table>',
            '<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="margin-top:28px;border-radius:18px;background:#0f172a;">',
            '<tr><td style="padding:24px;text-align:center;">',
            '<p style="margin:0 0 18px;color:#cbd5e1;font-size:14px;line-height:1.6;">%s</p>',
            '<a href="%s" style="display:inline-block;border-radius:999px;background:#3b82f6;padding:12px 18px;color:#ffffff;font-size:14px;font-weight:750;text-decoration:none;">%s</a>',
            '</td></tr></table>',
            '</td></tr>',
            '</table>',
            '<p style="margin:18px 0 0;text-align:center;color:#94a3b8;font-size:12px;line-height:1.5;">%s</p>',
            '</td></tr></table>',
            '</td></tr></table></body></html>',
        ]);

        return sprintf(
            $template,
            esc_html__('Jooosi Mail test email', 'jooosi-mail'),
            esc_html__('Delivery diagnostic passed.', 'jooosi-mail'),
            esc_html__('Delivery diagnostic', 'jooosi-mail'),
            esc_html__('Test email delivered', 'jooosi-mail'),
            esc_html__('Jooosi Mail rendered both HTML and plain-text alternatives, selected a delivery route, and recorded the diagnostic details below.', 'jooosi-mail'),
            esc_html__('Connection used', 'jooosi-mail'),
            esc_html($this->getDetailValue($details, 'connection')),
            $this->renderHtmlPill(__('Profile', 'jooosi-mail'), $this->getDetailValue($details, 'profile')),
            $this->renderHtmlPill(__('Mode', 'jooosi-mail'), $this->getDetailValue($details, 'mode')),
            $this->renderHtmlPill(__('Strategy', 'jooosi-mail'), $this->getDetailValue($details, 'strategy')),
            esc_html__('Diagnostic details', 'jooosi-mail'),
            $this->renderHtmlDetails($details),
            esc_html__('Open the mail log to inspect the stored HTML, text alternative, delivery attempt, and transport response.', 'jooosi-mail'),
            esc_url($mailLogUrl !== '' ? $mailLogUrl : $siteUrl),
            esc_html__('View email log', 'jooosi-mail'),
            esc_html__('Sent by Jooosi Mail for WordPress.', 'jooosi-mail'),
        );
    }

    /**
     * @param list<array{key: string, label: string, value: string}> $details
     *
     * @since 0.1.0
     */
    private function renderHtmlDetails(array $details): string
    {
        $rows = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">';

        foreach ($details as $index => $detail) {
            $background = $index % 2 === 0 ? '#ffffff' : '#f8fafc';
            $rows .= sprintf(
                '<tr><td style="width:38%%;background:%s;border-top:%s;padding:15px 16px;color:#64748b;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;vertical-align:top;">%s</td><td style="background:%s;border-top:%s;padding:15px 16px;color:#0f172a;font-size:15px;line-height:1.45;font-weight:600;word-break:break-word;vertical-align:top;">%s</td></tr>',
                $background,
                $index === 0 ? '0' : '1px solid #e2e8f0',
                esc_html($detail['label']),
                $background,
                $index === 0 ? '0' : '1px solid #e2e8f0',
                esc_html($detail['value'] !== '' ? $detail['value'] : '-'),
            );
        }

        return $rows . '</table>';
    }

    /**
     * @param list<array{key: string, label: string, value: string}> $details
     *
     * @since 0.1.0
     */
    private function getDetailValue(array $details, string $key): string
    {
        foreach ($details as $detail) {
            if ($detail['key'] === $key) {
                return $detail['value'];
            }
        }

        return '-';
    }

    /**
     * @since 0.1.0
     */
    private function renderHtmlPill(string $label, string $value): string
    {
        return sprintf(
            '<div style="border:1px solid #dbeafe;border-radius:12px;background:#ffffff;padding:12px 13px;"><div style="color:#64748b;font-size:11px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;">%s</div><div style="margin-top:5px;color:#0f172a;font-size:14px;font-weight:750;line-height:1.35;word-break:break-word;">%s</div></div>',
            esc_html($label),
            esc_html($value !== '' ? $value : '-'),
        );
    }
}
