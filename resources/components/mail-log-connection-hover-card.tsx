"use client";

import * as React from "react";

import { ProfileBrandIcon } from "@/components/profile-brand-icon";
import { Badge } from "@/components/reui/badge";
import {
  Popover,
  PopoverContent,
  PopoverDescription,
  PopoverHeader,
  PopoverTitle,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Spinner } from "@/components/ui/spinner";
import { getConnection, type AdminConnectionDetail } from "@/lib/admin-api";
import {
  getConnectionOperationalLabel,
  getConnectionOperationalStatus,
  getConnectionStatusVariant,
} from "@/lib/admin-connections";

type MailLogConnectionHoverCardProps = {
  connectionId: number;
  connectionName: string;
  profileKey: string;
  emphasis?: "default" | "prominent";
};

function getConfigurationEntries(
  connection: AdminConnectionDetail,
): Array<{ key: string; label: string; value: string }> {
  return connection.configurationFields.flatMap((field) => {
    if (field.secret) {
      return field.configured === undefined
        ? []
        : [{
            key: field.name,
            label: field.label,
            value: field.configured ? "Configured" : "Not configured",
          }];
    }

    if (field.value === undefined || field.value === null || field.value === "") {
      return [];
    }

    return [{ key: field.name, label: field.label, value: String(field.value) }];
  });
}

function ConnectionDetailRow({
  label,
  value,
}: {
  label: string;
  value: React.ReactNode;
}) {
  return (
    <div className="bg-card p-3">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="mt-1 break-words font-medium">{value}</dd>
    </div>
  );
}

export function MailLogConnectionHoverCard({
  connectionId,
  connectionName,
  profileKey,
  emphasis = "default",
}: MailLogConnectionHoverCardProps) {
  const [connection, setConnection] = React.useState<AdminConnectionDetail | null>(null);
  const [loading, setLoading] = React.useState(false);
  const [loadFailed, setLoadFailed] = React.useState(false);

  const loadConnection = React.useCallback(() => {
    if (connection !== null || loading) {
      return;
    }

    setLoading(true);
    setLoadFailed(false);

    void getConnection(connectionId)
      .then(({ connection: loadedConnection }) => setConnection(loadedConnection))
      .catch(() => setLoadFailed(true))
      .finally(() => setLoading(false));
  }, [connection, connectionId, loading]);

  const configurationEntries = connection ? getConfigurationEntries(connection) : [];
  const senderLabel = connection
    ? [connection.sender.name, connection.sender.email].filter(Boolean).join(" · ")
    : "";

  return (
    <Popover onOpenChange={(open) => open && loadConnection()}>
      <PopoverTrigger
        openOnHover
        delay={150}
        closeDelay={120}
        render={
          <button
            type="button"
            className={
              emphasis === "prominent"
                ? "inline-flex min-w-0 max-w-full items-center gap-3 rounded-sm text-left text-lg font-medium underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                : "inline-flex min-w-0 max-w-56 items-center gap-2 rounded-sm text-left font-medium underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
            }
          />
        }
      >
        <ProfileBrandIcon
          profileKey={profileKey}
          label={connectionName}
          size={emphasis === "prominent" ? "default" : "sm"}
        />
        <span className="min-w-0 truncate">{connectionName}</span>
      </PopoverTrigger>
      <PopoverContent
        side="right"
        align="start"
        sideOffset={8}
        className="max-h-[min(32rem,calc(100vh-2rem))] w-96 max-w-[calc(100vw-2rem)] overflow-y-auto overflow-x-hidden p-0"
      >
        <PopoverHeader className="border-b p-4">
          <div className="flex items-start gap-3">
            <ProfileBrandIcon
              profileKey={connection?.profile.key ?? profileKey}
              label={connection?.profile.label ?? connectionName}
            />
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <PopoverTitle>{connection?.name ?? connectionName}</PopoverTitle>
                {connection ? (
                  <Badge
                    variant={getConnectionStatusVariant(getConnectionOperationalStatus(connection))}
                  >
                    {getConnectionOperationalLabel(connection)}
                  </Badge>
                ) : null}
                {connection?.default ? <Badge variant="secondary">Default</Badge> : null}
              </div>
              <PopoverDescription className="mt-1 leading-relaxed">
                {connection?.profile.label ?? "Connection details"}
              </PopoverDescription>
            </div>
          </div>
        </PopoverHeader>

        {connection ? (
          <dl className="grid grid-cols-2 gap-px bg-border text-sm">
            <ConnectionDetailRow label="Health" value={`${connection.healthScore}/100`} />
            <ConnectionDetailRow
              label="Priority · Weight"
              value={`${connection.priority} · ${connection.weight}`}
            />
            {senderLabel ? <ConnectionDetailRow label="Sender" value={senderLabel} /> : null}
            {connection.profile.supportsWebhooks ? (
              <ConnectionDetailRow
                label="Webhooks"
                value={connection.webhookEnabled ? "Enabled" : "Disabled"}
              />
            ) : null}
            {connection.unavailableReasons.length > 0 ? (
              <div className="col-span-2 bg-card p-3">
                <dt className="text-xs text-muted-foreground">Availability</dt>
                <dd className="mt-1 font-medium">{connection.unavailableReasons.join(", ")}</dd>
              </div>
            ) : null}
            {connection.dsnOverrideConfigured ? (
              <ConnectionDetailRow label="Custom transport" value="Configured" />
            ) : null}
            {configurationEntries.length > 0 ? (
              <div className="col-span-2 bg-card p-3">
                <dt className="text-xs text-muted-foreground">Configuration</dt>
                <dd className="mt-2 flex flex-col gap-2">
                  {configurationEntries.map((entry) => (
                    <div
                      key={entry.key}
                      className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-3"
                    >
                      <span className="text-muted-foreground">{entry.label}</span>
                      <span className="break-words text-right font-medium">{entry.value}</span>
                    </div>
                  ))}
                </dd>
              </div>
            ) : null}
          </dl>
        ) : loading ? (
          <div className="flex items-center gap-2 p-4 text-sm text-muted-foreground">
            <Spinner />
            Loading connection details…
          </div>
        ) : loadFailed ? (
          <p className="p-4 text-sm text-muted-foreground">Connection details could not be loaded.</p>
        ) : null}
      </PopoverContent>
    </Popover>
  );
}
