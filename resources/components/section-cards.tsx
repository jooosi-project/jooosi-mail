import type { ReactNode } from "react";

import { Badge, type BadgeProps } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import type { AdminDashboardData } from "@/lib/admin-api";
import { formatAdminDateTime, formatAdminNumber } from "@/lib/admin-format";
import Clock01Icon from "~icons/hugeicons/clock-01";
import Database01Icon from "~icons/hugeicons/database-01";
import Mail01Icon from "~icons/hugeicons/mail-01";
import WebhookIcon from "~icons/hugeicons/webhook";

type SectionCardsProps = {
  summary: AdminDashboardData["summary"];
};

type DashboardMetric = {
  label: string;
  value: string;
  description: string;
  badge?: string;
  badgeVariant?: BadgeProps["variant"];
  icon: ReactNode;
};

function getConnectionDescription(summary: AdminDashboardData["summary"]): string {
  const activeConnections = `${formatAdminNumber(summary.activeConnections)} active`;

  if (summary.nextAvailableAt) {
    return `${activeConnections} · Next route opens ${formatAdminDateTime(summary.nextAvailableAt)}`;
  }

  return activeConnections;
}

export function SectionCards({ summary }: SectionCardsProps) {
  const metrics: DashboardMetric[] = [
    {
      label: "Messages",
      value: formatAdminNumber(summary.mailTotal),
      description: `${formatAdminNumber(summary.mailFailed)} failed · ${formatAdminNumber(summary.mailQueued)} queued · ${formatAdminNumber(summary.mailSent)} sent`,
      icon: <Mail01Icon />,
    },
    {
      label: "Connections",
      value: formatAdminNumber(summary.connectionsTotal),
      description: getConnectionDescription(summary),
      icon: <Database01Icon />,
    },
    {
      label: "Queue total",
      value: formatAdminNumber(
        summary.queuePendingReady +
          summary.queuePendingDeferred +
          summary.queueProcessing +
          summary.queueFailed +
          summary.queueCompleted,
      ),
      description: [
        `${formatAdminNumber(summary.queuePendingReady)} ready`,
        `${formatAdminNumber(summary.queuePendingDeferred)} deferred`,
        `${formatAdminNumber(summary.queueProcessing)} processing`,
        `${formatAdminNumber(summary.queueFailed)} failed`,
        `${formatAdminNumber(summary.queueCompleted)} completed`,
      ].join(" · "),
      badge:
        summary.queueStaleProcessing > 0
          ? `${formatAdminNumber(summary.queueStaleProcessing)} stale ${summary.queueStaleProcessing === 1 ? "claim" : "claims"}`
          : undefined,
      badgeVariant: "warning-light",
      icon: <Clock01Icon />,
    },
    {
      label: "Webhook events",
      value: formatAdminNumber(summary.webhookEvents),
      description: "Updates received from email providers.",
      icon: <WebhookIcon />,
    },
  ];

  return (
    <div className="px-4 lg:px-6">
      <Frame className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4" spacing="sm">
        {metrics.map((metric) => (
          <FramePanel key={metric.label} className="flex min-h-36 flex-col justify-between gap-5">
            <div className="flex items-start justify-between gap-3">
              <IconTile variant="frame" aria-hidden="true">
                {metric.icon}
              </IconTile>
              {metric.badge ? (
                <Badge variant={metric.badgeVariant} radius="full">
                  {metric.badge}
                </Badge>
              ) : null}
            </div>
            <div className="flex flex-col gap-1">
              <p className="text-sm font-medium text-muted-foreground">{metric.label}</p>
              <p className="text-3xl font-semibold tracking-tight tabular-nums">{metric.value}</p>
              <p className="text-xs text-muted-foreground">{metric.description}</p>
            </div>
          </FramePanel>
        ))}
      </Frame>
    </div>
  );
}
