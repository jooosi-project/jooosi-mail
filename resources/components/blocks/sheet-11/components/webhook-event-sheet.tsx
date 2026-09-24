"use client";

import * as React from "react";

import "@twinkleplop/theme-github";

import { MailLogConnectionHoverCard } from "@/components/mail-log-connection-hover-card";
import type { WebhookLogTableRow } from "@/components/webhook-log-table-types";
import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { tokenizeJsonCode, type TwinkleplopToken } from "@/lib/admin-twinkleplop";
import { cn } from "@/lib/utils";
import { formatAdminDateTime, titleCase } from "@/lib/admin-format";
import { getWebhookEventVariant } from "@/lib/admin-log-helpers";
import Cancel01Icon from "~icons/hugeicons/cancel-01";

type WebhookEventSheetProps = {
  event: WebhookLogTableRow | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

function Section({ label, children }: { label?: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-3 px-5 py-4">
      {label ? (
        <div className="flex items-center justify-between gap-2">
          <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
            {label}
          </h3>
        </div>
      ) : null}
      {children}
    </div>
  );
}

function FactRow({ label, value, mono = false }: { label: string; value: React.ReactNode; mono?: boolean }) {
  return (
    <div className="contents">
      <dt className="text-sm text-muted-foreground">{label}</dt>
      <dd
        className={cn(
          "min-w-0 text-sm text-foreground",
          mono && "font-mono text-xs break-all",
        )}
      >
        {value}
      </dd>
    </div>
  );
}

function ConnectionHero({ event }: { event: WebhookLogTableRow }) {
  return (
    <div className="flex min-w-0 items-center justify-between gap-3">
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
          Connection
        </span>
        {event.connectionId !== null && event.connectionName !== null ? (
          <MailLogConnectionHoverCard
            connectionId={event.connectionId}
            connectionName={event.connectionName}
            profileKey={event.connectionProfileKey ?? ""}
          />
        ) : (
          <span className="text-sm text-muted-foreground">—</span>
        )}
      </div>
      <div className="shrink-0 text-right">
        <span className="text-xs text-muted-foreground">Occurred</span>
        <p className="text-xs text-muted-foreground tabular-nums">
          {formatAdminDateTime(event.dateTime)}
        </p>
      </div>
    </div>
  );
}

function useHighlightedPayload(payloadJson: string | null) {
  const [highlightedPayload, setHighlightedPayload] = React.useState<TwinkleplopToken[] | null>(null);

  React.useEffect(() => {
    let active = true;

    if (!payloadJson) {
      setHighlightedPayload(null);
      return;
    }

    setHighlightedPayload(null);
    void tokenizeJsonCode(payloadJson)
      .then((tokens) => {
        if (active) {
          setHighlightedPayload(tokens);
        }
      })
      .catch(() => {
        if (active) {
          setHighlightedPayload(null);
        }
      });

    return () => {
      active = false;
    };
  }, [payloadJson]);

  return highlightedPayload;
}

export function WebhookEventSheet({ event, open, onOpenChange }: WebhookEventSheetProps) {
  const highlightedPayload = useHighlightedPayload(event?.payloadJson ?? null);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        showCloseButton={false}
        initialFocus={false}
        className="bg-popover left-auto flex h-[calc(100svh-2rem)] w-[min(30rem,calc(100vw-2rem))] max-w-none flex-col gap-0 overflow-hidden rounded-xl border border-border/70 p-0 shadow-lg outline-none"
        style={{
          top: "calc(var(--wp-admin--admin-bar--height, 32px) + 1rem)",
          right: "1rem",
          bottom: "1rem",
          height: "calc(100svh - var(--wp-admin--admin-bar--height, 32px) - 2rem)",
          width: "min(30rem, calc(100vw - 2rem))",
          maxWidth: "none",
        }}
      >
        {event ? (
          <>
            <SheetHeader className="shrink-0 gap-0 border-b p-0">
              <div className="flex min-h-11 items-center justify-between gap-2 px-5">
                <span className="inline-flex items-center gap-2 font-mono text-xs text-muted-foreground">
                  WEBHOOK #{event.id}
                </span>
                <SheetClose
                  render={
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon-sm"
                      aria-label="Close webhook details"
                      className="shrink-0"
                    />
                  }
                >
                  <Cancel01Icon aria-hidden="true" />
                </SheetClose>
              </div>
              <div className="flex min-w-0 flex-col gap-2 px-5 pt-1 pb-4">
                <div className="flex min-w-0 items-center gap-2">
                  <SheetTitle className="min-w-0 flex-1 truncate text-lg font-semibold tracking-tight">
                    Webhook event
                  </SheetTitle>
                  <Badge variant={getWebhookEventVariant(event.eventType)}>
                    {titleCase(event.eventType)}
                  </Badge>
                </div>
                <SheetDescription className="text-xs text-muted-foreground">
                  Received {formatAdminDateTime(event.createdAt)}
                </SheetDescription>
              </div>
            </SheetHeader>

            <div className="min-h-0 flex-1">
              <ScrollArea className="h-full min-h-0">
                <Section>
                  <ConnectionHero event={event} />
                </Section>

                <Separator className="opacity-60" />

                <Section label="Definition">
                  <dl className="grid grid-cols-[7.5rem_minmax(0,1fr)] gap-x-4 gap-y-2.5">
                    <FactRow label="Mail" value={event.mailLogLabel} />
                    <FactRow
                      label="Transport message"
                      value={event.transportMessageId ?? "—"}
                      mono
                    />
                    <FactRow
                      label="Provider event"
                      value={event.providerEventId ?? "—"}
                      mono
                    />
                    <FactRow label="Created" value={formatAdminDateTime(event.createdAt)} />
                  </dl>
                </Section>

                <Separator className="opacity-60" />

                <Section label="Payload">
                  {event.payloadJson ? (
                    highlightedPayload ? (
                      <div
                        className="overflow-x-auto rounded-md border bg-muted/20"
                      >
                        <pre className="twinkleplop m-0 min-w-full bg-transparent p-4 font-mono text-xs leading-relaxed whitespace-pre-wrap break-words">
                          <code>
                            {highlightedPayload.map((token, index) => (
                              <span key={index} className={`tok ${token.type}`}>
                                {token.text}
                              </span>
                            ))}
                          </code>
                        </pre>
                      </div>
                    ) : (
                      <pre className="overflow-x-auto rounded-md border bg-muted/20 p-4 font-mono text-xs leading-relaxed whitespace-pre-wrap break-words">
                        {event.payloadJson}
                      </pre>
                    )
                  ) : (
                    <p className="rounded-md border bg-muted/20 p-4 text-sm text-muted-foreground">
                      No payload stored.
                    </p>
                  )}
                </Section>
              </ScrollArea>
            </div>
          </>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}

export default WebhookEventSheet;
