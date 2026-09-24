"use client";

import * as React from "react";

import "@twinkleplop/theme-github";

import { MailLogConnectionHoverCard } from "@/components/mail-log-connection-hover-card";
import type { MailLogTableRow } from "@/components/mail-log-table-types";
import { formatAddressList } from "@/components/mail-log-table-types";
import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { formatAdminDateTime, titleCase } from "@/lib/admin-format";
import { getLogStatusVariant } from "@/lib/admin-log-helpers";
import { highlightHtmlCode } from "@/lib/admin-twinkleplop";
import { cn } from "@/lib/utils";
import RefreshIcon from "~icons/tabler/refresh";

type MailLogDetailsDialogProps = {
  log: MailLogTableRow | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onResend: (log: MailLogTableRow) => void;
};

function useHighlightedHtml(code: string | null) {
  const [highlightedCode, setHighlightedCode] = React.useState<string | null>(null);

  React.useEffect(() => {
    let active = true;

    if (!code) {
      setHighlightedCode(null);
      return;
    }

    void highlightHtmlCode(code)
      .then((html) => {
        if (active) {
          setHighlightedCode(html);
        }
      })
      .catch(() => {
        if (active) {
          setHighlightedCode(null);
        }
      });

    return () => {
      active = false;
    };
  }, [code]);

  return highlightedCode;
}

function buildHtmlPreviewDocument(html: string): string {
  return `<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      :root { color-scheme: light; }
      * { box-sizing: border-box; }
      body {
        margin: 0;
        padding: 16px;
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        font-size: 14px;
        line-height: 1.6;
        color: #111827;
        background: #ffffff;
      }
      img, table { max-width: 100%; }
      pre { white-space: pre-wrap; word-break: break-word; }
    </style>
  </head>
  <body>${html}</body>
</html>`;
}

function MetaRow({
  label,
  value,
  className,
  labelClassName,
}: {
  label: string;
  value: React.ReactNode;
  className?: string;
  labelClassName?: string;
}) {
  return (
    <div
      className={cn(
        "grid grid-cols-[88px_minmax(0,1fr)] gap-3 text-sm",
        className,
      )}
    >
      <dt className={cn("text-[11px] uppercase tracking-wide text-muted-foreground", labelClassName)}>
        {label}
      </dt>
      <dd className="min-w-0 leading-5 break-words">{value}</dd>
    </div>
  );
}

function BadgeWithTooltip({
  tooltip,
  variant = "outline",
  children,
}: {
  tooltip: string;
  variant?: React.ComponentProps<typeof Badge>["variant"];
  children: React.ReactNode;
}) {
  return (
    <Tooltip>
      <TooltipTrigger render={<Badge variant={variant} />}>{children}</TooltipTrigger>
      <TooltipContent>{tooltip}</TooltipContent>
    </Tooltip>
  );
}

type DetailTooltipEntry = {
  key: string;
  label: string;
  value: string;
};

function DetailValueWithTooltip({
  value,
  entries,
}: {
  value: string;
  entries: DetailTooltipEntry[];
}) {
  if (entries.length === 0) {
    return value;
  }

  return (
    <Tooltip>
      <TooltipTrigger
        render={<span className="cursor-help underline decoration-dotted underline-offset-2" />}
      >
        {value}
      </TooltipTrigger>
      <TooltipContent className="max-w-sm">
        <div className="flex flex-col gap-1.5">
          {entries.map((entry) => (
            <div key={entry.key} className="grid grid-cols-[auto_1fr] gap-2">
              <span className="opacity-70">{entry.label}</span>
              <span>{entry.value}</span>
            </div>
          ))}
        </div>
      </TooltipContent>
    </Tooltip>
  );
}

function getMailLogDateEntries(log: MailLogTableRow): DetailTooltipEntry[] {
  const dateEntries: DetailTooltipEntry[] = [
    { key: "sent", label: "Sent", value: formatAdminDateTime(log.sentAt) },
    { key: "queued", label: "Queued", value: formatAdminDateTime(log.queuedAt) },
    { key: "created", label: "Created", value: formatAdminDateTime(log.createdAt) },
    { key: "updated", label: "Updated", value: formatAdminDateTime(log.updatedAt) },
  ];

  return dateEntries.filter((entry) => entry.value !== "-");
}

