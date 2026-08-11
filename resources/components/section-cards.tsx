import type { ReactNode } from "react";

import { Badge, type BadgeProps } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import type { AdminDashboardData } from "@/lib/admin-api";
import { formatAdminDateTime, formatAdminNumber, titleCase } from "@/lib/admin-format";
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
  badge: string;
  badgeVariant: BadgeProps["variant"];
  icon: ReactNode;
};

export function SectionCards({ summary }: SectionCardsProps) {
  const metrics: DashboardMetric[] = [
    {
      label: "Messages",
      value: formatAdminNumber(summary.mailTotal),
      description: `${formatAdminNumber(summary.mailFailed)} failed · ${formatAdminNumber(summary.mailQueued)} queued`,
      badge: `${formatAdminNumber(summary.mailSent)} sent`,
      badgeVariant: "success-light",
      icon: <Mail01Icon />,
    },
    {
      label: "Connections",
      value: formatAdminNumber(summary.connectionsTotal),
      description: summary.nextAvailableAt
        ? `Next route opens ${formatAdminDateTime(summary.nextAvailableAt)}`
        : `${formatAdminNumber(summary.availableConnections)} available now`,
      badge: `${formatAdminNumber(summary.activeConnections)} active`,
      badgeVariant: "info-light",
      icon: <Database01Icon />,
    },
    {
      label: "Ready queue",
      value: formatAdminNumber(summary.queuePendingReady),
      description: `${formatAdminNumber(summary.queueProcessing)} processing · ${formatAdminNumber(summary.queueFailed)} failed`,
      badge: `${formatAdminNumber(summary.queuePendingDeferred)} deferred`,
      badgeVariant: summary.queuePendingDeferred > 0 ? "warning-light" : "outline",
      icon: <Clock01Icon />,
    },
    {
      label: "Webhook events",
      value: formatAdminNumber(summary.webhookEvents),
      description: `${titleCase(summary.routingStrategy)} connection selection`,
      badge: titleCase(summary.deliveryMode),
      badgeVariant: "primary-light",
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
              <Badge variant={metric.badgeVariant} radius="full">
                {metric.badge}
              </Badge>
            </div>
            <div className="flex flex-col gap-1">
              <p className="text-sm font-medium text-muted-foreground">{metric.label}</p>
              <p className="text-3xl font-semibold tracking-tight tabular-nums">{metric.value}</p>
              <p className="line-clamp-1 text-xs text-muted-foreground">{metric.description}</p>
            </div>
          </FramePanel>
        ))}
      </Frame>
    </div>
  );
}
