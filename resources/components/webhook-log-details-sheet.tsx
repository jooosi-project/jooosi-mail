"use client";

import * as React from "react";

import { buildAdminHashHref } from "@/admin/routes";
import type { WebhookLogTableRow } from "@/components/webhook-log-table-types";
import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { formatAdminDateTime, titleCase } from "@/lib/admin-format";
import { highlightCode } from "@/lib/admin-shiki";
import { getWebhookEventVariant } from "@/lib/admin-log-helpers";
import DatabaseIcon from "~icons/tabler/database";

type WebhookLogDetailsSheetProps = {
  event: WebhookLogTableRow | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

function MetaRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="grid grid-cols-[88px_minmax(0,1fr)] gap-3 text-sm">
      <dt className="text-[11px] uppercase tracking-wide text-muted-foreground">{label}</dt>
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

function ConnectionValue({ event }: { event: WebhookLogTableRow }) {
  const openConnection = React.useCallback(() => {
    if (typeof window === "undefined" || event.connectionId === null) {
      return;
    }

    window.location.hash = buildAdminHashHref("/connections", {
      id: event.connectionId,
    }).slice(1);
  }, [event.connectionId]);

  return (
    <Button
      variant="secondary"
      size="xs"
      disabled={event.connectionId === null}
      onClick={openConnection}
    >
      <DatabaseIcon data-icon="inline-start" />
      {event.connectionLabel}
    </Button>
  );
}

function useHighlightedJson(code: string | null) {
  const [highlightedCode, setHighlightedCode] = React.useState<string | null>(null);

  React.useEffect(() => {
    let active = true;

    if (!code) {
      setHighlightedCode(null);
      return;
    }

    void highlightCode(code, "json")
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

export function WebhookLogDetailsSheet({ event, open, onOpenChange }: WebhookLogDetailsSheetProps) {
  const highlightedJson = useHighlightedJson(event?.payloadJson ?? null);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full gap-0 overflow-hidden p-0 sm:max-w-4xl">
        {event ? (
          <>
            <SheetHeader className="gap-4 border-b px-6 py-5 pr-14">
              <div className="flex flex-wrap items-center gap-2">
                <BadgeWithTooltip
                  variant={getWebhookEventVariant(event.eventType)}
                  tooltip="Webhook event type"
                >
                  {titleCase(event.eventType)}
                </BadgeWithTooltip>
                <BadgeWithTooltip variant="outline" tooltip="Selected delivery connection">
                  {event.connectionLabel}
                </BadgeWithTooltip>
                <BadgeWithTooltip variant="outline" tooltip="Webhook event identifier">
                  #{event.id}
                </BadgeWithTooltip>
              </div>
              <SheetTitle className="text-xl">{titleCase(event.eventType)}</SheetTitle>
              <SheetDescription>
                Webhook event for {event.connectionLabel} on {formatAdminDateTime(event.dateTime)}
              </SheetDescription>
            </SheetHeader>

            <div className="flex-1 overflow-y-auto">
              <div className="flex flex-col gap-5 px-6 py-6">
                <section className="flex flex-col gap-4">
                  <div>
                    <h3 className="text-sm font-semibold">Details</h3>
                  </div>
                  <dl className="flex flex-col gap-3">
                    <MetaRow label="ID" value={`#${event.id}`} />
                    <MetaRow label="Event" value={titleCase(event.eventType)} />
                    <MetaRow label="Connection" value={<ConnectionValue event={event} />} />
                    <MetaRow label="Mail" value={event.mailLogLabel} />
                    <MetaRow label="Transport Message" value={event.transportMessageId ?? "-"} />
                    <MetaRow label="Provider Event" value={event.providerEventId ?? "-"} />
                    <MetaRow label="Occurred" value={formatAdminDateTime(event.occurredAt)} />
                    <MetaRow label="Created" value={formatAdminDateTime(event.createdAt)} />
                  </dl>
                </section>

                <Separator />

                <section className="flex flex-col gap-4">
                  <div>
                    <h3 className="text-sm font-semibold">Payload</h3>
                    <p className="text-sm text-muted-foreground">
                      Raw webhook payload captured after the event was normalized.
                    </p>
                  </div>
                  {event.payloadJson ? (
                    highlightedJson ? (
                      <div
                        className="shiki-wrapper overflow-x-auto rounded-md border bg-muted/10"
                        dangerouslySetInnerHTML={{ __html: highlightedJson }}
                      />
                    ) : (
                      <pre className="min-h-[320px] overflow-x-auto rounded-md border bg-muted/20 px-4 py-4 text-xs whitespace-pre-wrap break-words">
                        {event.payloadJson}
                      </pre>
                    )
                  ) : (
                    <div className="rounded-md border bg-muted/20 px-4 py-4 text-sm text-muted-foreground">
                      No payload stored.
                    </div>
                  )}
                </section>
              </div>
            </div>
          </>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}

export default WebhookLogDetailsSheet;