function buildRecipientTooltipEntries(
  entries: Array<{
    key: string;
    label: string;
    addresses: string[];
  }>,
): DetailTooltipEntry[] {
  return entries.map((entry) => ({
    key: entry.key,
    label: entry.label,
    value: formatAddressList(entry.addresses),
  }));
}

function DateValueWithTooltip({ log }: { log: MailLogTableRow }) {
  const dateEntries = getMailLogDateEntries(log);
  const primaryValue = formatAdminDateTime(log.dateTime);
  const primaryDate =
    dateEntries.find((entry) => entry.value === primaryValue) ?? dateEntries[0] ?? null;
  const secondaryDates = primaryDate
    ? dateEntries.filter((entry) => entry.key !== primaryDate.key)
    : [];

  if (primaryDate === null) {
    return primaryValue;
  }

  return <DetailValueWithTooltip value={primaryValue} entries={secondaryDates} />;
}

function ToValueWithTooltip({ log }: { log: MailLogTableRow }) {
  return (
    <DetailValueWithTooltip
      value={formatAddressList(log.toAddresses)}
      entries={buildRecipientTooltipEntries([
        { key: "cc", label: "CC", addresses: log.ccAddresses },
        { key: "bcc", label: "BCC", addresses: log.bccAddresses },
      ])}
    />
  );
}

function FromValueWithTooltip({ log }: { log: MailLogTableRow }) {
  return (
    <DetailValueWithTooltip
      value={formatAddressList(log.fromAddresses)}
      entries={buildRecipientTooltipEntries([
        { key: "reply-to", label: "Reply-To", addresses: log.replyToAddresses },
      ])}
    />
  );
}

function MiscValueWithTooltip({ log }: { log: MailLogTableRow }) {
  return (
    <DetailValueWithTooltip
      value="View details"
      entries={[
        { key: "source", label: "Source", value: titleCase(log.source) },
        {
          key: "transport-message",
          label: "Transport Message",
          value: log.transportMessageId ?? "-",
        },
      ]}
    />
  );
}

function ConnectionValue({ log }: { log: MailLogTableRow }) {
  if (log.finalConnectionId === null || log.connectionName === null) {
    return null;
  }

  return (
    <MailLogConnectionHoverCard
      connectionId={log.finalConnectionId}
      connectionName={log.connectionName}
      profileKey={log.connectionProfileKey ?? ""}
    />
  );
}

