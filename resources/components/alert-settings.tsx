import * as React from "react";

import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardAction,
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
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { useAdminQuery } from "@/hooks/use-admin-query";
import {
  disconnectAlertChannel,
  getAlertSettings,
  saveAlertChannel,
  testAlertChannel,
  type AdminAlertChannel,
  type AdminAlertChannelSavePayload,
  type AdminAlertChannelStatus,
  type AdminAlertSettings,
} from "@/lib/admin-api";

type AlertChannelDraft = {
  enabled: boolean;
  secret: string;
  target: string;
};

type AlertChannelDefinition = {
  channel: AdminAlertChannel;
  title: string;
  summary: string;
  secretLabel: string;
  secretPlaceholder: string;
  secretDescription: string;
  targetLabel?: string;
  targetPlaceholder?: string;
  targetDescription?: string;
  helpLinkUrl?: string;
  helpLinkLabel?: string;
};

type AlertChannelCardProps = {
  definition: AlertChannelDefinition;
  status: AdminAlertChannelStatus;
  draft: AlertChannelDraft;
  busy: boolean;
  disabled: boolean;
  error: string | null;
  notice: string | null;
  onEnabledChange: (enabled: boolean) => void;
  onFieldChange: (field: "secret" | "target", value: string) => void;
  onSave: () => void;
  onTest: () => void;
  onDisconnect: () => void;
};

const CHANNEL_DEFINITIONS: AlertChannelDefinition[] = [
  {
    channel: "telegram",
    title: "Telegram",
    summary: "Use your own bot to receive alerts.",
    secretLabel: "Bot token",
    secretPlaceholder: "123456789:…",
    secretDescription: "Create a bot with BotFather and paste its token.",
    targetLabel: "Destination",
    targetPlaceholder: "Chat ID or @channel",
    targetDescription:
      "DM/private groups: numeric ID (/start first for DMs). Public groups/channels: @username; channel bot must be an admin. Personal @usernames won’t work.",
    helpLinkUrl: "https://t.me/BotFather",
    helpLinkLabel: "Create a bot",
  },
  {
    channel: "discord",
    title: "Discord",
    summary: "Post alerts to a Discord channel.",
    secretLabel: "Webhook URL",
    secretPlaceholder: "https://discord.com/api/webhooks/…",
    secretDescription: "Paste a webhook URL from the channel you want.",
    helpLinkUrl: "https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks",
    helpLinkLabel: "Webhook guide",
  },
  {
    channel: "slack",
    title: "Slack",
    summary: "Use your own Slack app to send alerts.",
    secretLabel: "Bot token",
    secretPlaceholder: "xoxb-…",
    secretDescription: "Paste the xoxb token from your installed Slack app.",
    targetLabel: "Channel ID",
    targetPlaceholder: "e.g. C0123456789",
    targetDescription: "Add your app to the channel, then enter its ID.",
    helpLinkUrl: "https://api.slack.com/authentication/quickstart",
    helpLinkLabel: "Slack app guide",
  },
];

function emptyDraft(): AlertChannelDraft {
  return { enabled: false, secret: "", target: "" };
}

function createDrafts(settings: AdminAlertSettings): Record<AdminAlertChannel, AlertChannelDraft> {
  return Object.fromEntries(
    Object.entries(settings.channels).map(([channel, status]) => [channel, {
      enabled: status.enabled,
      secret: "",
      target: status.target,
    }]),
  );
}

