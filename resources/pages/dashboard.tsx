import * as React from "react";
import { format, subDays } from "date-fns";

import {
  ChartAreaInteractive,
  type DashboardSendingStatsRange,
} from "@/components/chart-area-interactive";
import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { SectionCards } from "@/components/section-cards";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useAdminQuery } from "@/hooks/use-admin-query";
import { usePersistentState } from "@/hooks/use-persistent-state";
import { getDashboard, type AdminDashboardQuery } from "@/lib/admin-api";
import AlertCircleIcon from "~icons/hugeicons/alert-circle";
import Cancel01Icon from "~icons/hugeicons/cancel-01";
import CheckmarkCircle01Icon from "~icons/hugeicons/checkmark-circle-01";
import RefreshIcon from "~icons/hugeicons/refresh";

function resolveSendingStatsQuery(range: DashboardSendingStatsRange): AdminDashboardQuery {
  if (range.preset === "custom") {
    return {
      fromDate: range.from,
      toDate: range.to,
    };
  }

  const today = new Date();
  const days = range.preset === "7d" ? 7 : range.preset === "30d" ? 30 : 90;

  return {
    fromDate: format(subDays(today, days - 1), "yyyy-MM-dd"),
    toDate: format(today, "yyyy-MM-dd"),
  };
}

function getDashboardQueryKey(query: AdminDashboardQuery): string {
  return `${query.fromDate ?? ""}:${query.toDate ?? ""}`;
}

function DashboardSkeleton() {
  return (
    <div
      className="flex flex-1 flex-col gap-4 px-4 py-4 lg:px-6 lg:py-6"
      role="status"
      aria-live="polite"
    >
      <span className="sr-only">Loading dashboard</span>
      <div className="flex items-center justify-between gap-4">
        <div className="flex flex-col gap-2">
          <Skeleton className="h-6 w-48" />
          <Skeleton className="h-4 w-80 max-w-full" />
        </div>
        <Skeleton className="h-8 w-24" />
      </div>
      <Frame className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4" spacing="sm">
        {Array.from({ length: 4 }, (_, index) => (
          <FramePanel key={index} className="flex min-h-36 flex-col justify-between gap-5">
            <div className="flex items-center justify-between">
              <Skeleton className="size-10" />
              <Skeleton className="h-5 w-20" />
            </div>
            <div className="flex flex-col gap-2">
              <Skeleton className="h-4 w-24" />
              <Skeleton className="h-8 w-20" />
              <Skeleton className="h-3 w-36" />
            </div>
          </FramePanel>
        ))}
      </Frame>
      <Frame dense>
        <FramePanel className="flex h-96 flex-col gap-4 p-4!">
          <Skeleton className="h-5 w-36" />
          <Skeleton className="min-h-0 flex-1" />
        </FramePanel>
      </Frame>
    </div>
  );
}

export default function DashboardPage() {
  const [sendingStatsRange, setSendingStatsRange] = usePersistentState<DashboardSendingStatsRange>(
    "jooosimail:table-filters:v1:dashboard:sending-volume-range",
    { preset: "7d" },
  );
  const dashboardQuery = React.useMemo(
    () => resolveSendingStatsQuery(sendingStatsRange),
    [sendingStatsRange],
  );
  const dashboardQueryKey = getDashboardQueryKey(dashboardQuery);
  const loadDashboard = React.useCallback((signal: AbortSignal) => getDashboard(dashboardQuery, signal), [dashboardQuery]);
  const { data, loading, refreshing, error, refresh } = useAdminQuery(loadDashboard, {
    reloadKey: dashboardQueryKey,
  });

  if (loading && !data) {
    return <DashboardSkeleton />;
  }

  if (!data) {
    return (
      <div className="flex flex-1 flex-col px-4 py-4 lg:px-6 lg:py-6">
        <Frame>
          <FramePanel className="p-0!">
            <Alert variant="destructive">
              <AlertCircleIcon />
              <AlertTitle>Dashboard unavailable</AlertTitle>
              <AlertDescription>{error ?? "The dashboard could not be loaded."}</AlertDescription>
              <AlertAction>
                <Button type="button" size="xs" variant="outline" onClick={() => void refresh()}>
                  Try again
                </Button>
              </AlertAction>
            </Alert>
          </FramePanel>
        </Frame>
      </div>
    );
  }

  return (
    <div className="@container/main flex flex-1 flex-col gap-4 py-4 md:gap-6 md:py-6">
      <div className="flex flex-col gap-3 px-4 lg:flex-row lg:items-center lg:justify-between lg:px-6">
        <div className="flex flex-col gap-0.5">
          <h2 className="text-xl font-semibold tracking-tight">Mail operations</h2>
          <p className="text-sm text-muted-foreground">
            Delivery capacity, queue pressure, and provider feedback in one live view.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge
            variant={data.summary.interceptEnabled ? "success-light" : "warning-light"}
            radius="full"
          >
            {data.summary.interceptEnabled ? (
              <CheckmarkCircle01Icon aria-hidden="true" />
            ) : (
              <Cancel01Icon aria-hidden="true" />
            )}
            {data.summary.interceptEnabled ? "Mail delivery active" : "Mail delivery off"}
          </Badge>
          <Button
            type="button"
            variant="outline"
            disabled={refreshing}
            onClick={() => void refresh()}
          >
            <RefreshIcon
              data-icon="inline-start"
              className={refreshing ? "animate-spin" : undefined}
              aria-hidden="true"
            />
            <span aria-live="polite">{refreshing ? "Refreshing..." : "Refresh"}</span>
          </Button>
        </div>
      </div>

      {error ? (
        <div className="px-4 lg:px-6" role="status" aria-live="polite">
          <Alert variant="destructive">
            <AlertCircleIcon />
            <AlertTitle>Refresh failed</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        </div>
      ) : null}

      <SectionCards summary={data.summary} />

      <div className="px-4 lg:px-6">
        <ChartAreaInteractive
          data={data.sendingStats}
          range={sendingStatsRange}
          onRangeChange={setSendingStatsRange}
        />
      </div>
    </div>
  );
}
