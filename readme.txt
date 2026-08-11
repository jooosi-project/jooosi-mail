=== Jooosi Mail ===
Contributors: suabahasa
Donate link: https://ko-fi.com/Q5Q75XSF7
Tags: email, smtp, mailer, transactional email, logs
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 1.0.8
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A modern WordPress mail delivery plugin routing wp_mail() through Symfony Mailer, with queues, failover, webhooks, and observability.

== Description ==

Jooosi Mail is a modern email sending solution for WordPress sites. It routes WordPress email through configurable providers, records delivery activity, supports queue-based processing, and provides operational visibility for sent, queued, and failed messages.

### Features

* **Multiple provider support**: Use SMTP or popular email services from one plugin.
* **Queue-based delivery**: Queue busy sending periods and process messages with Action Scheduler.
* **Provider failover**: Fall back to another connection when a provider is unavailable.
* **Email logs**: Review sent, queued, failed, and webhook-related activity.
* **Webhook feedback**: Supported providers can report deliveries, bounces, complaints, opens, and clicks back to WordPress.
* **Admin and CLI workflows**: Manage Jooosi Mail from the WordPress dashboard or WP-CLI.

### Supported Providers

Jooosi Mail works with SMTP and many popular email providers. Available sending methods include:

* [AhaSend](https://ahasend.com) - API, SMTP. Webhooks supported.
* [Amazon SES](https://aws.amazon.com/ses/) - API, HTTPS, SMTP.
* [Azure Communication Services](https://azure.microsoft.com/en-us/products/communication-services) - API.
* [Bird](https://bird.com) - API, SMTP, SMTPS. Webhooks supported.
* [Brevo](https://www.brevo.com) - API, SMTP. Webhooks supported.
* [Cloudflare Email Service](https://developers.cloudflare.com/email-service/) - API.
* [Elastic Email](https://elasticemail.com) - API, SMTP, SMTPS.
* [Emailit](https://emailit.com) - API, SMTP, SMTPS.
* [Gmail](https://workspace.google.com/products/gmail/) - API, SMTP.
* [Infobip](https://www.infobip.com) - API, SMTP.
* [MailerSend](https://www.mailersend.com) - API, SMTP. Webhooks supported.
* [Mailgun](https://www.mailgun.com) - API, HTTPS, SMTP. Webhooks supported.
* [Mailjet](https://www.mailjet.com) - API, SMTP. Webhooks supported.
* [Mailomat](https://mailomat.swiss) - API, SMTP. Webhooks supported.
* [MailPace](https://mailpace.com) - API, SMTP.
* [Mailtrap](https://mailtrap.io) - SMTP, API, Sandbox. Webhooks supported.
* [Mandrill](https://mailchimp.com/developer/transactional/) - API, HTTPS, SMTP. Webhooks supported.
* [Microsoft Graph](https://developer.microsoft.com/en-us/graph) - API.
* [Native PHP](https://www.php.net/manual/en/function.mail.php) - Native PHP mail.
* [Null](https://symfony.com/doc/current/mailer.html#disabling-delivery) - Null transport.
* [Pepipost](https://netcorecloud.com/email/) - API, SMTP, SMTPS.
* [Postal](https://docs.postalserver.io) - API.
* [Postmark](https://postmarkapp.com) - API, SMTP. Webhooks supported.
* [Resend](https://resend.com) - API, SMTP. Webhooks supported.
* [Scaleway](https://www.scaleway.com/en/transactional-email-tem/) - API, SMTP.
* [SendGrid](https://sendgrid.com) - API, SMTP. Webhooks supported.
* [SendLayer](https://sendlayer.com) - API, SMTP, SMTPS. Webhooks supported.
* [Sendmail](https://www.proofpoint.com/us/products/email-protection/open-source-email-solution) - Sendmail.
* [SendPulse](https://sendpulse.com) - API, SMTP, SMTPS.
* [SMTP](https://en.wikipedia.org/wiki/Simple_Mail_Transfer_Protocol) - SMTP, SMTPS.
* [SMTP2GO](https://www.smtp2go.com) - API, SMTP, SMTPS. Webhooks supported.
* [SMTP.com](https://www.smtp.com) - API, SMTP, SMTPS.
* [SparkPost](https://www.sparkpost.com) - API, SMTP, SMTPS. Webhooks supported.
* [Sweego](https://www.sweego.io) - API, SMTP. Webhooks supported.
* [toSend](https://tosend.com) - API. Webhooks supported.
* [ZeptoMail](https://www.zoho.com/zeptomail/) - API, SMTP, SMTPS. Webhooks supported.
* [Zoho Mail](https://www.zoho.com/mail/) - SMTP, SMTPS.

Visit [the GitHub repository](https://github.com/jooosi-project/jooosi-mail) for documentation, development notes, and issue tracking.

### External services

Jooosi Mail routes WordPress emails through third-party email service providers. No data is sent to any external service unless the site administrator explicitly configures a connection to that provider.

When Jooosi Mail sends an email, the following data is transmitted to the configured provider:

- Email content (subject, body, headers)
- Recipient address(es)
- Sender address (From, Reply-To)
- Attachments (if any)

This data is sent only when WordPress processes queued or immediate emails (via `wp_mail()` or Action Scheduler). Jooosi Mail does not collect or transmit any data independently.

If webhooks are enabled for a supported provider, that provider may send delivery, bounce, complaint, open, and click data back to your WordPress site.

Named third-party provider terms and privacy policies are available here:

* AhaSend: [Terms](https://ahasend.com/terms), [Privacy Policy](https://ahasend.com/privacy)
* Amazon SES: [AWS Service Terms](https://aws.amazon.com/service-terms/), [AWS Privacy Notice](https://aws.amazon.com/privacy/)
* Azure Communication Services: [Microsoft Product Terms for Online Services](https://www.microsoft.com/licensing/terms/product/ForOnlineServices/all), [Microsoft Privacy Statement](https://privacy.microsoft.com/en-us/privacystatement)
* Bird: [Terms](https://bird.com/en-us/legal/terms), [Privacy Statement](https://bird.com/en-us/legal/privacy)
* Brevo: [Terms of Use](https://www.brevo.com/legal/termsofuse/), [Privacy Policy](https://www.brevo.com/legal/privacypolicy/)
* Cloudflare Email Service: [Terms](https://www.cloudflare.com/terms/), [Privacy Policy](https://www.cloudflare.com/privacypolicy/)
* Elastic Email: [Terms of Use](https://elasticemail.com/resources/usage-policies/terms-of-use), [Privacy Policy](https://elasticemail.com/resources/usage-policies/privacy-policy)
* Emailit: [Terms of Service](https://emailit.com/terms-of-service/), [Privacy Policy](https://emailit.com/privacy-policy/)
* Gmail: [Google API Terms](https://developers.google.com/terms), [Google Privacy Policy](https://policies.google.com/privacy)
* Infobip: [Service Terms and Conditions](https://www.infobip.com/policies/service-terms-conditions), [Privacy Notice](https://www.infobip.com/policies/privacy-notice)
* MailerSend: [Terms of Service](https://www.mailersend.com/legal), [Privacy Policy](https://www.mailersend.com/legal/privacy-policy)
* Mailgun: [Terms of Service](https://www.mailgun.com/legal/terms/), [Privacy Policy](https://www.mailgun.com/legal/privacy-policy/)
* Mailjet: [Terms of Service](https://www.mailjet.com/legal/terms/), [Privacy Policy](https://www.mailjet.com/legal/privacy-policy/)
* Mailomat: [Terms](https://mailomat.swiss/nutzungsbedingungen), [Privacy Policy](https://mailomat.swiss/datenschutz)
* MailPace: [Terms](https://mailpace.com/terms/), [Privacy Policy](https://mailpace.com/privacy/)
* Mailtrap: [Terms of Service](https://mailtrap.io/terms/), [Privacy Policy](https://mailtrap.io/privacy/)
* Mandrill: [Mailchimp Terms of Use](https://mailchimp.com/legal/terms/), [Mailchimp Privacy Policy](https://mailchimp.com/legal/privacy/)
* Microsoft Graph: [Microsoft APIs Terms of Use](https://learn.microsoft.com/en-us/legal/microsoft-apis/terms-of-use), [Microsoft Privacy Statement](https://privacy.microsoft.com/en-us/privacystatement)
* Pepipost: [Netcore Cloud Terms and Conditions](https://netcorecloud.com/email/email-api/terms-and-conditions/), [Netcore Cloud Privacy Policy](https://netcorecloud.com/email/email-api/privacy-policy/)
* Postmark: [Terms of Service](https://postmarkapp.com/terms-of-service), [Privacy Policy](https://www.activecampaign.com/legal/privacy-policy)
* Resend: [Terms of Service](https://resend.com/legal/terms-of-service), [Privacy Policy](https://resend.com/legal/privacy-policy)
* Scaleway: [Terms and Contracts](https://www.scaleway.com/en/contracts/), [Privacy Policy](https://www.scaleway.com/en/privacy-policy/)
* SendGrid: [Twilio Terms of Service](https://www.twilio.com/en-us/legal/tos), [Twilio Privacy Notice](https://www.twilio.com/en-us/legal/privacy)
* SendLayer: [Terms of Service](https://sendlayer.com/terms-of-service/), [Privacy Policy](https://sendlayer.com/privacy-policy/)
* SendPulse: [Terms of Use](https://sendpulse.com/legal/terms), [Privacy Policy](https://sendpulse.com/legal/pp)
* SMTP2GO: [Terms](https://www.smtp2go.com/terms), [Privacy Policy](https://www.smtp2go.com/privacy)
* SMTP.com: [Terms and Conditions](https://www.smtp.com/policies/terms-and-conditions/), [Privacy Policy](https://www.smtp.com/policies/privacy-policy/)
* SparkPost: [Bird Terms](https://bird.com/en-us/legal/terms), [Bird Privacy Statement](https://bird.com/en-us/legal/privacy)
* Sweego: [General Terms and Conditions of Sale](https://www.sweego.io/general-terms-and-conditions-of-sale), [Privacy Policy](https://www.sweego.io/privacy-policy)
* toSend: [Terms of Service](https://tosend.com/legal/terms-of-service/), [Privacy Policy](https://tosend.com/legal/privacy-policy/)
* ZeptoMail: [Zoho Terms of Service](https://www.zoho.com/terms.html), [Zoho Privacy Policy](https://www.zoho.com/privacy.html)
* Zoho Mail: [Zoho Terms of Service](https://www.zoho.com/terms.html), [Zoho Privacy Policy](https://www.zoho.com/privacy.html)

When a site administrator configures one of those transports to send through an external server, email data is sent to the server they configure and is governed by that server operator's terms and privacy policy.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/jooosi-mail`, or install the plugin through the WordPress plugins screen.
2. Activate Jooosi Mail through the Plugins screen in WordPress.
3. Open Jooosi Mail in the WordPress admin and configure a sending connection.

== Frequently Asked Questions ==

= Which email providers are supported? =

Jooosi Mail supports SMTP and many popular transactional email providers through Symfony Mailer transports.

= Does Jooosi Mail replace wp_mail()? =

Yes. Jooosi Mail intercepts WordPress `wp_mail()` calls and routes them through the configured delivery workflow.

= Does Jooosi Mail support WP-CLI? =

Yes. Jooosi Mail includes WP-CLI commands for operational tasks such as managing connections, migrations, queue processing, and diagnostics.

== Changelog ==

= 1.0.8 - 2026-08-11 =

**Changed**

* Lowered the minimum PHP version to 8.1.

= 1.0.7 - 2026-07-07 =

**Changed**

* The minimum required PHP version has been updated to 8.3.0.

= 1.0.6 - 2026-07-06 =

**Added**

* Jooosi Mail plugin is now available on the [WordPress.org plugin repository](https://wordpress.org/plugins/jooosi-mail/).
* Blueprint for WordPress.org plugin repository.

= 1.0.5 - 2026-07-03 =

**Changed**

* Added direct terms and privacy policy links for supported email providers.

= 1.0.4 - 2026-06-29 =

**Changed**

* Renamed the project from Omni Mail to Jooosi Mail.

= 1.0.3 - 2026-06-24 =

**Changed**

* Update to address the WordPress.org submission review feedback.

= 1.0.2 - 2026-06-05 =

**Fixed**

* Resolved WordPress Plugin Check reports across the codebase.

= 1.0.1 - 2026-06-05 =

**Fixed**

* Resolved WordPress Plugin Check reports across the codebase.

= 1.0.0 - 2026-06-05 =

**Added**

* 🐣 Initial release.

[See changelog for all versions.](https://github.com/jooosi-project/jooosi-mail/blob/main/CHANGELOG.md)