function AlertChannelCard({
  definition,
  status,
  draft,
  busy,
  disabled,
  error,
  notice,
  onEnabledChange,
  onFieldChange,
  onSave,
  onTest,
  onDisconnect,
}: AlertChannelCardProps) {
  const channel = definition.channel;
  const enabledId = `alert-${channel}-enabled`;
  const secretId = `alert-${channel}-secret`;
  const targetId = `alert-${channel}-target`;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{definition.title}</CardTitle>
        <CardDescription>{definition.summary}</CardDescription>
        <CardAction>
          <Badge variant={status.enabled ? "default" : "outline"}>
            {status.enabled ? "Enabled" : status.configured ? "Configured" : "Not configured"}
          </Badge>
        </CardAction>
      </CardHeader>
      <CardContent className="flex flex-col gap-5">
        <FieldGroup className="gap-4">
          <Field orientation="horizontal" className="gap-4 rounded-xl border bg-muted/30 p-4">
            <FieldContent className="gap-1">
              <FieldLabel htmlFor={enabledId}>Enable {definition.title} alerts</FieldLabel>
            </FieldContent>
            <Switch
              id={enabledId}
              checked={draft.enabled}
              disabled={disabled}
              onCheckedChange={onEnabledChange}
            />
          </Field>

          <Field className="gap-2">
            <FieldLabel htmlFor={secretId}>{definition.secretLabel}</FieldLabel>
            <Input
              id={secretId}
              type="password"
              autoComplete="new-password"
              value={draft.secret}
              disabled={disabled}
              placeholder={
                status.hasSavedCredential
                  ? "Enter a new value to replace the saved one"
                  : definition.secretPlaceholder
              }
              onChange={(event) => onFieldChange("secret", event.target.value)}
            />
            <FieldDescription>
              {status.hasSavedCredential
                ? "Leave blank to keep the saved value."
                : definition.secretDescription}
              {definition.helpLinkUrl && definition.helpLinkLabel ? (
                <>
                  {" "}
                  <a
                    href={definition.helpLinkUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="font-medium text-primary"
                  >
                    {definition.helpLinkLabel}
                  </a>
                </>
              ) : null}
            </FieldDescription>
          </Field>

          {definition.targetLabel ? (
            <Field className="gap-2">
              <FieldLabel htmlFor={targetId}>{definition.targetLabel}</FieldLabel>
              <Input
                id={targetId}
                value={draft.target}
                disabled={disabled}
                placeholder={definition.targetPlaceholder}
                onChange={(event) => onFieldChange("target", event.target.value)}
              />
              {definition.targetDescription ? (
                <FieldDescription>{definition.targetDescription}</FieldDescription>
              ) : null}
            </Field>
          ) : null}
        </FieldGroup>

        {error ? <p role="alert" className="text-sm text-destructive">{error}</p> : null}
        {notice ? <p role="status" className="text-sm text-emerald-700 dark:text-emerald-400">{notice}</p> : null}

        <div className="flex flex-wrap gap-2">
          <Button type="button" onClick={onSave} disabled={disabled}>
            {busy ? "Working…" : "Save settings"}
          </Button>
          <Button type="button" variant="outline" onClick={onTest} disabled={disabled || !status.configured}>
            Send test alert
          </Button>
          {status.hasSavedCredential || status.enabled || draft.target !== "" ? (
            <Button type="button" variant="outline" onClick={onDisconnect} disabled={disabled}>
              Disconnect
            </Button>
          ) : null}
        </div>
      </CardContent>
    </Card>
  );
}

