<p align="center">
  <img src="./jooosi-mail.svg" alt="Jooosi Mail Logo" width="100" />
</p>

<h1 align="center">Jooosi Mail</h1>

<p align="center">
  <i>A modern WordPress mail delivery plugin that routes <code>wp_mail()</code> through Symfony Mailer, durable queues, provider failover, webhooks, and operational observability.</i>
</p>

<p align="center">
  <a href="https://downloads.wordpress.org/plugin/jooosi-mail.zip">
    <picture>
      <img src="https://img.shields.io/wordpress/plugin/v/jooosi-mail.svg?logo=wordpress&label=version" alt="WordPress plugin version" />
    </picture>
  </a>
  <a href="https://wordpress.org/plugins/jooosi-mail/advanced/">
    <picture>
      <img src="https://img.shields.io/wordpress/plugin/dt/jooosi-mail.svg?logo=wordpress" alt="WordPress plugin downloads" />
    </picture>
  </a>
  <a href="https://wordpress.org/plugins/jooosi-mail/">
    <picture>
      <img src="https://img.shields.io/wordpress/plugin/installs/jooosi-mail.svg?logo=wordpress" alt="WordPress plugin active installs" />
    </picture>
  </a>
  <a href="https://wordpress.org/support/plugin/jooosi-mail/reviews/?filter=5/#new-post">
    <picture>
      <img src="https://img.shields.io/wordpress/plugin/stars/jooosi-mail.svg?logo=wordpress" alt="WordPress plugin rating" />
    </picture>
  </a>
  <br />
  <a href="https://github.com/jooosi-project/jooosi-mail/releases">
    <picture>
      <img src="https://img.shields.io/github/v/release/jooosi-project/jooosi-mail.svg?logo=github" alt="GitHub Release" />
    </picture>
  </a>
  <a href="https://github.com/jooosi-project/jooosi-mail">
    <picture>
      <img src="https://img.shields.io/github/license/jooosi-project/jooosi-mail.svg" alt="GPL-3.0-or-later License" />
    </picture>
  </a>
  <a href="https://github.com/jooosi-project/jooosi-mail/actions">
    <picture>
      <img src="https://img.shields.io/github/actions/workflow/status/jooosi-project/jooosi-mail/ci.yaml?branch=main" alt="Build Status" />
    </picture>
  </a>
  <br />
  <a aria-label="GitHub Sponsors" href="https://github.com/sponsors/suasgn">
    <picture>
      <img alt="GitHub Sponsors button" src="https://img.shields.io/github/sponsors/suasgn?logo=github" />
    </picture>
  </a>
  <a aria-label="Support me on Ko-fi" href="https://ko-fi.com/Q5Q75XSF7">
    <picture>
      <img alt="Ko-fi button" src="https://img.shields.io/badge/Buy_me_a_Coffee-ff5e5b?logo=ko-fi&label=Ko-fi" />
    </picture>
  </a>
  <a aria-label="Join our Facebook community" href="https://wind.press/go/facebook">
    <picture>
      <img alt="Facebook community button" src="https://img.shields.io/badge/Join_us-0866ff?logo=facebook&label=Community" />
    </picture>
  </a>
</p>

