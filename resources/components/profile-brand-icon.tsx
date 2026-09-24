import * as React from "react"

import ahaSendIconUrl from "@/icons/provider-icons/ahasend.svg"
import birdIconUrl from "@/icons/provider-icons/bird.svg"
import emailitIconUrl from "@/icons/provider-icons/emailit.svg"
import infobipIconUrl from "@/icons/provider-icons/infobip.svg"
import mailerSendIconUrl from "@/icons/provider-icons/mailersend.svg"
import mailomatIconUrl from "@/icons/provider-icons/mailomat.svg"
import mailPaceIconUrl from "@/icons/provider-icons/mailpace.svg"
import pepipostIconUrl from "@/icons/provider-icons/pepipost.svg"
import postalIconUrl from "@/icons/provider-icons/postal.svg"
import postmarkIconUrl from "@/icons/provider-icons/postmark.svg"
import sendLayerIconUrl from "@/icons/provider-icons/sendlayer.svg"
import sendPulseIconUrl from "@/icons/provider-icons/sendpulse.svg"
import smtp2goIconUrl from "@/icons/provider-icons/smtp2go.svg"
import smtpComIconUrl from "@/icons/provider-icons/smtpcom.svg"
import sweegoIconUrl from "@/icons/provider-icons/sweego.svg"
import toSendIconUrl from "@/icons/provider-icons/tosend.svg"
import { cn } from "@/lib/utils"
import MailjetIcon from "~icons/logos/mailjet-icon"
import MandrillIcon from "~icons/logos/mandrill"
import SendGridIcon from "~icons/logos/sendgrid-icon"
import AmazonSimpleEmailServiceIcon from "~icons/simple-icons/amazonsimpleemailservice"
import BrevoIcon from "~icons/simple-icons/brevo"
import CloudflareIcon from "~icons/simple-icons/cloudflare"
import ElasticIcon from "~icons/simple-icons/elastic"
import MailgunIcon from "~icons/simple-icons/mailgun"
import MailtrapIcon from "~icons/simple-icons/mailtrap"
import MicrosoftIcon from "~icons/simple-icons/microsoft"
import MicrosoftAzureIcon from "~icons/simple-icons/microsoftazure"
import MicrosoftOutlookIcon from "~icons/simple-icons/microsoftoutlook"
import ResendIcon from "~icons/simple-icons/resend"
import ScalewayIcon from "~icons/simple-icons/scaleway"
import SparkPostIcon from "~icons/simple-icons/sparkpost"
import ZohoIcon from "~icons/simple-icons/zoho"
import GmailIcon from "~icons/skill-icons/gmail-light"
import NativePhpIcon from "~icons/vscode-icons/file-type-php3"

type ProfileBrandPalette = {
  background: string
  foreground: string
}

type ProfileBrandIconComponent = React.ComponentType<React.SVGProps<SVGSVGElement>>

type ProfileBrandIconAsset = {
  src: string
  className?: string
  brandBackground?: boolean
}

type ProfileBrandStyle = React.CSSProperties & {
  "--profile-brand": string
  "--profile-brand-foreground": string
}

type ProfileBrandIconProps = {
  profileKey: string
  label: string
  size?: "inline" | "sm" | "default"
  className?: string
}

const PROFILE_BRAND_PALETTES: Record<string, ProfileBrandPalette> = {
  ahasend: { background: "#0077ff", foreground: "#ffffff" },
  azure: { background: "#0078d4", foreground: "#ffffff" },
  bird: { background: "#111827", foreground: "#ffffff" },
  brevo: { background: "#0b996e", foreground: "#ffffff" },
  cloudflare: { background: "#f38020", foreground: "#111827" },
  elasticemail: { background: "#005571", foreground: "#ffffff" },
  emailit: { background: "#15c182", foreground: "#052e16" },
  gmail: { background: "#ea4335", foreground: "#ffffff" },
  infobip: { background: "#fc6423", foreground: "#111827" },
  mailpace: { background: "#f38f0b", foreground: "#111827" },
  mailgun: { background: "#c21f32", foreground: "#ffffff" },
  mailersend: { background: "#4f46e5", foreground: "#ffffff" },
  mailjet: { background: "#9585f4", foreground: "#111827" },
  mailomat: { background: "#111827", foreground: "#ffffff" },
  mailtrap: { background: "#22c55e", foreground: "#052e16" },
  microsoftgraph: { background: "#2563eb", foreground: "#ffffff" },
  native: { background: "#777bb4", foreground: "#ffffff" },
  pepipost: { background: "#fc5e02", foreground: "#111827" },
  postal: { background: "#ff9900", foreground: "#111827" },
  postmark: { background: "#ffde00", foreground: "#111827" },
  resend: { background: "#111827", foreground: "#ffffff" },
  sendgrid: { background: "#1a82e2", foreground: "#ffffff" },
  sendlayer: { background: "#211fa6", foreground: "#ffffff" },
  sendpulse: { background: "#009fc1", foreground: "#ffffff" },
  ses: { background: "#ff9900", foreground: "#111827" },
  smtp: { background: "var(--primary)", foreground: "var(--primary-foreground)" },
  smtp2go: { background: "#1d4f86", foreground: "#ffffff" },
  smtpcom: { background: "#0057b8", foreground: "#ffffff" },
  sweego: { background: "#111827", foreground: "#ffffff" },
  tosend: { background: "#4d2243", foreground: "#ffffff" },
  zohomail: { background: "#d9232e", foreground: "#ffffff" },
}