export function MailLogDetailsDialog({
  log,
  open,
  onOpenChange,
  onResend,
}: MailLogDetailsDialogProps) {
  const highlightedHtml = useHighlightedHtml(log?.htmlBody ?? null);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="min-h-0 max-h-[min(calc(100dvh-3rem),48rem)] w-[min(calc(100vw-var(--wp-admin--sidebar-width)-2rem),72rem)] max-w-[min(calc(100vw-var(--wp-admin--sidebar-width)-2rem),72rem)] gap-0 overflow-hidden p-0 sm:max-w-[min(calc(100vw-var(--wp-admin--sidebar-width)-2rem),72rem)]">
        {log ? (
          <>
            <DialogHeader className="gap-4 border-b px-6 py-5 pr-14">
              <div className="flex flex-wrap items-center gap-3">
                <Tooltip>
                  <TooltipTrigger
                    render={
                      <span className="cursor-help font-heading text-xl font-medium text-muted-foreground/80" />
                    }
                  >
                    #{log.id}
                  </TooltipTrigger>
                  <TooltipContent>Email log identifier</TooltipContent>
                </Tooltip>
                <DialogTitle className="text-xl">{log.subject || "(No subject)"}</DialogTitle>
                <BadgeWithTooltip
                  variant={getLogStatusVariant(log.status)}
                  tooltip="Current delivery state"
                >
                  {titleCase(log.status)}
                </BadgeWithTooltip>
                <Tooltip>
                  <TooltipTrigger
                    render={
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Resend email"
                        onClick={() => onResend(log)}
                      />
                    }
                  >
                    <RefreshIcon />
                  </TooltipTrigger>
                  <TooltipContent>Resend email</TooltipContent>
                </Tooltip>
              </div>
            </DialogHeader>

            <div className="min-h-0 flex-1 overflow-y-auto">
              <div className="flex flex-col gap-5 px-6 py-6">
                <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
                  <section className="flex min-w-0 flex-col gap-4">
                    <div>
                      <h3 className="text-sm font-semibold">Details</h3>
                    </div>
                    <dl className="flex flex-col gap-3">
                      <MetaRow
                        label="Connection"
                        value={<ConnectionValue log={log} />}
                        className="items-start"
                        labelClassName="self-center"
                      />
                      <MetaRow label="Date" value={<DateValueWithTooltip log={log} />} />
                      <MetaRow label="Misc" value={<MiscValueWithTooltip log={log} />} />
                    </dl>
                  </section>

                  <section className="flex min-w-0 flex-col gap-4">
                    <div>
                      <h3 className="text-sm font-semibold">Recipients</h3>
                    </div>
                    <dl className="flex flex-col gap-3">
                      <MetaRow label="To" value={<ToValueWithTooltip log={log} />} />
                      <MetaRow label="From" value={<FromValueWithTooltip log={log} />} />
                    </dl>
                  </section>
                </div>

                {log.lastError ? (
                  <div className="rounded-md border border-destructive/15 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                    {log.lastError}
                  </div>
                ) : null}

                <Separator />

                <section className="flex flex-col gap-4">
                  <div>
                    <h3 className="text-sm font-semibold">Message</h3>
                  </div>
                  <Tabs defaultValue={log.textBody ? "text" : "html-rendered"} className="gap-4">
                    <TabsList>
                      <TabsTrigger value="text">Text</TabsTrigger>
                      <TabsTrigger value="html-rendered">HTML</TabsTrigger>
                      {log.htmlBody ? <TabsTrigger value="html-raw">Raw HTML</TabsTrigger> : null}
                    </TabsList>

                    <TabsContent value="text" className="mt-0">
                      {log.textBody ? (
                        <div className="max-h-[min(36vh,22rem)] overflow-y-auto overscroll-contain rounded-md border bg-muted/20 px-4 py-4 text-sm whitespace-pre-wrap break-words">
                          {log.textBody}
                        </div>
                      ) : (
                        <div className="rounded-md border bg-muted/20 px-4 py-4 text-sm text-muted-foreground">
                          No text body stored.
                        </div>
                      )}
                    </TabsContent>

                    <TabsContent value="html-rendered" className="mt-0">
                      {log.htmlBody ? (
                        <iframe
                          title={`Email log HTML preview ${log.id}`}
                          srcDoc={buildHtmlPreviewDocument(log.htmlBody)}
                          sandbox=""
                          className="h-[36vh] min-h-[260px] max-h-[22rem] w-full rounded-md border bg-white"
                        />
                      ) : (
                        <div className="rounded-md border bg-muted/20 px-4 py-4 text-sm text-muted-foreground">
                          No HTML body stored.
                        </div>
                      )}
                    </TabsContent>

                    {log.htmlBody ? (
                      <TabsContent value="html-raw" className="mt-0">
                        {highlightedHtml ? (
                          <div
                            className="twinkleplop-wrapper overflow-x-auto rounded-md border bg-muted/10 [&_pre]:m-0 [&_pre]:inline-block [&_pre]:min-w-full [&_pre]:align-top [&_pre]:bg-transparent [&_pre]:px-4 [&_pre]:py-4"
                            dangerouslySetInnerHTML={{ __html: highlightedHtml }}
                          />
                        ) : (
                          <pre className="min-h-[280px] overflow-x-auto rounded-md border bg-muted/20 px-4 py-4 text-xs whitespace-pre-wrap break-words">
                            {log.htmlBody}
                          </pre>
                        )}
                      </TabsContent>
                    ) : null}
                  </Tabs>
                </section>
              </div>
            </div>
          </>
        ) : null}
      </DialogContent>
    </Dialog>
  );
}

export default MailLogDetailsDialog;
