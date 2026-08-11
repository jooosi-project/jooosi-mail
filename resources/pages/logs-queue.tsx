import * as React from "react";

import { MetricCard } from "@/components/admin/metric-card";
import { Frame } from "@/components/reui/frame";
import { QueueLogDataTable } from "@/components/queue-log-data-table";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/reui/frame-card";
import { useLogsData } from "@/hooks/use-logs-data";
import { formatAdminNumber } from "@/lib/admin-format";
import RefreshIcon from "~icons/tabler/refresh";

export default function QueueLogsPage() {
  const { data, loading, refreshing, error, refresh } = useLogsData();
  const [queueLogRefreshToken, setQueueLogRefreshToken] = React.useState(0);

  const handleRefresh = React.useCallback(() => {
    setQueueLogRefreshToken((currentValue) => currentValue + 1);
    void refresh();
  }, [refresh]);

  if (loading && !data) {
    return (
      <div className="flex flex-1 flex-col px-4 py-4 lg:px-6 lg:py-6">
        <Card>
          <CardHeader>
            <CardTitle>Loading queue logs</CardTitle>
            <CardDescription>Collecting queued email activity.</CardDescription>
          </CardHeader>
        </Card>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="flex flex-1 flex-col px-4 py-4 lg:px-6 lg:py-6">
        <Card>
          <CardHeader>
            <CardTitle>Queue logs unavailable</CardTitle>
            <CardDescription>{error ?? "The queue log view could not be loaded."}</CardDescription>
          </CardHeader>
          <CardContent>
            <Button type="button" onClick={() => void refresh()}>
              Try again
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="@container/main flex flex-1 flex-col gap-4 py-4 md:gap-6 md:py-6">
      <div className="flex flex-col gap-3 px-4 lg:flex-row lg:items-center lg:justify-between lg:px-6">
        <div className="flex flex-col gap-1">
          <h2 className="text-xl font-semibold tracking-tight">Queue logs</h2>
          <p className="text-sm text-muted-foreground">
            Review queued emails and their current status.
          </p>
        </div>
        <Button type="button" variant="outline" onClick={handleRefresh}>
          <RefreshIcon
            data-icon="inline-start"
            className={refreshing ? "animate-spin" : undefined}
          />
          {refreshing ? "Refreshing..." : "Refresh"}
        </Button>
      </div>

      <Frame className="mx-4 grid grid-cols-4 lg:mx-6" spacing="sm">
        <MetricCard
          label="Ready now"
          value={formatAdminNumber(data.summary.queue.pendingReady)}
          description="Messages available for immediate processing."
        />
        <MetricCard
          label="Deferred"
          value={formatAdminNumber(data.summary.queue.pendingDeferred)}
          description="Messages delayed by retry backoff or future availability."
        />
        <MetricCard
          label="Processing"
          value={formatAdminNumber(data.summary.queue.processing)}
          description={`Includes ${formatAdminNumber(data.summary.queue.staleProcessing)} stale claims.`}
        />
        <MetricCard
          label="Failed"
          value={formatAdminNumber(data.summary.queue.failed)}
          description="Messages that need retries or manual intervention."
        />
      </Frame>

      <div className="px-4 lg:px-6">
        <QueueLogDataTable refreshToken={queueLogRefreshToken} />
      </div>
    </div>
  );
}
