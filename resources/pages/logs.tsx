import * as React from "react";

import { MetricCard } from "@/components/admin/metric-card";
import { Frame } from "@/components/reui/frame";
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
import { MailLogDataTable } from "@/components/mail-log-data-table";
import { SendTestEmailDialog } from "@/components/send-test-email-dialog";
import MailIcon from "~icons/tabler/mail";
import RefreshIcon from "~icons/tabler/refresh";

export default function MailLogsPage() {
  const { data, loading, refreshing, error, refresh } = useLogsData();
  const [sendTestOpen, setSendTestOpen] = React.useState(false);
  const [mailLogRefreshToken, setMailLogRefreshToken] = React.useState(0);

  const handleRefresh = React.useCallback(() => {
    setMailLogRefreshToken((currentValue) => currentValue + 1);
    void refresh();
  }, [refresh]);

  if (loading && !data) {
    return (
      <div className="flex flex-1 flex-col px-4 py-4 lg:px-6 lg:py-6">
        <Card>
          <CardHeader>
            <CardTitle>Loading mail logs</CardTitle>
            <CardDescription>Collecting message lifecycle rows and send attempts.</CardDescription>
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
            <CardTitle>Mail logs unavailable</CardTitle>
            <CardDescription>{error ?? "The mail log view could not be loaded."}</CardDescription>
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
          <h2 className="text-xl font-semibold tracking-tight">Email logs</h2>
          <p className="text-sm text-muted-foreground">
            Review your recent email activity and delivery attempts.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" onClick={() => setSendTestOpen(true)}>
            <MailIcon data-icon="inline-start" />
            Send test
          </Button>
          <Button type="button" variant="outline" onClick={handleRefresh}>
            <RefreshIcon
              data-icon="inline-start"
              className={refreshing ? "animate-spin" : undefined}
            />
            {refreshing ? "Refreshing..." : "Refresh"}
          </Button>
        </div>
      </div>

      <Frame className="mx-4 grid grid-cols-4 lg:mx-6" spacing="sm">
        <MetricCard
          label="Total"
          value={formatAdminNumber(data.summary.mail.total)}
          description="Every intercepted message recorded in the mail lifecycle table."
        />
        <MetricCard
          label="Sent"
          value={formatAdminNumber(data.summary.mail.sent)}
          description="Messages confirmed as sent by the selected provider connection."
        />
        <MetricCard
          label="Queued"
          value={formatAdminNumber(data.summary.mail.queued)}
          description="Messages waiting for asynchronous processing."
        />
        <MetricCard
          label="Failed"
          value={formatAdminNumber(data.summary.mail.failed)}
          description="Messages that exhausted delivery without reaching a sent state."
        />
      </Frame>

      <div className="px-4 lg:px-6">
        <MailLogDataTable refreshToken={mailLogRefreshToken} />
      </div>

      <SendTestEmailDialog
        open={sendTestOpen}
        onOpenChange={setSendTestOpen}
        onSent={handleRefresh}
      />
    </div>
  );
}