export function AlertSettings() {
  const { data, setData, loading, error: loadError, refresh } = useAdminQuery(getAlertSettings);
  const [drafts, setDrafts] = React.useState<Record<AdminAlertChannel, AlertChannelDraft>>({});
  const [busyChannel, setBusyChannel] = React.useState<AdminAlertChannel | null>(null);
  const [errors, setErrors] = React.useState<Record<AdminAlertChannel, string | null>>({});
  const [notices, setNotices] = React.useState<Record<AdminAlertChannel, string | null>>({});
  const previousDataRef = React.useRef<AdminAlertSettings | null>(null);

  React.useEffect(() => {
    if (!data) {
      return;
    }

    const previousData = previousDataRef.current;

    if (!previousData) {
      setDrafts(createDrafts(data));
    } else {
      setDrafts((current) => {
        const next = { ...current };

        for (const channel of Object.keys(data.channels)) {
          const previous = previousData.channels[channel];
          const updated = data.channels[channel];

          if (
            !previous ||
            previous.enabled !== updated.enabled ||
            previous.configured !== updated.configured ||
            previous.target !== updated.target
          ) {
            next[channel] = {
              ...(current[channel] ?? emptyDraft()),
              enabled: updated.enabled,
              target: updated.target,
            };
          }
        }

        return next;
      });
    }

    previousDataRef.current = data;
  }, [data]);

  const updateDraft = (channel: AdminAlertChannel, update: Partial<AlertChannelDraft>) => {
    setDrafts((current) => ({
      ...current,
      [channel]: { ...(current[channel] ?? emptyDraft()), ...update },
    }));
  };

  const saveChannel = async (channel: AdminAlertChannel) => {
    const draft = drafts[channel] ?? emptyDraft();
    const payload: AdminAlertChannelSavePayload = {
      enabled: draft.enabled,
      secret: draft.secret,
      target: draft.target,
    };

    setBusyChannel(channel);
    setErrors((current) => ({ ...current, [channel]: null }));
    setNotices((current) => ({ ...current, [channel]: null }));

    try {
      const response = await saveAlertChannel(channel, payload);
      setData(response);
      setDrafts((current) => ({
        ...current,
        [channel]: { ...(current[channel] ?? emptyDraft()), secret: "" },
      }));
      const title = CHANNEL_DEFINITIONS.find((item) => item.channel === channel)?.title ?? "Channel";
      setNotices((current) => ({ ...current, [channel]: `${title} settings saved.` }));
    } catch (caughtError) {
      setErrors((current) => ({
        ...current,
        [channel]: caughtError instanceof Error ? caughtError.message : "The channel settings could not be saved.",
      }));
    } finally {
      setBusyChannel(null);
    }
  };

  const testChannel = async (channel: AdminAlertChannel) => {
    setBusyChannel(channel);
    setErrors((current) => ({ ...current, [channel]: null }));
    setNotices((current) => ({ ...current, [channel]: null }));

    try {
      const response = await testAlertChannel(channel);
      setNotices((current) => ({ ...current, [channel]: response.message }));
    } catch (caughtError) {
      setErrors((current) => ({
        ...current,
        [channel]: caughtError instanceof Error ? caughtError.message : "The test alert could not be sent.",
      }));
    } finally {
      setBusyChannel(null);
    }
  };

  const disconnectChannel = async (channel: AdminAlertChannel) => {
    setBusyChannel(channel);
    setErrors((current) => ({ ...current, [channel]: null }));
    setNotices((current) => ({ ...current, [channel]: null }));

    try {
      const response = await disconnectAlertChannel(channel);
      setData(response);
      setDrafts((current) => ({ ...current, [channel]: emptyDraft() }));
      setNotices((current) => ({ ...current, [channel]: "Disconnected and cleared saved credentials." }));
    } catch (caughtError) {
      setErrors((current) => ({
        ...current,
        [channel]: caughtError instanceof Error ? caughtError.message : "The channel could not be disconnected.",
      }));
    } finally {
      setBusyChannel(null);
    }
  };

  if (loading && !data) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-48" />
        <Skeleton className="h-48" />
      </div>
    );
  }

  if (!data) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Alerts unavailable</CardTitle>
          <CardDescription>{loadError ?? "Alert settings could not be loaded."}</CardDescription>
        </CardHeader>
        <CardContent>
          <Button type="button" onClick={() => void refresh()}>Try again</Button>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle>Delivery failure alerts</CardTitle>
          <CardDescription>
            Get an alert when an email still can’t be delivered after retries. Connect your own bot, webhook, or Slack app.
          </CardDescription>
        </CardHeader>
      </Card>

      {CHANNEL_DEFINITIONS.map((definition) => (
        <AlertChannelCard
          key={definition.channel}
          definition={definition}
          status={data.channels[definition.channel]}
          draft={drafts[definition.channel] ?? emptyDraft()}
          busy={busyChannel === definition.channel}
          disabled={busyChannel !== null}
          error={errors[definition.channel]}
          notice={notices[definition.channel]}
          onEnabledChange={(enabled) => updateDraft(definition.channel, { enabled })}
          onFieldChange={(field, value) => {
            if (field === "secret") {
              updateDraft(definition.channel, { secret: value });
            } else {
              updateDraft(definition.channel, { target: value });
            }
          }}
          onSave={() => void saveChannel(definition.channel)}
          onTest={() => void testChannel(definition.channel)}
          onDisconnect={() => void disconnectChannel(definition.channel)}
        />
      ))}
    </div>
  );
}
