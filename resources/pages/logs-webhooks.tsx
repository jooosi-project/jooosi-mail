import * as React from "react";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/reui/frame-card";
import { useLogsData } from "@/hooks/use-logs-data";
import { WebhookLogDataTable } from "@/components/webhook-log-data-table";
import RefreshIcon from "~icons/tabler/refresh";

export default function WebhookLogsPage() {
  const { data, loading, refreshing, error, refresh } = useLogsData();
  const [webhookLogRefreshToken, setWebhookLogRefreshToken] = React.useState(0);

  const handleRefresh = React.useCallback(() => {
    setWebhookLogRefreshToken((currentValue) => currentValue + 1);
    void refresh();
  }, [refresh]);

  if (loading && !data) {
    return (
      <div className="flex flex-1 flex-col px-4 py-4 lg:px-6 lg:py-6">
        <Card>
          <CardHeader>
            <CardTitle>Loading webhook logs</CardTitle>
            <CardDescription>Collecting webhook activity.</CardDescription>
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
            <CardTitle>Webhook logs unavailable</CardTitle>
            <CardDescription>
              {error ?? "The webhook log view could not be loaded."}
            </CardDescription>
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
          <h2 className="text-xl font-semibold tracking-tight">Webhook logs</h2>
          <p className="text-sm text-muted-foreground">
            Review webhook activity and related emails.
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

      <div className="px-4 lg:px-6">
        <WebhookLogDataTable refreshToken={webhookLogRefreshToken} />
      </div>
    </div>
  );
}