const PROFILE_BRAND_ICON_ASSETS: Record<string, ProfileBrandIconAsset> = {
  ahasend: { src: ahaSendIconUrl, className: "size-10" },
  bird: { src: birdIconUrl },
  emailit: { src: emailitIconUrl, className: "size-10" },
  infobip: { src: infobipIconUrl },
  mailersend: { src: mailerSendIconUrl },
  mailomat: { src: mailomatIconUrl, brandBackground: true },
  mailpace: { src: mailPaceIconUrl, className: "size-10" },
  pepipost: { src: pepipostIconUrl, className: "size-10" },
  postal: { src: postalIconUrl, className: "size-10" },
  postmark: { src: postmarkIconUrl },
  sendlayer: { src: sendLayerIconUrl, className: "size-10" },
  sendpulse: { src: sendPulseIconUrl, className: "size-9" },
  smtp2go: { src: smtp2goIconUrl, className: "size-9", brandBackground: true },
  smtpcom: { src: smtpComIconUrl, className: "size-10" },
  sweego: { src: sweegoIconUrl, className: "size-11", brandBackground: true },
  tosend: { src: toSendIconUrl, className: "size-10" },
}

const PROFILE_BRAND_ICON_CONTAINER_CLASSES: Record<string, string> = {
  mailjet: "bg-background p-2 [&_svg]:size-10",
  native: "bg-background p-2 [&_svg]:size-10",
}

const PROFILE_BRAND_SMALL_ICON_CONTAINER_CLASSES: Record<string, string> = {
  mailjet: "bg-background p-1 [&_svg]:size-5",
  native: "bg-background p-1 [&_svg]:size-5",
}

const PROFILE_BRAND_ICONS: Record<string, ProfileBrandIconComponent> = {
  azure: MicrosoftAzureIcon,
  brevo: BrevoIcon,
  cloudflare: CloudflareIcon,
  elasticemail: ElasticIcon,
  gmail: GmailIcon,
  mailjet: MailjetIcon,
  mailgun: MailgunIcon,
  mailtrap: MailtrapIcon,
  mandrill: MandrillIcon,
  microsoftgraph: MicrosoftIcon,
  native: NativePhpIcon,
  outlook: MicrosoftOutlookIcon,
  resend: ResendIcon,
  scaleway: ScalewayIcon,
  sendgrid: SendGridIcon,
  ses: AmazonSimpleEmailServiceIcon,
  sparkpost: SparkPostIcon,
  zeptomail: ZohoIcon,
  zohomail: ZohoIcon,
}

function formatProfileMark(label: string): string {
  return label.match(/[A-Za-z0-9]/)?.[0]?.toUpperCase() ?? "?"
}

function getProfileBrandStyle(profileKey: string): ProfileBrandStyle {
  const palette = PROFILE_BRAND_PALETTES[profileKey] ?? {
    background: "var(--primary)",
    foreground: "var(--primary-foreground)",
  }

  return {
    "--profile-brand": palette.background,
    "--profile-brand-foreground": palette.foreground,
  }
}

function getAssetContainerClassName(asset: ProfileBrandIconAsset, small: boolean): string {
  return cn(
    asset.brandBackground ? "bg-[var(--profile-brand)]" : "bg-background",
    small ? "p-1" : "p-2",
  )
}

export function ProfileBrandIcon({
  profileKey,
  label,
  size = "default",
  className,
}: ProfileBrandIconProps) {
  const small = size === "sm"
  const iconAsset = PROFILE_BRAND_ICON_ASSETS[profileKey]
  const Icon = PROFILE_BRAND_ICONS[profileKey]

  if (size === "inline") {
    return (
      <span
        aria-hidden="true"
        data-icon="inline-start"
        style={getProfileBrandStyle(profileKey)}
        className={cn(
          "flex size-4 shrink-0 items-center justify-center text-xs [&_svg]:size-full",
          iconAsset?.brandBackground && "rounded-sm bg-[var(--profile-brand)]",
          className,
        )}
      >
        {iconAsset ? (
          <img src={iconAsset.src} alt="" className="size-full object-contain" />
        ) : Icon ? (
          <Icon />
        ) : (
          formatProfileMark(label)
        )}
      </span>
    )
  }

  return (
    <span
      style={getProfileBrandStyle(profileKey)}
      className={cn(
        "flex shrink-0 items-center justify-center shadow-xs",
        small ? "size-7 rounded-lg text-xs" : "size-14 rounded-2xl text-3xl ring-8 ring-background/70",
        iconAsset
          ? getAssetContainerClassName(iconAsset, small)
          : (small
            ? (PROFILE_BRAND_SMALL_ICON_CONTAINER_CLASSES[profileKey]
              ?? "bg-[var(--profile-brand)] text-[var(--profile-brand-foreground)] [&_svg]:size-5")
            : (PROFILE_BRAND_ICON_CONTAINER_CLASSES[profileKey]
              ?? "bg-[var(--profile-brand)] text-[var(--profile-brand-foreground)] [&_svg]:size-8")),
        className,
      )}
    >
      {iconAsset ? (
        <img
          src={iconAsset.src}
          alt=""
          aria-hidden="true"
          className={cn("object-contain", small ? "size-5" : "size-10", small ? undefined : iconAsset.className)}
        />
      ) : Icon ? (
        <Icon aria-hidden="true" />
      ) : (
        formatProfileMark(label)
      )}
    </span>
  )
}