> [!NOTE]
>
> Jooosi Mail is an open-source WordPress plugin by [Nabasa](https://nabasa.dev). Consider sponsoring us to support continued development.

## Intro

Send WordPress email through the provider you choose, and watch it work with your existing plugins like WooCommerce, Contact Form 7, and more.

### Features

- ✅ **Easy to use**: Connect your mail provider and start sending from WordPress.
- 📬 **Multiple provider support**: Use SMTP or popular email services from one plugin.
- 🔁 **Smarter delivery**: Queue busy sending periods and fall back to another connection when needed.
- 🧪 **Test email sending**: Send a test message before relying on a new connection.
- 📊 **Email logs**: See sent, queued, and failed emails in one place.
- 🚦 **Connection health**: Keep an eye on provider availability and delivery issues.
- 📥 **Webhook feedback**: Supported providers can report deliveries, bounces, complaints, opens, and clicks back to WordPress.
- 🛠️ **Admin and CLI workflows**: Manage Jooosi Mail from the WordPress dashboard or with WP-CLI.

### Email Providers

Jooosi Mail works with SMTP and many popular email providers. The table below shows the available sending methods and whether delivery updates can be sent back to WordPress.

| Profile | Key | Transports | Webhooks |
| --- | --- | --- | --- |
| [AhaSend](https://ahasend.com) | `ahasend` | API, SMTP | ✅ |
| [Azure](https://azure.microsoft.com/en-us/products/communication-services) | `azure` | API |  |
| [Bird](https://bird.com) | `bird` | API, SMTP, SMTPS | ✅ |
| [Brevo](https://www.brevo.com) | `brevo` | API, SMTP | ✅ |
| [Cloudflare Email Service](https://developers.cloudflare.com/email-service/) | `cloudflare` | API |  |
| [Elastic Email](https://elasticemail.com) | `elasticemail` | API, SMTP, SMTPS |  |
| [Emailit](https://emailit.com) | `emailit` | API, SMTP, SMTPS |  |
| [Gmail](https://workspace.google.com/products/gmail/) | `gmail` | API, SMTP |  |
| [Infobip](https://www.infobip.com) | `infobip` | API, SMTP |  |
| [MailerSend](https://www.mailersend.com) | `mailersend` | API, SMTP | ✅ |
| [Mailgun](https://www.mailgun.com) | `mailgun` | API, HTTPS, SMTP | ✅ |
| [Mailjet](https://www.mailjet.com) | `mailjet` | API, SMTP | ✅ |
| [Mailomat](https://mailomat.swiss) | `mailomat` | API, SMTP | ✅ |
| [MailPace](https://mailpace.com) | `mailpace` | API, SMTP |  |
| [Mailtrap](https://mailtrap.io) | `mailtrap` | SMTP, API, Sandbox | ✅ |
| [Mandrill](https://mailchimp.com/developer/transactional/) | `mandrill` | API, HTTPS, SMTP | ✅ |
| [Microsoft Graph](https://developer.microsoft.com/en-us/graph) | `microsoftgraph` | API |  |
| Native PHP | `native` | Native PHP |  |
| Null | `null` | Null |  |
| [Pepipost](https://netcorecloud.com/email/) | `pepipost` | API, SMTP, SMTPS |  |
| [Postal](https://docs.postalserver.io) | `postal` | API |  |
| [Postmark](https://postmarkapp.com) | `postmark` | API, SMTP | ✅ |
| [Resend](https://resend.com) | `resend` | API, SMTP | ✅ |
| [Scaleway](https://www.scaleway.com/en/transactional-email-tem/) | `scaleway` | API, SMTP |  |
| [SendGrid](https://sendgrid.com) | `sendgrid` | API, SMTP | ✅ |
| [SendLayer](https://sendlayer.com) | `sendlayer` | API, SMTP, SMTPS | ✅ |
| Sendmail | `sendmail` | Sendmail |  |
| [SendPulse](https://sendpulse.com) | `sendpulse` | API, SMTP, SMTPS |  |
| [Amazon SES](https://aws.amazon.com/ses/) | `ses` | API, HTTPS, SMTP |  |
| SMTP | `smtp` | SMTP, SMTPS |  |
| [SMTP2GO](https://www.smtp2go.com) | `smtp2go` | API, SMTP, SMTPS | ✅ |
| [SMTP.com](https://www.smtp.com) | `smtpcom` | API, SMTP, SMTPS |  |
| [SparkPost](https://www.sparkpost.com) | `sparkpost` | API, SMTP, SMTPS | ✅ |
| [Sweego](https://www.sweego.io) | `sweego` | API, SMTP | ✅ |
| [toSend](https://tosend.com) | `tosend` | API | ✅ |
| [ZeptoMail](https://www.zoho.com/zeptomail/) | `zeptomail` | API, SMTP, SMTPS | ✅ |
| [Zoho Mail](https://www.zoho.com/mail/) | `zohomail` | SMTP, SMTPS |  |

## Development

Want to contribute or customize the plugin? Check out the following documentation for detailed information about:

- [`documentation/README.md`](documentation/README.md) - documentation hub
- [`documentation/OPERATIONS.md`](documentation/OPERATIONS.md) - setup, admin and CLI workflows, queue, routing, webhooks, and observability
- [`documentation/ARCHITECTURE.md`](documentation/ARCHITECTURE.md) - system design and runtime flows
- [`documentation/DEVELOPMENT.md`](documentation/DEVELOPMENT.md) - local development, testing, and extension points
- [`ROADMAP.md`](ROADMAP.md) - planned work
- [`CHANGELOG.md`](CHANGELOG.md) - notable changes

## Sponsors


If you like this project, please consider supporting us by becoming a sponsor. Your sponsorship helps us maintain and improve **all our open-source WordPress plugins**, not just Jooosi Mail.

### Sponsorship Benefits

As a sponsor, you'll receive benefits across our entire plugin ecosystem:

- 📝 **Your logo and link featured** in the README of **all our current and future open-source plugins**
- ⭐ **Recognition** in the admin area sponsor section across **all our plugins**
- 💼 **Direct exposure** to thousands of WordPress developers and designers using our plugin ecosystem
- 🌟 **Unified sponsor listing** - one sponsorship covers your presence in our entire plugin family

**Supporting one plugin means supporting all our open-source efforts!**

### Become a Sponsor

- [GitHub Sponsors](https://github.com/sponsors/suasgn)
- [Ko-fi](https://ko-fi.com/Q5Q75XSF7)

Thank you to our amazing sponsors who support all our plugin development! 🥰🫰🫶

<!-- Sponsor logos will be displayed here -->

<p align="center">
  <a href="https://jooo.si" title="Jooosi - Open-source tools and products for WordPress"><kbd><img src="./resources/icons/jooosi.svg" width="80" height="80" alt="Jooosi" /></kbd></a>
  <a href="https://livecanvas.com" title="LiveCanvas - The Professional Page Builder for WordPress"><kbd><img src="https://livecanvas.com/wp-content/uploads/2022/06/favicon_big.png" width="80" height="80" alt="LiveCanvas" /></kbd></a>
</p>

<!-- --- -->

<!-- *Interested in sponsoring? Contact us to discuss custom sponsorship packages tailored to your needs.* -->

## Credits

- Built with [Symfony 6.4 Mailer](https://symfony.com/doc/6.4/mailer.html)
- Queueing by [Symfony 6.4 Messenger](https://symfony.com/doc/6.4/messenger.html)
- WordPress queue wakeups by [Action Scheduler](https://actionscheduler.org/)
- Database access by [Doctrine DBAL](https://www.doctrine-project.org/projects/dbal.html)

## Support

For issues, questions, or feature requests, please open an issue on GitHub.
