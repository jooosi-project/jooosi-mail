import * as React from "react";

import { buildAdminHashHref, parseAdminHashLocation } from "@/admin/routes";
import { ConnectionsOverviewTable } from "@/components/connections-overview-table";
import { Badge } from "@/components/reui/badge";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/reui/frame-card";
import {
  Field,
  FieldContent,
  FieldDescription,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { useAdminQuery } from "@/hooks/use-admin-query";
import ahaSendIconUrl from "@/icons/provider-icons/ahasend.svg";
import birdIconUrl from "@/icons/provider-icons/bird.svg";
import emailitIconUrl from "@/icons/provider-icons/emailit.svg";
import infobipIconUrl from "@/icons/provider-icons/infobip.svg";
import mailerSendIconUrl from "@/icons/provider-icons/mailersend.svg";
import mailomatIconUrl from "@/icons/provider-icons/mailomat.svg";
import mailPaceIconUrl from "@/icons/provider-icons/mailpace.svg";
import pepipostIconUrl from "@/icons/provider-icons/pepipost.svg";
import postalIconUrl from "@/icons/provider-icons/postal.svg";
import postmarkIconUrl from "@/icons/provider-icons/postmark.svg";
import sendLayerIconUrl from "@/icons/provider-icons/sendlayer.svg";
import sendPulseIconUrl from "@/icons/provider-icons/sendpulse.svg";
import smtp2goIconUrl from "@/icons/provider-icons/smtp2go.svg";
import smtpComIconUrl from "@/icons/provider-icons/smtpcom.svg";
import sweegoIconUrl from "@/icons/provider-icons/sweego.svg";
import toSendIconUrl from "@/icons/provider-icons/tosend.svg";
import {
  deleteConnection,
  getConnection,
  getConnections,
  makeDefaultConnection,
  saveConnection,
  setConnectionEnabled,
  type AdminConfigurationField,
  type AdminConnectionDetail,
  type AdminConnectionProfile,
  type AdminConnectionProfileMetadata,
  type AdminConnectionSenderPolicy,
  type ConnectionSavePayload,
  type ConnectionSecretAction,
} from "@/lib/admin-api";
import { hasEnabledConnectionWebhooks, supportsConnectionWebhooks } from "@/lib/admin-connections";
import { formatAdminDateTime, formatAdminNumber, titleCase } from "@/lib/admin-format";
import { cn } from "@/lib/utils";
import Add01Icon from "~icons/hugeicons/add-01";
import ArrowRight01Icon from "~icons/hugeicons/arrow-right-01";
import BookOpen01Icon from "~icons/hugeicons/book-open-01";
import Briefcase02Icon from "~icons/hugeicons/briefcase-02";
import ClockAlertIcon from "~icons/hugeicons/clock-alert";
import Globe02Icon from "~icons/hugeicons/globe-02";
import InformationCircleIcon from "~icons/hugeicons/information-circle";
import WebhookIcon from "~icons/hugeicons/webhook";
import MailjetIcon from "~icons/logos/mailjet-icon";
import MandrillIcon from "~icons/logos/mandrill";
import SendGridIcon from "~icons/logos/sendgrid-icon";
import AmazonSimpleEmailServiceIcon from "~icons/simple-icons/amazonsimpleemailservice";
import BrevoIcon from "~icons/simple-icons/brevo";
import CloudflareIcon from "~icons/simple-icons/cloudflare";
import ElasticIcon from "~icons/simple-icons/elastic";
import MailgunIcon from "~icons/simple-icons/mailgun";
import MailtrapIcon from "~icons/simple-icons/mailtrap";
import MicrosoftIcon from "~icons/simple-icons/microsoft";
import MicrosoftAzureIcon from "~icons/simple-icons/microsoftazure";
import MicrosoftOutlookIcon from "~icons/simple-icons/microsoftoutlook";
import ResendIcon from "~icons/simple-icons/resend";
import ScalewayIcon from "~icons/simple-icons/scaleway";
import SparkPostIcon from "~icons/simple-icons/sparkpost";
import ZohoIcon from "~icons/simple-icons/zoho";
import GmailIcon from "~icons/skill-icons/gmail-light";
import NativePhpIcon from "~icons/vscode-icons/file-type-php3";

type SecretDraft = {
  action: ConnectionSecretAction;
  value: string;
};

type ConnectionDraft = {
  id: number | null;
  profile: string;
  name: string;
  dsn: string;
  enabled: boolean;
  default: boolean;
  priority: string;
  weight: string;
  webhookEnabled: boolean;
  webhookSecretAction: ConnectionSecretAction;
  webhookSecret: string;
  sender: AdminConnectionSenderPolicy;
  rateLimits: {
    minute: string;
    hour: string;
    day: string;
  };
  circuitBreaker: {
    threshold: string;
    window: string;
    cooldown: string;
  };
  configuration: Record<string, string>;
  secretConfiguration: Record<string, SecretDraft>;
};

type AdvancedSettingsOpenState = {
  sender: boolean;
  routing: boolean;
  safeguards: boolean;
  webhooks: boolean;
};

type ProfileBrandPalette = {
  background: string;
  foreground: string;
  soft: string;
};

type ProfileBrandIcon = React.ComponentType<React.SVGProps<SVGSVGElement>>;

type ProfileBrandIconAsset = {
  src: string;
  className?: string;
  containerClassName?: string;
};

type ProfileBrandStyle = React.CSSProperties & {
  "--profile-brand": string;
  "--profile-brand-foreground": string;
  "--profile-brand-soft": string;
};

const PROFILE_BRAND_PALETTES: Record<string, ProfileBrandPalette> = {
  ahasend: { background: "#0077ff", foreground: "#ffffff", soft: "rgb(0 119 255 / 16%)" },
  azure: { background: "#0078d4", foreground: "#ffffff", soft: "rgb(0 120 212 / 16%)" },
  bird: { background: "#111827", foreground: "#ffffff", soft: "rgb(17 24 39 / 16%)" },
  brevo: { background: "#0b996e", foreground: "#ffffff", soft: "rgb(11 153 110 / 16%)" },
  cloudflare: { background: "#f38020", foreground: "#111827", soft: "rgb(243 128 32 / 18%)" },
  elasticemail: { background: "#005571", foreground: "#ffffff", soft: "rgb(0 85 113 / 16%)" },
  emailit: { background: "#15c182", foreground: "#052e16", soft: "rgb(21 193 130 / 16%)" },
  gmail: { background: "#ea4335", foreground: "#ffffff", soft: "rgb(234 67 53 / 16%)" },
  infobip: { background: "#fc6423", foreground: "#111827", soft: "rgb(252 100 35 / 16%)" },
  mailpace: { background: "#f38f0b", foreground: "#111827", soft: "rgb(243 143 11 / 18%)" },
  mailgun: { background: "#c21f32", foreground: "#ffffff", soft: "rgb(194 31 50 / 16%)" },
  mailersend: { background: "#4f46e5", foreground: "#ffffff", soft: "rgb(79 70 229 / 16%)" },
  mailjet: { background: "#9585f4", foreground: "#111827", soft: "rgb(149 133 244 / 16%)" },
  mailomat: { background: "#111827", foreground: "#ffffff", soft: "rgb(17 24 39 / 16%)" },
  mailtrap: { background: "#22c55e", foreground: "#052e16", soft: "rgb(34 197 94 / 16%)" },
  microsoftgraph: { background: "#2563eb", foreground: "#ffffff", soft: "rgb(37 99 235 / 16%)" },
  native: { background: "#777bb4", foreground: "#ffffff", soft: "rgb(119 123 180 / 18%)" },
  pepipost: { background: "#fc5e02", foreground: "#111827", soft: "rgb(252 94 2 / 16%)" },
  postal: { background: "#ff9900", foreground: "#111827", soft: "rgb(255 153 0 / 18%)" },
  postmark: { background: "#ffde00", foreground: "#111827", soft: "rgb(255 222 0 / 20%)" },
  resend: { background: "#111827", foreground: "#ffffff", soft: "rgb(17 24 39 / 16%)" },
  sendgrid: { background: "#1a82e2", foreground: "#ffffff", soft: "rgb(26 130 226 / 16%)" },
  sendlayer: { background: "#211fa6", foreground: "#ffffff", soft: "rgb(33 31 166 / 16%)" },
  sendpulse: { background: "#009fc1", foreground: "#ffffff", soft: "rgb(0 159 193 / 16%)" },
  ses: { background: "#ff9900", foreground: "#111827", soft: "rgb(255 153 0 / 20%)" },
  smtp: {
    background: "var(--primary)",
    foreground: "var(--primary-foreground)",
    soft: "var(--muted)",
  },
  smtp2go: { background: "#1d4f86", foreground: "#ffffff", soft: "rgb(29 79 134 / 16%)" },
  smtpcom: { background: "#0057b8", foreground: "#ffffff", soft: "rgb(0 87 184 / 16%)" },
  sweego: { background: "#111827", foreground: "#ffffff", soft: "rgb(17 24 39 / 16%)" },
  tosend: { background: "#4d2243", foreground: "#ffffff", soft: "rgb(77 34 67 / 16%)" },
  zohomail: { background: "#d9232e", foreground: "#ffffff", soft: "rgb(217 35 46 / 16%)" },
};

const PROFILE_BRAND_ICON_ASSETS: Record<string, ProfileBrandIconAsset> = {
  ahasend: { src: ahaSendIconUrl, className: "size-10" },
  bird: { src: birdIconUrl },
  emailit: { src: emailitIconUrl, className: "size-10" },
  infobip: { src: infobipIconUrl },
  mailersend: { src: mailerSendIconUrl },
  mailomat: { src: mailomatIconUrl, containerClassName: "bg-[var(--profile-brand)] p-2" },
  mailpace: { src: mailPaceIconUrl, className: "size-10" },
  pepipost: { src: pepipostIconUrl, className: "size-10" },
  postal: { src: postalIconUrl, className: "size-10" },
  postmark: { src: postmarkIconUrl },
  sendlayer: { src: sendLayerIconUrl, className: "size-10" },
  sendpulse: { src: sendPulseIconUrl, className: "size-9" },
  smtp2go: {
    src: smtp2goIconUrl,
    className: "size-9",
    containerClassName: "bg-[var(--profile-brand)] p-2",
  },
  smtpcom: { src: smtpComIconUrl, className: "size-10" },
  sweego: {
    src: sweegoIconUrl,
    className: "size-11",
    containerClassName: "bg-[var(--profile-brand)] p-2",
  },
  tosend: { src: toSendIconUrl, className: "size-10" },
};

const PROFILE_BRAND_ICON_CONTAINER_CLASSES: Record<string, string> = {
  mailjet: "bg-background p-2 [&_svg]:size-10",
  native: "bg-background p-2 [&_svg]:size-10",
};

const PROFILE_BRAND_ICONS: Record<string, ProfileBrandIcon> = {
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
};

const CONNECTION_RETURN_PATH_MODE_OPTIONS = [
  { value: "inherit", label: "Inherit global setting" },
  { value: "provider_default", label: "Provider default" },
  { value: "match_from", label: "Match From Email" },
  { value: "custom", label: "Custom address" },
];

function createEmptySenderPolicy(): AdminConnectionSenderPolicy {
  return {
    email: "",
    name: "",
    forceEmail: false,
    forceName: false,
    returnPathMode: "inherit",
    returnPathEmail: "",
  };
}

function normalizeConditionValue(value: string | undefined): string {
  return (value ?? "").trim().toLowerCase();
}

function formatProfileMetadataLabel(label: string): string {
  return titleCase(label.replace(/([a-z0-9])([A-Z])/g, "$1 $2").replace(/[_-]+/g, " "));
}

function formatProfileMetadataValue(value: string | string[]): string {
  return Array.isArray(value) ? value.join(", ") : value;
}

function formatProfileMark(label: string): string {
  const character = label.match(/[A-Za-z0-9]/)?.[0] ?? "?";

  return character.toUpperCase();
}

function getProfileBrandStyle(profileKey: string): ProfileBrandStyle {
  const palette = PROFILE_BRAND_PALETTES[profileKey] ?? {
    background: "var(--primary)",
    foreground: "var(--primary-foreground)",
    soft: "var(--muted)",
  };

  return {
    "--profile-brand": palette.background,
    "--profile-brand-foreground": palette.foreground,
    "--profile-brand-soft": palette.soft,
  };
}

function getProfileMetadataExtraEntries(
  metadata: AdminConnectionProfileMetadata | undefined,
): Array<[string, string | string[]]> {
  return Object.entries(metadata?.extra ?? {});
}

function matchesFieldConditions(
  conditions: AdminConfigurationField["visibleWhen"] | AdminConfigurationField["requiredWhen"],
  draft: ConnectionDraft,
  fallback: boolean,
): boolean {
  if (conditions.length === 0) {
    return fallback;
  }

  return conditions.every((condition) => {
    const actualValue = normalizeConditionValue(draft.configuration[condition.field]);
    const expectedValues = condition.values.map((value) => normalizeConditionValue(value));

    if (condition.operator === "not_in") {
      return expectedValues.includes(actualValue) === false;
    }

    return expectedValues.includes(actualValue);
  });
}

function isFieldVisible(field: AdminConfigurationField, draft: ConnectionDraft): boolean {
  return matchesFieldConditions(field.visibleWhen, draft, true);
}

function isFieldRequired(field: AdminConfigurationField, draft: ConnectionDraft): boolean {
  if (field.required) {
    return true;
  }

  return matchesFieldConditions(field.requiredWhen, draft, false);
}

function hasDraftFieldValue(field: AdminConfigurationField, draft: ConnectionDraft): boolean {
  if (field.secret) {
    const secretDraft = draft.secretConfiguration[field.name];

    if (
      secretDraft?.action === "keep" ||
      (secretDraft === undefined && field.configured === true)
    ) {
      return true;
    }

    return secretDraft?.action === "replace" && secretDraft.value.trim() !== "";
  }

  return (draft.configuration[field.name] ?? "").trim() !== "";
}

function validateDraft(draft: ConnectionDraft, fields: AdminConfigurationField[]): string | null {
  if (draft.name.trim() === "") {
    return "Connection name is required.";
  }

  for (const field of fields) {
    if (isFieldRequired(field, draft) && !hasDraftFieldValue(field, draft)) {
      return `${field.label} is required.`;
    }
  }

  return null;
}

function valueToString(value: string | number | null | undefined): string {
  return value === null || value === undefined ? "" : String(value);
}

function createDraftFromProfile(
  profile: AdminConnectionProfile,
  currentDraft?: ConnectionDraft,
): ConnectionDraft {
  const configuration = Object.fromEntries(
    profile.configurationFields
      .filter((field) => !field.secret)
      .map((field) => [field.name, valueToString(field.default)]),
  );
  const secretConfiguration = Object.fromEntries(
    profile.configurationFields
      .filter((field) => field.secret)
      .map((field) => [
        field.name,
        { action: "replace" as const, value: "" } satisfies SecretDraft,
      ]),
  ) as Record<string, SecretDraft>;

  return {
    id: currentDraft?.id ?? null,
    profile: profile.key,
    name: currentDraft?.name ?? "",
    dsn: currentDraft?.dsn ?? "",
    enabled: currentDraft?.enabled ?? true,
    default: currentDraft?.default ?? false,
    priority: currentDraft?.priority ?? "10",
    weight: currentDraft?.weight ?? "1",
    webhookEnabled: profile.supportsWebhooks ? (currentDraft?.webhookEnabled ?? false) : false,
    webhookSecretAction: profile.supportsWebhooks
      ? (currentDraft?.webhookSecretAction ?? "replace")
      : "keep",
    webhookSecret: "",
    sender: currentDraft?.sender ?? createEmptySenderPolicy(),
    rateLimits: currentDraft?.rateLimits ?? {
      minute: "",
      hour: "",
      day: "",
    },
    circuitBreaker: currentDraft?.circuitBreaker ?? {
      threshold: "",
      window: "",
      cooldown: "",
    },
    configuration,
    secretConfiguration,
  };
}

function createDraftFromConnection(connection: AdminConnectionDetail): ConnectionDraft {
  const configuration = Object.fromEntries(
    connection.configurationFields
      .filter((field) => !field.secret)
      .map((field) => [field.name, valueToString(field.value)]),
  );
  const secretConfiguration = Object.fromEntries(
    connection.configurationFields
      .filter((field) => field.secret)
      .map((field) => [
        field.name,
        {
          action: (field.configured ? "keep" : "replace") as ConnectionSecretAction,
          value: "",
        },
      ]),
  ) as Record<string, SecretDraft>;

  return {
    id: connection.id,
    profile: connection.profile.key,
    name: connection.name,
    dsn: connection.dsn ?? "",
    enabled: connection.enabled,
    default: connection.default,
    priority: String(connection.priority),
    weight: String(connection.weight),
    webhookEnabled: hasEnabledConnectionWebhooks(connection),
    webhookSecretAction: supportsConnectionWebhooks(connection)
      ? connection.webhookSecretConfigured
        ? "keep"
        : "replace"
      : "keep",
    webhookSecret: "",
    sender: connection.sender,
    rateLimits: {
      minute: valueToString(connection.rateLimits.minute),
      hour: valueToString(connection.rateLimits.hour),
      day: valueToString(connection.rateLimits.day),
    },
    circuitBreaker: {
      threshold: valueToString(connection.circuitBreaker.threshold),
      window: valueToString(connection.circuitBreaker.window),
      cooldown: valueToString(connection.circuitBreaker.cooldown),
    },
    configuration,
    secretConfiguration,
  };
}

function parsePositiveInteger(value: string, fallback: number, minimum = 1): number {
  const parsedValue = Number.parseInt(value, 10);

  if (Number.isNaN(parsedValue)) {
    return fallback;
  }

  return Math.max(minimum, parsedValue);
}

function getOptionalInteger(value: string): number | undefined {
  if (value.trim() === "") {
    return undefined;
  }

  const parsedValue = Number.parseInt(value, 10);

  return Number.isNaN(parsedValue) ? undefined : Math.max(0, parsedValue);
}

function buildSavePayload(draft: ConnectionDraft): ConnectionSavePayload {
  const rateLimits: ConnectionSavePayload["rateLimits"] = {};
  const circuitBreaker: ConnectionSavePayload["circuitBreaker"] = {};

  const minute = getOptionalInteger(draft.rateLimits.minute);
  const hour = getOptionalInteger(draft.rateLimits.hour);
  const day = getOptionalInteger(draft.rateLimits.day);
  const threshold = getOptionalInteger(draft.circuitBreaker.threshold);
  const window = getOptionalInteger(draft.circuitBreaker.window);
  const cooldown = getOptionalInteger(draft.circuitBreaker.cooldown);

  if (minute !== undefined) {
    rateLimits.minute = minute;
  }

  if (hour !== undefined) {
    rateLimits.hour = hour;
  }

  if (day !== undefined) {
    rateLimits.day = day;
  }

  if (threshold !== undefined) {
    circuitBreaker.threshold = threshold;
  }

  if (window !== undefined) {
    circuitBreaker.window = window;
  }

  if (cooldown !== undefined) {
    circuitBreaker.cooldown = cooldown;
  }

  return {
    profile: draft.profile,
    name: draft.name,
    dsn: draft.dsn,
    enabled: draft.enabled,
    default: draft.default,
    priority: parsePositiveInteger(draft.priority, 10),
    weight: parsePositiveInteger(draft.weight, 1),
    webhookEnabled: draft.webhookEnabled,
    webhookSecretAction: draft.webhookSecretAction,
    webhookSecret: draft.webhookSecret,
    sender: draft.sender,
    rateLimits: Object.keys(rateLimits).length > 0 ? rateLimits : undefined,
    circuitBreaker: Object.keys(circuitBreaker).length > 0 ? circuitBreaker : undefined,
    configuration: draft.configuration,
    secretConfiguration: draft.secretConfiguration,
  };
}

function subscribeToHashChange(callback: () => void): () => void {
  if (typeof window === "undefined") {
    return () => undefined;
  }

  window.addEventListener("hashchange", callback);

  return () => window.removeEventListener("hashchange", callback);
}

function createClosedAdvancedSettingsState(): AdvancedSettingsOpenState {
  return {
    sender: false,
    routing: false,
    safeguards: false,
    webhooks: false,
  };
}

function getConnectionIdFromHash(): number | null {
  if (typeof window === "undefined") {
    return null;
  }

  const connectionId = parseAdminHashLocation(window.location.hash).searchParams.get("id");

  if (!connectionId || /^\d+$/.test(connectionId) === false) {
    return null;
  }

  return Number(connectionId);
}

export default function ConnectionsPage() {
  const loadConnections = React.useCallback(() => getConnections(), []);
  const { data, setData, loading, error, refresh } = useAdminQuery(loadConnections);
  const [selectedConnectionId, setSelectedConnectionId] = React.useState<number | null>(null);
  const [currentConnection, setCurrentConnection] = React.useState<AdminConnectionDetail | null>(
    null,
  );
  const [draft, setDraft] = React.useState<ConnectionDraft | null>(null);
  const [detailLoading, setDetailLoading] = React.useState(false);
  const [detailError, setDetailError] = React.useState<string | null>(null);
  const [actionError, setActionError] = React.useState<string | null>(null);
  const [saving, setSaving] = React.useState(false);
  const [enabledSavingConnectionIds, setEnabledSavingConnectionIds] = React.useState<Set<number>>(
    () => new Set(),
  );
  const [defaultSavingConnectionId, setDefaultSavingConnectionId] = React.useState<number | null>(
    null,
  );
  const [advancedSettingsOpen, setAdvancedSettingsOpen] = React.useState<AdvancedSettingsOpenState>(
    createClosedAdvancedSettingsState,
  );
  const targetedConnectionId = React.useSyncExternalStore(
    subscribeToHashChange,
    getConnectionIdFromHash,
    () => null,
  );

  const profiles = data?.profiles ?? [];
  const connections = data?.connections ?? [];
  const activeProfile = profiles.find((profile) => profile.key === draft?.profile);
  const activeProfileDescription =
    activeProfile?.description ??
    currentConnection?.profile.description ??
    "No profile description is available.";
  const activeProfileMetadata = activeProfile?.metadata ?? currentConnection?.profile.metadata;
  const activeProfileMetadataExtraEntries = getProfileMetadataExtraEntries(activeProfileMetadata);
  const activeProfileKey =
    activeProfile?.key ?? currentConnection?.profile.key ?? draft?.profile ?? "";
  const activeProfileLabel =
    activeProfile?.label ?? currentConnection?.profile.label ?? "Selected profile";
  const activeProfileMark = formatProfileMark(activeProfileLabel);
  const activeProfileBrandStyle = getProfileBrandStyle(activeProfileKey);
  const activeProfileIconAsset = PROFILE_BRAND_ICON_ASSETS[activeProfileKey];
  const activeProfileIconContainerClassName =
    PROFILE_BRAND_ICON_CONTAINER_CLASSES[activeProfileKey];
  const ActiveProfileIcon = PROFILE_BRAND_ICONS[activeProfileKey];
  const activeProfileUseCases = activeProfileMetadata?.useCases ?? [];
  const hasConnectionAvailabilityDetails =
    (currentConnection?.unavailableReasons.length ?? 0) > 0 ||
    Boolean(currentConnection?.nextAvailableAt);
  const activeFields = React.useMemo(() => {
    if (!draft) {
      return [];
    }

    const profileFields =
      currentConnection && currentConnection.profile.key === draft.profile
        ? currentConnection.configurationFields
        : (activeProfile?.configurationFields ?? []);

    return profileFields.filter((field) => isFieldVisible(field, draft));
  }, [activeProfile, currentConnection, draft]);
  const transportSchemeField = activeFields.find((field) => field.name === "scheme");
  const providerConfigurationFields = activeFields.filter((field) => field.name !== "scheme");
  const supportsWebhooks =
    activeProfile?.supportsWebhooks ?? currentConnection?.profile.supportsWebhooks ?? false;
  const webhookSecretConfigured = currentConnection?.webhookSecretConfigured === true;
  const draftPriorityLabel = draft?.priority.trim() || "10";
  const draftWeightLabel = draft?.weight.trim() || "1";
  const hasCustomSender = Boolean(
    draft?.sender.email.trim() ||
    draft?.sender.name.trim() ||
    draft?.sender.forceEmail === true ||
    draft?.sender.forceName === true ||
    draft?.sender.returnPathMode !== "inherit" ||
    draft?.sender.returnPathEmail.trim(),
  );
  const hasCustomRouting =
    Boolean(draft) && (draftPriorityLabel !== "10" || draftWeightLabel !== "1");
  const hasCustomRateLimits = Boolean(
    draft?.rateLimits.minute.trim() ||
    draft?.rateLimits.hour.trim() ||
    draft?.rateLimits.day.trim(),
  );
  const hasCustomCircuitBreaker = Boolean(
    draft?.circuitBreaker.threshold.trim() ||
    draft?.circuitBreaker.window.trim() ||
    draft?.circuitBreaker.cooldown.trim(),
  );
  const hasCustomSafeguards = hasCustomRateLimits || hasCustomCircuitBreaker;
  const advancedSettingsCount = [
    hasCustomSender,
    hasCustomRouting,
    hasCustomSafeguards,
    supportsWebhooks && draft?.webhookEnabled === true,
  ].filter(Boolean).length;
  const showOverview = selectedConnectionId === null && draft === null;

  const syncConnectionHash = React.useCallback((connectionId: number | null) => {
    if (typeof window === "undefined") {
      return;
    }

    const { searchParams } = parseAdminHashLocation(window.location.hash);

    if (connectionId === null) {
      searchParams.delete("id");
    } else {
      searchParams.set("id", `${connectionId}`);
    }

    const nextUrl = new URL(window.location.href);
    nextUrl.hash = buildAdminHashHref("/connections", searchParams);
    window.history.replaceState(window.history.state, "", nextUrl);
  }, []);

  const handleReturnToList = React.useCallback(() => {
    syncConnectionHash(null);
    setSelectedConnectionId(null);
    setCurrentConnection(null);
    setDraft(null);
    setDetailError(null);
    setActionError(null);
    setAdvancedSettingsOpen(createClosedAdvancedSettingsState());
  }, [syncConnectionHash]);

  React.useEffect(() => {
    if (!data) {
      return;
    }

    const hashConnectionId = getConnectionIdFromHash();

    if (hashConnectionId !== null) {
      if (selectedConnectionId === hashConnectionId) {
        return;
      }

      setSelectedConnectionId(hashConnectionId);
      setCurrentConnection(null);
      setDraft(null);
      setDetailError(null);
      setActionError(null);
      setAdvancedSettingsOpen(createClosedAdvancedSettingsState());
      return;
    }

    if (selectedConnectionId !== null) {
      handleReturnToList();
    }
  }, [data, handleReturnToList, selectedConnectionId, targetedConnectionId]);

  React.useEffect(() => {
    if (selectedConnectionId === null) {
      return;
    }

    let active = true;

    setDetailLoading(true);
    setDetailError(null);

    void getConnection(selectedConnectionId)
      .then((response) => {
        if (!active) {
          return;
        }

        setCurrentConnection(response.connection);
        setDraft(createDraftFromConnection(response.connection));
      })
      .catch((caughtError) => {
        if (!active) {
          return;
        }

        setDetailError(
          caughtError instanceof Error
            ? caughtError.message
            : "The connection could not be loaded.",
        );
      })
      .finally(() => {
        if (active) {
          setDetailLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, [selectedConnectionId]);

  const handleCreateConnection = () => {
    if (!profiles[0]) {
      return;
    }

    syncConnectionHash(null);
    setSelectedConnectionId(null);
    setCurrentConnection(null);
    setDetailError(null);
    setActionError(null);
    setAdvancedSettingsOpen(createClosedAdvancedSettingsState());
    setDraft(createDraftFromProfile(profiles[0]));
  };

  const handleSelectConnection = (connectionId: number | null) => {
    if (connectionId === null) {
      handleCreateConnection();
      return;
    }

    syncConnectionHash(connectionId);
    setSelectedConnectionId(connectionId);
    setCurrentConnection(null);
    setDraft(null);
    setDetailError(null);
    setActionError(null);
    setAdvancedSettingsOpen(createClosedAdvancedSettingsState());
  };

  const handleEnabledChange = React.useCallback(
    async (connectionId: number, enabled: boolean) => {
      setEnabledSavingConnectionIds((currentIds) => {
        const nextIds = new Set(currentIds);

        nextIds.add(connectionId);

        return nextIds;
      });
      setActionError(null);

      try {
        const response = await setConnectionEnabled(connectionId, enabled);

        setData((currentData) =>
          currentData
            ? {
                ...currentData,
                connections: currentData.connections.map((connection) =>
                  connection.id === connectionId
                    ? { ...connection, ...response.connection }
                    : connection,
                ),
              }
            : currentData,
        );
        await refresh();
      } catch (caughtError) {
        setActionError(
          caughtError instanceof Error
            ? caughtError.message
            : "The connection could not be updated.",
        );
      } finally {
        setEnabledSavingConnectionIds((currentIds) => {
          const nextIds = new Set(currentIds);

          nextIds.delete(connectionId);

          return nextIds;
        });
      }
    },
    [refresh, setData],
  );

  const handleDefaultChange = React.useCallback(
    async (connectionId: number) => {
      setDefaultSavingConnectionId(connectionId);
      setActionError(null);

      try {
        const response = await makeDefaultConnection(connectionId);

        setData((currentData) =>
          currentData
            ? {
                ...currentData,
                connections: currentData.connections.map((connection) =>
                  connection.id === connectionId
                    ? { ...connection, ...response.connection }
                    : { ...connection, default: false },
                ),
              }
            : currentData,
        );
        await refresh();
      } catch (caughtError) {
        setActionError(
          caughtError instanceof Error
            ? caughtError.message
            : "The default connection could not be updated.",
        );
      } finally {
        setDefaultSavingConnectionId(null);
      }
    },
    [refresh, setData],
  );

  const handleProfileChange = (profileKey: string | null) => {
    if (!profileKey) {
      return;
    }

    const nextProfile = profiles.find((profile) => profile.key === profileKey);

    if (!nextProfile) {
      return;
    }

    setDraft((currentDraft) => createDraftFromProfile(nextProfile, currentDraft ?? undefined));
  };

  const setSenderPolicy = <Key extends keyof AdminConnectionSenderPolicy>(
    key: Key,
    value: AdminConnectionSenderPolicy[Key],
  ) => {
    setDraft((currentDraft) =>
      currentDraft
        ? {
            ...currentDraft,
            sender: {
              ...currentDraft.sender,
              [key]: value,
            },
          }
        : currentDraft,
    );
  };

  const handleSave = async () => {
    if (!draft) {
      return;
    }

    const validationError = validateDraft(draft, activeFields);

    if (validationError) {
      setActionError(validationError);
      return;
    }

    setSaving(true);
    setActionError(null);

    try {
      const response = await saveConnection(buildSavePayload(draft), draft.id ?? undefined);
      setCurrentConnection(response.connection);
      setDraft(createDraftFromConnection(response.connection));
      setSelectedConnectionId(response.connection.id);
      syncConnectionHash(response.connection.id);
      await refresh();
    } catch (caughtError) {
      setActionError(
        caughtError instanceof Error ? caughtError.message : "The connection could not be saved.",
      );
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!draft?.id || !window.confirm(`Delete connection "${draft.name}"?`)) {
      return;
    }

    setSaving(true);
    setActionError(null);

    try {
      await deleteConnection(draft.id);
      await refresh();
      handleReturnToList();
    } catch (caughtError) {
      setActionError(
        caughtError instanceof Error ? caughtError.message : "The connection could not be deleted.",
      );
    } finally {
      setSaving(false);
    }
  };

  if (loading && !draft) {
    return (
      <div className="flex flex-1 flex-col px-4 py-4 lg:px-6 lg:py-6">
        <Card>
          <CardHeader>
            <CardTitle>Loading connections</CardTitle>
            <CardDescription>
              Reading connection profiles and current provider configuration.
            </CardDescription>
          </CardHeader>
        </Card>
      </div>
    );
  }

  if (!draft && profiles.length === 0 && connections.length === 0) {
    return (
      <div className="flex flex-1 flex-col px-4 py-4 lg:px-6 lg:py-6">
        <Card>
          <CardHeader>
            <CardTitle>No connection profiles available</CardTitle>
            <CardDescription>
              Jooosi Mail could not find any registered transport profiles to configure.
            </CardDescription>
          </CardHeader>
        </Card>
      </div>
    );
  }

  return (
    <div className="@container/main flex flex-1 flex-col gap-4 py-4 md:gap-6 md:py-6">
      <div className="flex flex-col gap-3 px-4 lg:flex-row lg:items-start lg:justify-between lg:px-6">
        <div className="flex flex-col gap-1">
          {showOverview ? (
            <h2 className="text-xl font-semibold tracking-tight">Connections</h2>
          ) : (
            <Breadcrumb>
              <BreadcrumbList className="gap-2 text-xl font-semibold tracking-tight">
                <BreadcrumbItem>
                  <BreadcrumbLink
                    render={<button type="button" />}
                    onClick={handleReturnToList}
                    className="font-semibold tracking-tight"
                  >
                    Connections
                  </BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator className="[&>svg]:size-4" />
                <BreadcrumbItem>
                  <BreadcrumbPage className="text-xl font-semibold tracking-tight">
                    {selectedConnectionId === null ? "New connection" : draft?.name || "Connection"}
                  </BreadcrumbPage>
                </BreadcrumbItem>
              </BreadcrumbList>
            </Breadcrumb>
          )}
          <p className="text-sm text-muted-foreground">
            {showOverview
              ? "Set up and manage the email services Jooosi Mail can use to send messages from your site."
              : "Review and update the selected connection settings."}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {showOverview ? (
            <Button type="button" onClick={handleCreateConnection}>
              <Add01Icon data-icon="inline-start" />
              New connection
            </Button>
          ) : draft ? (
            <>
              <div className="flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium shadow-xs">
                <Switch
                  id="connection-enabled"
                  checked={draft.enabled}
                  onCheckedChange={(checked) => {
                    setDraft((currentDraft) =>
                      currentDraft
                        ? {
                            ...currentDraft,
                            enabled: checked === true,
                            default: checked === true ? currentDraft.default : false,
                          }
                        : currentDraft,
                    );
                  }}
                />
                <Label htmlFor="connection-enabled" className="cursor-pointer">
                  Enabled
                </Label>
              </div>
              {draft.id ? (
                <Button
                  type="button"
                  variant="destructive"
                  onClick={() => void handleDelete()}
                  disabled={saving || detailLoading}
                >
                  Delete
                </Button>
              ) : null}
              <Button
                type="button"
                onClick={() => void handleSave()}
                disabled={saving || detailLoading}
              >
                {saving ? "Saving..." : draft.id ? "Save changes" : "Create connection"}
              </Button>
            </>
          ) : null}
        </div>
      </div>

      {error || actionError || detailError ? (
        <div className="px-4 lg:px-6">
          <Card>
            <CardContent className="py-4 text-sm text-destructive">
              {actionError ?? detailError ?? error}
            </CardContent>
          </Card>
        </div>
      ) : null}

      <div className="px-4 lg:px-6">
        {showOverview ? (
          <ConnectionsOverviewTable
            profiles={profiles}
            connections={connections}
            onOpenConnection={handleSelectConnection}
            onCreateConnection={handleCreateConnection}
            onEnabledChange={handleEnabledChange}
            onDefaultChange={handleDefaultChange}
            enabledSavingConnectionIds={enabledSavingConnectionIds}
            defaultSavingConnectionId={defaultSavingConnectionId}
          />
        ) : (
          <div className="flex flex-col gap-6">
            {detailLoading ? (
              <div className="text-sm text-muted-foreground">Loading connection details...</div>
            ) : draft ? (
              <>
                <Card className="overflow-hidden py-0" style={activeProfileBrandStyle}>
                  <div className="grid lg:grid-cols-[9rem_minmax(0,1fr)]">
                    <div className="flex items-center justify-center border-b bg-[linear-gradient(135deg,var(--profile-brand-soft),transparent_70%)] p-5 lg:border-r lg:border-b-0">
                      <div
                        className={cn(
                          "flex size-14 items-center justify-center rounded-2xl shadow-xs ring-8 ring-background/70",
                          activeProfileIconAsset
                            ? (activeProfileIconAsset.containerClassName ?? "bg-background p-2")
                            : (activeProfileIconContainerClassName ??
                                "bg-[var(--profile-brand)] text-3xl font-semibold text-[var(--profile-brand-foreground)]"),
                        )}
                      >
                        {activeProfileIconAsset ? (
                          <img
                            src={activeProfileIconAsset.src}
                            alt=""
                            aria-hidden="true"
                            className={cn(
                              "size-10 object-contain",
                              activeProfileIconAsset.className,
                            )}
                          />
                        ) : ActiveProfileIcon ? (
                          <ActiveProfileIcon aria-hidden="true" />
                        ) : (
                          activeProfileMark
                        )}
                      </div>
                    </div>

                    <div className="min-w-0">
                      <CardHeader className="border-b px-5 py-5">
                        <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                          <div className="min-w-0">
                            <CardTitle className="text-2xl">{activeProfileLabel}</CardTitle>
                            <CardDescription className="mt-2 max-w-3xl text-base leading-relaxed">
                              {activeProfileDescription}
                            </CardDescription>
                          </div>
                          {activeProfileMetadata?.website || activeProfileMetadata?.docsUrl ? (
                            <div className="flex flex-wrap gap-2 xl:justify-end">
                              {activeProfileMetadata?.website ? (
                                <a
                                  href={activeProfileMetadata.website}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium shadow-xs transition-colors hover:bg-muted"
                                >
                                  <Globe02Icon aria-hidden="true" />
                                  Provider site
                                </a>
                              ) : null}
                              {activeProfileMetadata?.docsUrl ? (
                                <a
                                  href={activeProfileMetadata.docsUrl}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium shadow-xs transition-colors hover:bg-muted"
                                >
                                  <BookOpen01Icon aria-hidden="true" />
                                  Setup docs
                                </a>
                              ) : null}
                            </div>
                          ) : null}
                        </div>
                      </CardHeader>

                      <CardContent className="p-0">
                        <div className="grid gap-px bg-border text-sm md:grid-cols-[repeat(auto-fit,minmax(16rem,1fr))]">
                          {activeProfileUseCases.length > 0 ? (
                            <div className="bg-card p-4">
                              <dt className="flex items-center gap-2 text-muted-foreground">
                                <span className="flex size-7 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                  <Briefcase02Icon aria-hidden="true" />
                                </span>
                                Best suited for
                              </dt>
                              <dd className="mt-3 font-medium text-foreground">
                                {activeProfileUseCases
                                  .map((useCase) => titleCase(useCase))
                                  .join(", ")}
                              </dd>
                            </div>
                          ) : null}
                          <div className="bg-card p-4">
                            <dt className="flex items-center gap-2 text-muted-foreground">
                              <span
                                className={cn(
                                  "flex size-7 items-center justify-center rounded-md",
                                  supportsWebhooks
                                    ? "bg-[var(--profile-brand-soft)] text-[var(--profile-brand)]"
                                    : "bg-muted text-muted-foreground",
                                )}
                              >
                                <WebhookIcon aria-hidden="true" />
                              </span>
                              Webhook support
                            </dt>
                            <dd className="mt-3 flex items-center gap-2 font-medium text-foreground">
                              <span
                                className={cn(
                                  "size-2 rounded-full",
                                  supportsWebhooks
                                    ? "bg-[var(--profile-brand)]"
                                    : "bg-muted-foreground/50",
                                )}
                              />
                              <span>{supportsWebhooks ? "Available" : "Unavailable"}</span>
                            </dd>
                          </div>
                          {activeProfileMetadataExtraEntries.map(([label, value]) => (
                            <div key={label} className="bg-card p-4">
                              <dt className="flex items-center gap-2 text-muted-foreground">
                                <InformationCircleIcon aria-hidden="true" />
                                {formatProfileMetadataLabel(label)}
                              </dt>
                              <dd className="mt-2 font-medium text-foreground">
                                {formatProfileMetadataValue(value)}
                              </dd>
                            </div>
                          ))}
                        </div>
                        {hasConnectionAvailabilityDetails ? (
                          <div className="border-t p-4 text-sm">
                            <div className="flex items-start gap-3">
                              <ClockAlertIcon aria-hidden="true" />
                              <div className="min-w-0">
                                <div className="font-medium">Availability note</div>
                                <div className="mt-2 flex flex-col gap-2">
                                  {(currentConnection?.unavailableReasons.length ?? 0) > 0 ? (
                                    <p className="text-destructive">
                                      {currentConnection?.unavailableReasons.join(", ")}
                                    </p>
                                  ) : null}
                                  {currentConnection?.nextAvailableAt ? (
                                    <p className="text-muted-foreground">
                                      Next available at{" "}
                                      {formatAdminDateTime(currentConnection.nextAvailableAt)}.
                                    </p>
                                  ) : null}
                                </div>
                              </div>
                            </div>
                          </div>
                        ) : null}
                      </CardContent>
                    </div>
                  </div>
                </Card>

                <section className="flex flex-col gap-4">
                  <div className="flex flex-col gap-1">
                    <h3 className="text-base font-medium">Basics</h3>
                    <p className="text-sm text-muted-foreground">
                      Name the route, choose a profile, and configure its provider details.
                    </p>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div className="grid gap-2">
                      <Label htmlFor="connection-name">Connection name</Label>
                      <Input
                        id="connection-name"
                        value={draft.name}
                        onChange={(event) => {
                          setDraft((currentDraft) =>
                            currentDraft
                              ? {
                                  ...currentDraft,
                                  name: event.target.value,
                                }
                              : currentDraft,
                          );
                        }}
                      />
                    </div>
                    <div className="grid gap-2">
                      <Label htmlFor="connection-profile">Profile</Label>
                      <Select value={draft.profile} onValueChange={handleProfileChange}>
                        <SelectTrigger id="connection-profile" className="w-full">
                          <SelectValue placeholder="Select a profile" />
                        </SelectTrigger>
                        <SelectContent>
                          {profiles.map((profile) => (
                            <SelectItem key={profile.key} value={profile.key}>
                              {profile.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    {transportSchemeField ? (
                      <div className="grid gap-2">
                        <Label htmlFor="provider-field-scheme" className="gap-1">
                          {transportSchemeField.label}
                          {isFieldRequired(transportSchemeField, draft) ? (
                            <span aria-hidden="true" className="text-destructive">
                              *
                            </span>
                          ) : null}
                        </Label>
                        <Select
                          value={draft.configuration[transportSchemeField.name] || ""}
                          onValueChange={(value) => {
                            setDraft((currentDraft) =>
                              currentDraft
                                ? {
                                    ...currentDraft,
                                    configuration: {
                                      ...currentDraft.configuration,
                                      [transportSchemeField.name]: value ?? "",
                                    },
                                  }
                                : currentDraft,
                            );
                          }}
                        >
                          <SelectTrigger
                            id="provider-field-scheme"
                            aria-required={isFieldRequired(transportSchemeField, draft)}
                            className="w-full"
                          >
                            <SelectValue
                              placeholder={`Select ${transportSchemeField.label.toLowerCase()}`}
                            />
                          </SelectTrigger>
                          <SelectContent>
                            {transportSchemeField.choices.map((choice) => (
                              <SelectItem key={choice} value={choice}>
                                {choice}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    ) : (
                      <div className="grid gap-2">
                        <Label htmlFor="connection-dsn">Raw DSN override</Label>
                        <Input
                          id="connection-dsn"
                          value={draft.dsn}
                          onChange={(event) => {
                            setDraft((currentDraft) =>
                              currentDraft
                                ? {
                                    ...currentDraft,
                                    dsn: event.target.value,
                                  }
                                : currentDraft,
                            );
                          }}
                          placeholder="Leave empty to build the DSN from profile fields"
                        />
                      </div>
                    )}
                  </div>
                </section>

                <section className="flex flex-col gap-4 border-t pt-6">
                  <div className="flex flex-col gap-1">
                    <h3 className="text-base font-medium">Provider configuration</h3>
                    <p className="text-sm text-muted-foreground">
                      Enter the fields required by the selected provider profile.
                    </p>
                  </div>

                  {providerConfigurationFields.length === 0 && !transportSchemeField ? (
                    <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                      This profile does not expose editable configuration fields.
                    </div>
                  ) : (
                    <FieldGroup className="grid gap-4 md:grid-cols-2">
                      {transportSchemeField ? (
                        <Field className="gap-3 rounded-lg border bg-muted/20 p-4 shadow-xs">
                          <FieldLabel htmlFor="connection-dsn">Raw DSN override</FieldLabel>
                          <Input
                            id="connection-dsn"
                            value={draft.dsn}
                            onChange={(event) => {
                              setDraft((currentDraft) =>
                                currentDraft
                                  ? {
                                      ...currentDraft,
                                      dsn: event.target.value,
                                    }
                                  : currentDraft,
                              );
                            }}
                            placeholder="Leave empty to build the DSN from profile fields"
                          />
                          <FieldDescription>
                            Leave empty to build the DSN from profile fields.
                          </FieldDescription>
                        </Field>
                      ) : null}
                      {providerConfigurationFields.map((field) => {
                        const secretDraft = draft.secretConfiguration[field.name];
                        const secretConfigured =
                          secretDraft?.action === "keep" || field.configured === true;
                        const isMultilineSecret = field.name.includes("private_key");
                        const fieldIsRequired = isFieldRequired(field, draft);
                        const fieldId = `provider-field-${field.name}`;

                        return (
                          <Field
                            key={field.name}
                            className="gap-3 rounded-lg border bg-muted/20 p-4 shadow-xs"
                          >
                            <div className="flex items-center justify-between gap-3">
                              <FieldLabel
                                htmlFor={
                                  field.secret &&
                                  secretConfigured &&
                                  secretDraft?.action === "clear"
                                    ? undefined
                                    : fieldId
                                }
                                className="gap-1"
                              >
                                {field.label}
                                {fieldIsRequired ? (
                                  <span aria-hidden="true" className="text-destructive">
                                    *
                                  </span>
                                ) : null}
                              </FieldLabel>
                              {field.secret && secretConfigured ? (
                                secretDraft?.action === "replace" ? (
                                  <Button
                                    type="button"
                                    size="xs"
                                    variant="ghost"
                                    onClick={() => {
                                      setDraft((currentDraft) =>
                                        currentDraft
                                          ? {
                                              ...currentDraft,
                                              secretConfiguration: {
                                                ...currentDraft.secretConfiguration,
                                                [field.name]: {
                                                  ...currentDraft.secretConfiguration[field.name],
                                                  action: "keep",
                                                  value: "",
                                                },
                                              },
                                            }
                                          : currentDraft,
                                      );
                                    }}
                                  >
                                    Cancel
                                  </Button>
                                ) : secretDraft?.action === "clear" ? (
                                  <Button
                                    type="button"
                                    size="xs"
                                    variant="outline"
                                    onClick={() => {
                                      setDraft((currentDraft) =>
                                        currentDraft
                                          ? {
                                              ...currentDraft,
                                              secretConfiguration: {
                                                ...currentDraft.secretConfiguration,
                                                [field.name]: {
                                                  ...currentDraft.secretConfiguration[field.name],
                                                  action: "keep",
                                                  value: "",
                                                },
                                              },
                                            }
                                          : currentDraft,
                                      );
                                    }}
                                  >
                                    Undo
                                  </Button>
                                ) : (
                                  <div className="flex flex-wrap gap-1">
                                    <Button
                                      type="button"
                                      size="xs"
                                      variant="outline"
                                      onClick={() => {
                                        setDraft((currentDraft) =>
                                          currentDraft
                                            ? {
                                                ...currentDraft,
                                                secretConfiguration: {
                                                  ...currentDraft.secretConfiguration,
                                                  [field.name]: {
                                                    ...currentDraft.secretConfiguration[field.name],
                                                    action: "replace",
                                                    value: "",
                                                  },
                                                },
                                              }
                                            : currentDraft,
                                        );
                                      }}
                                    >
                                      Replace
                                    </Button>
                                    <Button
                                      type="button"
                                      size="xs"
                                      variant="destructive"
                                      onClick={() => {
                                        setDraft((currentDraft) =>
                                          currentDraft
                                            ? {
                                                ...currentDraft,
                                                secretConfiguration: {
                                                  ...currentDraft.secretConfiguration,
                                                  [field.name]: {
                                                    ...currentDraft.secretConfiguration[field.name],
                                                    action: "clear",
                                                    value: "",
                                                  },
                                                },
                                              }
                                            : currentDraft,
                                        );
                                      }}
                                    >
                                      Clear
                                    </Button>
                                  </div>
                                )
                              ) : null}
                            </div>

                            {field.secret ? (
                              secretDraft?.action === "replace" || !secretConfigured ? (
                                isMultilineSecret ? (
                                  <Textarea
                                    id={fieldId}
                                    required={fieldIsRequired}
                                    value={secretDraft?.value ?? ""}
                                    onChange={(event) => {
                                      setDraft((currentDraft) =>
                                        currentDraft
                                          ? {
                                              ...currentDraft,
                                              secretConfiguration: {
                                                ...currentDraft.secretConfiguration,
                                                [field.name]: {
                                                  action: "replace",
                                                  value: event.target.value,
                                                },
                                              },
                                            }
                                          : currentDraft,
                                      );
                                    }}
                                    placeholder={
                                      field.configured
                                        ? "Enter a new value to replace the stored secret"
                                        : "Enter the secret value"
                                    }
                                  />
                                ) : (
                                  <Input
                                    id={fieldId}
                                    type="password"
                                    required={fieldIsRequired}
                                    value={secretDraft?.value ?? ""}
                                    onChange={(event) => {
                                      setDraft((currentDraft) =>
                                        currentDraft
                                          ? {
                                              ...currentDraft,
                                              secretConfiguration: {
                                                ...currentDraft.secretConfiguration,
                                                [field.name]: {
                                                  action: "replace",
                                                  value: event.target.value,
                                                },
                                              },
                                            }
                                          : currentDraft,
                                      );
                                    }}
                                    placeholder={
                                      field.configured
                                        ? "Enter a new value to replace the stored secret"
                                        : "Enter the secret value"
                                    }
                                  />
                                )
                              ) : secretDraft?.action === "clear" ? (
                                <FieldDescription className="rounded-md border border-destructive/20 bg-destructive/10 px-3 py-2 text-sm text-destructive last:mt-0 nth-last-2:mt-0">
                                  The stored secret will be removed when you save.
                                </FieldDescription>
                              ) : (
                                <Input id={fieldId} type="password" value="********" disabled />
                              )
                            ) : field.type === "choice" ? (
                              <Select
                                value={draft.configuration[field.name] || ""}
                                onValueChange={(value) => {
                                  setDraft((currentDraft) =>
                                    currentDraft
                                      ? {
                                          ...currentDraft,
                                          configuration: {
                                            ...currentDraft.configuration,
                                            [field.name]: value ?? "",
                                          },
                                        }
                                      : currentDraft,
                                  );
                                }}
                              >
                                <SelectTrigger
                                  id={fieldId}
                                  aria-required={fieldIsRequired}
                                  className="w-full"
                                >
                                  <SelectValue
                                    placeholder={`Select ${field.label.toLowerCase()}`}
                                  />
                                </SelectTrigger>
                                <SelectContent>
                                  {field.choices.map((choice) => (
                                    <SelectItem key={choice} value={choice}>
                                      {choice}
                                    </SelectItem>
                                  ))}
                                </SelectContent>
                              </Select>
                            ) : (
                              <Input
                                id={fieldId}
                                type={field.type === "number" ? "number" : "text"}
                                required={fieldIsRequired}
                                value={draft.configuration[field.name] || ""}
                                onChange={(event) => {
                                  setDraft((currentDraft) =>
                                    currentDraft
                                      ? {
                                          ...currentDraft,
                                          configuration: {
                                            ...currentDraft.configuration,
                                            [field.name]: event.target.value,
                                          },
                                        }
                                      : currentDraft,
                                  );
                                }}
                              />
                            )}
                          </Field>
                        );
                      })}
                    </FieldGroup>
                  )}
                </section>

                <section className="border-t pt-6">
                  <Card className="gap-0 overflow-hidden py-0">
                    <CardHeader className="border-b px-5 py-5">
                      <CardTitle className="flex flex-wrap items-center gap-2">
                        Advanced settings
                        <Badge variant={advancedSettingsCount > 0 ? "secondary" : "outline"}>
                          {advancedSettingsCount === 0
                            ? "Using defaults"
                            : `${advancedSettingsCount} active override${advancedSettingsCount === 1 ? "" : "s"}`}
                        </Badge>
                      </CardTitle>
                      <CardDescription>
                        Fine-tune delivery order, safety limits, and callback handling only when
                        this route needs custom behavior.
                      </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                      <div className="flex flex-col gap-px bg-border">
                        <Collapsible
                          open={advancedSettingsOpen.sender}
                          onOpenChange={(open) => {
                            setAdvancedSettingsOpen((current) => ({
                              ...current,
                              sender: open,
                            }));
                          }}
                          render={<div className="contents" />}
                        >
                          <CollapsibleTrigger
                            render={
                              <button
                                type="button"
                                className={cn(
                                  "group flex w-full flex-col gap-3 bg-card p-4 text-left text-card-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50",
                                  advancedSettingsOpen.sender ? "bg-muted/40" : "",
                                )}
                              />
                            }
                          >
                            <span className="flex items-start justify-between gap-3">
                              <span className="flex min-w-0 flex-col gap-1">
                                <span className="text-sm font-medium">Sender identity</span>
                                <span className="text-sm text-muted-foreground">
                                  {hasCustomSender
                                    ? "This route has its own From or Return-Path policy."
                                    : "Global sender settings are inherited."}
                                </span>
                              </span>
                              <span className="flex shrink-0 items-center gap-2">
                                <Badge variant={hasCustomSender ? "secondary" : "outline"}>
                                  {hasCustomSender ? "Custom" : "Inherited"}
                                </Badge>
                                <ArrowRight01Icon
                                  aria-hidden="true"
                                  className="transition-transform group-data-panel-open:rotate-90"
                                />
                              </span>
                            </span>
                          </CollapsibleTrigger>
                          <CollapsibleContent className="bg-background p-5">
                            <Card size="sm" className="shadow-none">
                              <CardHeader>
                                <CardTitle>Sender identity</CardTitle>
                                <CardDescription>
                                  Override global sender settings only when this provider requires a
                                  verified address or bounce domain.
                                </CardDescription>
                              </CardHeader>
                              <CardContent className="flex flex-col gap-4">
                                <FieldGroup className="grid gap-4 md:grid-cols-2 **:data-[slot=field]:gap-2">
                                  <Field>
                                    <FieldLabel htmlFor="connection-sender-email">
                                      From Email
                                    </FieldLabel>
                                    <Input
                                      id="connection-sender-email"
                                      type="email"
                                      value={draft.sender.email}
                                      onChange={(event) =>
                                        setSenderPolicy("email", event.target.value)
                                      }
                                      placeholder="Inherit global From Email"
                                    />
                                    <FieldDescription>
                                      Used for this connection when the message does not provide a
                                      From header, or when this connection forces From Email.
                                    </FieldDescription>
                                  </Field>
                                  <Field>
                                    <FieldLabel htmlFor="connection-sender-name">
                                      From Name
                                    </FieldLabel>
                                    <Input
                                      id="connection-sender-name"
                                      value={draft.sender.name}
                                      onChange={(event) =>
                                        setSenderPolicy("name", event.target.value)
                                      }
                                      placeholder="Inherit global From Name"
                                    />
                                    <FieldDescription>
                                      Display name paired with this connection's From Email.
                                    </FieldDescription>
                                  </Field>
                                </FieldGroup>

                                <FieldGroup className="grid gap-4 md:grid-cols-2">
                                  <Field
                                    orientation="horizontal"
                                    className="gap-4 rounded-xl border bg-muted/30 p-4"
                                  >
                                    <FieldContent className="gap-1">
                                      <FieldLabel htmlFor="connection-force-email">
                                        Force From Email
                                      </FieldLabel>
                                      <FieldDescription>
                                        Override plugin-supplied From email addresses. When off, the
                                        normal/global priority applies.
                                      </FieldDescription>
                                    </FieldContent>
                                    <Switch
                                      id="connection-force-email"
                                      checked={draft.sender.forceEmail}
                                      onCheckedChange={(checked) =>
                                        setSenderPolicy("forceEmail", checked)
                                      }
                                    />
                                  </Field>
                                  <Field
                                    orientation="horizontal"
                                    className="gap-4 rounded-xl border bg-muted/30 p-4"
                                  >
                                    <FieldContent className="gap-1">
                                      <FieldLabel htmlFor="connection-force-name">
                                        Force From Name
                                      </FieldLabel>
                                      <FieldDescription>
                                        Override plugin-supplied sender names. When off, the
                                        normal/global priority applies.
                                      </FieldDescription>
                                    </FieldContent>
                                    <Switch
                                      id="connection-force-name"
                                      checked={draft.sender.forceName}
                                      onCheckedChange={(checked) =>
                                        setSenderPolicy("forceName", checked)
                                      }
                                    />
                                  </Field>
                                </FieldGroup>

                                <Separator />

                                <FieldGroup className="grid gap-4 md:grid-cols-2 **:data-[slot=field]:gap-2">
                                  <Field>
                                    <FieldLabel htmlFor="connection-return-path-mode">
                                      Return-Path
                                    </FieldLabel>
                                    <Select
                                      value={draft.sender.returnPathMode}
                                      onValueChange={(value) =>
                                        setSenderPolicy(
                                          "returnPathMode",
                                          value ?? draft.sender.returnPathMode,
                                        )
                                      }
                                    >
                                      <SelectTrigger
                                        id="connection-return-path-mode"
                                        className="w-full"
                                      >
                                        <SelectValue placeholder="Select Return-Path behavior" />
                                      </SelectTrigger>
                                      <SelectContent>
                                        <SelectGroup>
                                          {CONNECTION_RETURN_PATH_MODE_OPTIONS.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                              {option.label}
                                            </SelectItem>
                                          ))}
                                        </SelectGroup>
                                      </SelectContent>
                                    </Select>
                                    <FieldDescription>
                                      Return-Path is applied as the envelope sender where the
                                      transport supports it.
                                    </FieldDescription>
                                  </Field>

                                  {draft.sender.returnPathMode === "custom" ? (
                                    <Field>
                                      <FieldLabel htmlFor="connection-return-path-email">
                                        Return-Path Email
                                      </FieldLabel>
                                      <Input
                                        id="connection-return-path-email"
                                        type="email"
                                        value={draft.sender.returnPathEmail}
                                        onChange={(event) =>
                                          setSenderPolicy("returnPathEmail", event.target.value)
                                        }
                                        placeholder="bounces@example.com"
                                      />
                                      <FieldDescription>
                                        Use an address accepted by this provider for bounce
                                        handling.
                                      </FieldDescription>
                                    </Field>
                                  ) : null}
                                </FieldGroup>
                              </CardContent>
                            </Card>
                          </CollapsibleContent>
                        </Collapsible>

                        <Collapsible
                          open={advancedSettingsOpen.routing}
                          onOpenChange={(open) => {
                            setAdvancedSettingsOpen((current) => ({
                              ...current,
                              routing: open,
                            }));
                          }}
                          render={<div className="contents" />}
                        >
                          <CollapsibleTrigger
                            render={
                              <button
                                type="button"
                                className={cn(
                                  "group flex w-full flex-col gap-3 bg-card p-4 text-left text-card-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50",
                                  advancedSettingsOpen.routing ? "bg-muted/40" : "",
                                )}
                              />
                            }
                          >
                            <span className="flex items-start justify-between gap-3">
                              <span className="flex min-w-0 flex-col gap-1">
                                <span className="text-sm font-medium">Routing</span>
                                <span className="text-sm text-muted-foreground">
                                  Priority {draftPriorityLabel}, weight {draftWeightLabel}
                                </span>
                              </span>
                              <span className="flex shrink-0 items-center gap-2">
                                <Badge variant={hasCustomRouting ? "secondary" : "outline"}>
                                  {hasCustomRouting ? "Custom" : "Default"}
                                </Badge>
                                <ArrowRight01Icon
                                  aria-hidden="true"
                                  className="transition-transform group-data-panel-open:rotate-90"
                                />
                              </span>
                            </span>
                          </CollapsibleTrigger>
                          <CollapsibleContent className="bg-background p-5">
                            <Card size="sm" className="shadow-none">
                              <CardHeader>
                                <CardTitle>Routing</CardTitle>
                                <CardDescription>
                                  Set how this route is ordered when more than one enabled
                                  connection can send mail.
                                </CardDescription>
                              </CardHeader>
                              <CardContent>
                                <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                  <Field>
                                    <FieldLabel htmlFor="connection-priority">Priority</FieldLabel>
                                    <Input
                                      id="connection-priority"
                                      type="number"
                                      min="1"
                                      value={draft.priority}
                                      onChange={(event) => {
                                        setDraft((currentDraft) =>
                                          currentDraft
                                            ? {
                                                ...currentDraft,
                                                priority: event.target.value,
                                              }
                                            : currentDraft,
                                        );
                                      }}
                                    />
                                    <FieldDescription>
                                      Controls route ordering when connections are compared.
                                    </FieldDescription>
                                  </Field>
                                  <Field>
                                    <FieldLabel htmlFor="connection-weight">Weight</FieldLabel>
                                    <Input
                                      id="connection-weight"
                                      type="number"
                                      min="1"
                                      value={draft.weight}
                                      onChange={(event) => {
                                        setDraft((currentDraft) =>
                                          currentDraft
                                            ? {
                                                ...currentDraft,
                                                weight: event.target.value,
                                              }
                                            : currentDraft,
                                        );
                                      }}
                                    />
                                    <FieldDescription>
                                      Balances traffic between routes with matching priority.
                                    </FieldDescription>
                                  </Field>
                                </FieldGroup>
                              </CardContent>
                            </Card>
                          </CollapsibleContent>
                        </Collapsible>

                        <Collapsible
                          open={advancedSettingsOpen.safeguards}
                          onOpenChange={(open) => {
                            setAdvancedSettingsOpen((current) => ({
                              ...current,
                              safeguards: open,
                            }));
                          }}
                          render={<div className="contents" />}
                        >
                          <CollapsibleTrigger
                            render={
                              <button
                                type="button"
                                className={cn(
                                  "group flex w-full flex-col gap-3 bg-card p-4 text-left text-card-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50",
                                  advancedSettingsOpen.safeguards ? "bg-muted/40" : "",
                                )}
                              />
                            }
                          >
                            <span className="flex items-start justify-between gap-3">
                              <span className="flex min-w-0 flex-col gap-1">
                                <span className="text-sm font-medium">Safeguards</span>
                                <span className="text-sm text-muted-foreground">
                                  {hasCustomSafeguards
                                    ? "Route-specific limits are configured."
                                    : "Global safety settings are inherited."}
                                </span>
                              </span>
                              <span className="flex shrink-0 items-center gap-2">
                                <Badge variant={hasCustomSafeguards ? "secondary" : "outline"}>
                                  {hasCustomSafeguards ? "Custom" : "Inherited"}
                                </Badge>
                                <ArrowRight01Icon
                                  aria-hidden="true"
                                  className="transition-transform group-data-panel-open:rotate-90"
                                />
                              </span>
                            </span>
                          </CollapsibleTrigger>
                          <CollapsibleContent className="bg-background p-5">
                            <Card size="sm" className="shadow-none">
                              <CardHeader>
                                <CardTitle>Safeguards</CardTitle>
                                <CardDescription>
                                  Override rate limits or circuit breaker settings only when this
                                  route needs its own protection.
                                </CardDescription>
                              </CardHeader>
                              <CardContent className="flex flex-col gap-5">
                                {currentConnection ? (
                                  <div className="grid gap-3 md:grid-cols-2">
                                    <div
                                      className={cn(
                                        "rounded-lg border p-4",
                                        currentConnection.rateLimitStatus.blocked
                                          ? "border-destructive/20 bg-destructive/10"
                                          : "bg-muted/20",
                                      )}
                                    >
                                      <div className="flex items-center justify-between gap-3">
                                        <div className="text-sm font-medium">Rate limit status</div>
                                        <Badge
                                          variant={
                                            currentConnection.rateLimitStatus.blocked
                                              ? "destructive"
                                              : hasCustomRateLimits
                                                ? "secondary"
                                                : "outline"
                                          }
                                        >
                                          {currentConnection.rateLimitStatus.blocked
                                            ? "Blocked"
                                            : hasCustomRateLimits
                                              ? "Custom"
                                              : "Inherited"}
                                        </Badge>
                                      </div>
                                      <p className="mt-2 text-sm text-muted-foreground">
                                        {currentConnection.rateLimitStatus.blocked
                                          ? "This route is currently blocked by an active rate limit window."
                                          : hasCustomRateLimits
                                            ? "Custom rate limits are configured for this route."
                                            : "This route is inheriting the global rate limits."}
                                      </p>
                                    </div>
                                    <div className="rounded-lg border bg-muted/20 p-4">
                                      <div className="flex items-center justify-between gap-3">
                                        <div className="text-sm font-medium">Circuit breaker</div>
                                        <Badge
                                          variant={
                                            currentConnection.circuitBreakerStatus.enabled
                                              ? "secondary"
                                              : "outline"
                                          }
                                        >
                                          {currentConnection.circuitBreakerStatus.enabled
                                            ? "Tracking"
                                            : "Inactive"}
                                        </Badge>
                                      </div>
                                      <p className="mt-2 text-sm text-muted-foreground">
                                        {currentConnection.circuitBreakerStatus.enabled
                                          ? `Tracking ${formatAdminNumber(currentConnection.circuitBreakerStatus.recentFailures)} recent failure${currentConnection.circuitBreakerStatus.recentFailures === 1 ? "" : "s"}.`
                                          : "Circuit breaker protection is currently inactive for this route."}
                                      </p>
                                      {currentConnection.circuitBreakerStatus.blacklistedUntil ? (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                          Blacklisted until{" "}
                                          {formatAdminDateTime(
                                            currentConnection.circuitBreakerStatus.blacklistedUntil,
                                          )}
                                          .
                                        </p>
                                      ) : null}
                                    </div>
                                  </div>
                                ) : null}

                                <div className="flex flex-col gap-3">
                                  <div className="flex flex-col gap-1">
                                    <h4 className="text-sm font-medium">Rate limits</h4>
                                    <p className="text-sm text-muted-foreground">
                                      Leave fields blank to inherit the global limits.
                                    </p>
                                  </div>
                                  <FieldGroup className="grid gap-4 md:grid-cols-3">
                                    <Field>
                                      <FieldLabel htmlFor="rate-minute">Per minute</FieldLabel>
                                      <Input
                                        id="rate-minute"
                                        type="number"
                                        min="0"
                                        value={draft.rateLimits.minute}
                                        onChange={(event) => {
                                          setDraft((currentDraft) =>
                                            currentDraft
                                              ? {
                                                  ...currentDraft,
                                                  rateLimits: {
                                                    ...currentDraft.rateLimits,
                                                    minute: event.target.value,
                                                  },
                                                }
                                              : currentDraft,
                                          );
                                        }}
                                        placeholder="Inherit"
                                      />
                                    </Field>
                                    <Field>
                                      <FieldLabel htmlFor="rate-hour">Per hour</FieldLabel>
                                      <Input
                                        id="rate-hour"
                                        type="number"
                                        min="0"
                                        value={draft.rateLimits.hour}
                                        onChange={(event) => {
                                          setDraft((currentDraft) =>
                                            currentDraft
                                              ? {
                                                  ...currentDraft,
                                                  rateLimits: {
                                                    ...currentDraft.rateLimits,
                                                    hour: event.target.value,
                                                  },
                                                }
                                              : currentDraft,
                                          );
                                        }}
                                        placeholder="Inherit"
                                      />
                                    </Field>
                                    <Field>
                                      <FieldLabel htmlFor="rate-day">Per day</FieldLabel>
                                      <Input
                                        id="rate-day"
                                        type="number"
                                        min="0"
                                        value={draft.rateLimits.day}
                                        onChange={(event) => {
                                          setDraft((currentDraft) =>
                                            currentDraft
                                              ? {
                                                  ...currentDraft,
                                                  rateLimits: {
                                                    ...currentDraft.rateLimits,
                                                    day: event.target.value,
                                                  },
                                                }
                                              : currentDraft,
                                          );
                                        }}
                                        placeholder="Inherit"
                                      />
                                    </Field>
                                  </FieldGroup>
                                </div>

                                <Separator />

                                <div className="flex flex-col gap-3">
                                  <div className="flex flex-col gap-1">
                                    <h4 className="text-sm font-medium">Circuit breaker</h4>
                                    <p className="text-sm text-muted-foreground">
                                      Leave fields blank to inherit the global failure policy.
                                    </p>
                                  </div>
                                  <FieldGroup className="grid gap-4 md:grid-cols-3">
                                    <Field>
                                      <FieldLabel htmlFor="circuit-threshold">
                                        Failure threshold
                                      </FieldLabel>
                                      <Input
                                        id="circuit-threshold"
                                        type="number"
                                        min="0"
                                        value={draft.circuitBreaker.threshold}
                                        onChange={(event) => {
                                          setDraft((currentDraft) =>
                                            currentDraft
                                              ? {
                                                  ...currentDraft,
                                                  circuitBreaker: {
                                                    ...currentDraft.circuitBreaker,
                                                    threshold: event.target.value,
                                                  },
                                                }
                                              : currentDraft,
                                          );
                                        }}
                                        placeholder="Inherit"
                                      />
                                    </Field>
                                    <Field>
                                      <FieldLabel htmlFor="circuit-window">
                                        Window seconds
                                      </FieldLabel>
                                      <Input
                                        id="circuit-window"
                                        type="number"
                                        min="0"
                                        value={draft.circuitBreaker.window}
                                        onChange={(event) => {
                                          setDraft((currentDraft) =>
                                            currentDraft
                                              ? {
                                                  ...currentDraft,
                                                  circuitBreaker: {
                                                    ...currentDraft.circuitBreaker,
                                                    window: event.target.value,
                                                  },
                                                }
                                              : currentDraft,
                                          );
                                        }}
                                        placeholder="Inherit"
                                      />
                                    </Field>
                                    <Field>
                                      <FieldLabel htmlFor="circuit-cooldown">
                                        Cooldown seconds
                                      </FieldLabel>
                                      <Input
                                        id="circuit-cooldown"
                                        type="number"
                                        min="0"
                                        value={draft.circuitBreaker.cooldown}
                                        onChange={(event) => {
                                          setDraft((currentDraft) =>
                                            currentDraft
                                              ? {
                                                  ...currentDraft,
                                                  circuitBreaker: {
                                                    ...currentDraft.circuitBreaker,
                                                    cooldown: event.target.value,
                                                  },
                                                }
                                              : currentDraft,
                                          );
                                        }}
                                        placeholder="Inherit"
                                      />
                                    </Field>
                                  </FieldGroup>
                                </div>
                              </CardContent>
                            </Card>
                          </CollapsibleContent>
                        </Collapsible>

                        {supportsWebhooks ? (
                          <Collapsible
                            open={advancedSettingsOpen.webhooks}
                            onOpenChange={(open) => {
                              setAdvancedSettingsOpen((current) => ({
                                ...current,
                                webhooks: open,
                              }));
                            }}
                            render={<div className="contents" />}
                          >
                            <CollapsibleTrigger
                              render={
                                <button
                                  type="button"
                                  className={cn(
                                    "group flex w-full flex-col gap-3 bg-card p-4 text-left text-card-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50",
                                    advancedSettingsOpen.webhooks ? "bg-muted/40" : "",
                                  )}
                                />
                              }
                            >
                              <span className="flex items-start justify-between gap-3">
                                <span className="flex min-w-0 flex-col gap-1">
                                  <span className="text-sm font-medium">Webhooks</span>
                                  <span className="text-sm text-muted-foreground">
                                    {draft.webhookEnabled
                                      ? "Webhooks are enabled for this route."
                                      : "Webhooks are supported but disabled."}
                                  </span>
                                </span>
                                <span className="flex shrink-0 items-center gap-2">
                                  <Badge variant={draft.webhookEnabled ? "default" : "outline"}>
                                    {draft.webhookEnabled ? "Enabled" : "Off"}
                                  </Badge>
                                  <ArrowRight01Icon
                                    aria-hidden="true"
                                    className="transition-transform group-data-panel-open:rotate-90"
                                  />
                                </span>
                              </span>
                            </CollapsibleTrigger>
                            <CollapsibleContent className="bg-background p-5">
                              <Card size="sm" className="shadow-none">
                                <CardHeader>
                                  <CardTitle>Webhooks</CardTitle>
                                  <CardDescription>
                                    Configure the callback endpoint and the secret used to verify
                                    incoming webhook traffic.
                                  </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                                  <div className="flex flex-col gap-4">
                                    <Field
                                      orientation="horizontal"
                                      className="rounded-lg border bg-muted/20 p-4"
                                    >
                                      <FieldContent>
                                        <FieldLabel
                                          htmlFor="connection-webhooks-enabled"
                                          className="cursor-pointer"
                                        >
                                          Enable webhooks
                                        </FieldLabel>
                                        <FieldDescription>
                                          Expose the callback endpoint for this provider.
                                        </FieldDescription>
                                      </FieldContent>
                                      <Switch
                                        id="connection-webhooks-enabled"
                                        checked={draft.webhookEnabled}
                                        onCheckedChange={(checked) => {
                                          setDraft((currentDraft) =>
                                            currentDraft
                                              ? {
                                                  ...currentDraft,
                                                  webhookEnabled: checked === true,
                                                }
                                              : currentDraft,
                                          );
                                        }}
                                      />
                                    </Field>

                                    <div className="rounded-lg bg-muted/20 p-4 text-sm text-muted-foreground">
                                      {draft.webhookEnabled
                                        ? "This route can receive webhooks. Keep the endpoint and secret aligned with your provider settings."
                                        : "This profile supports webhooks, but they are currently disabled for this route."}
                                    </div>
                                  </div>

                                  <div className="flex flex-col gap-4">
                                    {currentConnection?.webhookUrl ? (
                                      <Field className="gap-2">
                                        <FieldLabel>Webhook endpoint</FieldLabel>
                                        <code className="block rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                          {currentConnection.webhookUrl}
                                        </code>
                                      </Field>
                                    ) : null}

                                    <div className="flex flex-col gap-3 rounded-lg border bg-muted/20 p-4 shadow-xs">
                                      <div className="flex items-center justify-between gap-3">
                                        <FieldLabel
                                          htmlFor={
                                            draft.webhookSecretAction !== "clear" ||
                                            !webhookSecretConfigured
                                              ? "connection-webhook-secret"
                                              : undefined
                                          }
                                        >
                                          Webhook secret
                                        </FieldLabel>
                                        {webhookSecretConfigured ? (
                                          draft.webhookSecretAction === "replace" ? (
                                            <Button
                                              type="button"
                                              size="xs"
                                              variant="ghost"
                                              onClick={() => {
                                                setDraft((currentDraft) =>
                                                  currentDraft
                                                    ? {
                                                        ...currentDraft,
                                                        webhookSecretAction: "keep",
                                                        webhookSecret: "",
                                                      }
                                                    : currentDraft,
                                                );
                                              }}
                                            >
                                              Cancel
                                            </Button>
                                          ) : draft.webhookSecretAction === "clear" ? (
                                            <Button
                                              type="button"
                                              size="xs"
                                              variant="outline"
                                              onClick={() => {
                                                setDraft((currentDraft) =>
                                                  currentDraft
                                                    ? {
                                                        ...currentDraft,
                                                        webhookSecretAction: "keep",
                                                        webhookSecret: "",
                                                      }
                                                    : currentDraft,
                                                );
                                              }}
                                            >
                                              Undo
                                            </Button>
                                          ) : (
                                            <div className="flex flex-wrap gap-1">
                                              <Button
                                                type="button"
                                                size="xs"
                                                variant="outline"
                                                onClick={() => {
                                                  setDraft((currentDraft) =>
                                                    currentDraft
                                                      ? {
                                                          ...currentDraft,
                                                          webhookSecretAction: "replace",
                                                          webhookSecret: "",
                                                        }
                                                      : currentDraft,
                                                  );
                                                }}
                                              >
                                                Replace
                                              </Button>
                                              <Button
                                                type="button"
                                                size="xs"
                                                variant="destructive"
                                                onClick={() => {
                                                  setDraft((currentDraft) =>
                                                    currentDraft
                                                      ? {
                                                          ...currentDraft,
                                                          webhookSecretAction: "clear",
                                                          webhookSecret: "",
                                                        }
                                                      : currentDraft,
                                                  );
                                                }}
                                              >
                                                Clear
                                              </Button>
                                            </div>
                                          )
                                        ) : null}
                                      </div>

                                      {draft.webhookSecretAction === "replace" ||
                                      !webhookSecretConfigured ? (
                                        <Input
                                          id="connection-webhook-secret"
                                          type="password"
                                          value={draft.webhookSecret}
                                          onChange={(event) => {
                                            setDraft((currentDraft) =>
                                              currentDraft
                                                ? {
                                                    ...currentDraft,
                                                    webhookSecret: event.target.value,
                                                  }
                                                : currentDraft,
                                            );
                                          }}
                                          placeholder={
                                            webhookSecretConfigured
                                              ? "Enter a new webhook secret"
                                              : "Enter the webhook secret"
                                          }
                                        />
                                      ) : draft.webhookSecretAction === "clear" ? (
                                        <div className="rounded-md border border-destructive/20 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                          The stored webhook secret will be removed when you save.
                                        </div>
                                      ) : (
                                        <Input
                                          id="connection-webhook-secret"
                                          type="password"
                                          value="********"
                                          disabled
                                        />
                                      )}
                                    </div>
                                  </div>
                                </CardContent>
                              </Card>
                            </CollapsibleContent>
                          </Collapsible>
                        ) : (
                          <div className="bg-card p-4 text-card-foreground">
                            <span className="flex items-start justify-between gap-3">
                              <span className="flex min-w-0 flex-col gap-1">
                                <span className="text-sm font-medium">Webhooks</span>
                                <span className="text-sm text-muted-foreground">
                                  Webhooks are not supported by this provider profile.
                                </span>
                              </span>
                              <Badge variant="secondary">Unavailable</Badge>
                            </span>
                          </div>
                        )}
                      </div>
                    </CardContent>
                  </Card>
                </section>

                <div className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                  <p className="text-sm text-muted-foreground">
                    {draft.id
                      ? "Changes are applied when you save the route."
                      : "Create the route to persist credentials, health state, and webhook details."}
                  </p>
                </div>
              </>
            ) : null}
          </div>
        )}
      </div>
    </div>
  );
}
