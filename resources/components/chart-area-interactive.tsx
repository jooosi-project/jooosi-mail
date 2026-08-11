import { Area, AreaChart, CartesianGrid, XAxis } from "recharts";

import { DateRangePicker, type DateRangeFilter } from "@/components/date-range-picker";
import { Badge } from "@/components/reui/badge";
import {
  Frame,
  FrameDescription,
  FrameFooter,
  FrameHeader,
  FramePanel,
  FrameTitle,
} from "@/components/reui/frame";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import type { AdminDashboardSendingStat } from "@/lib/admin-api";

export const description = "An interactive sending stats chart";

type ChartAreaInteractiveProps = {
  data: AdminDashboardSendingStat[];
  range: DashboardSendingStatsRange;
  onRangeChange: (range: DashboardSendingStatsRange) => void;
};

const chartConfig = {
  total: {
    label: "Total emails",
  },
  sent: {
    label: "Sent",
    color: "var(--primary)",
  },
  failed: {
    label: "Failed",
    color: "var(--destructive)",
  },
} satisfies ChartConfig;

const timeRangeLabels = {
  "90d": "Last 3 months",
  "30d": "Last 30 days",
  "7d": "Last 7 days",
  custom: "Custom range",
} as const;

const timeRangeItems = Object.entries(timeRangeLabels).map(([value, label]) => ({
  value,
  label,
}));

export type DashboardSendingStatsRange = DateRangeFilter & {
  preset: keyof typeof timeRangeLabels;
};

function parseChartDate(value: string): Date {
  return new Date(`${value}T00:00:00`);
}

function formatChartDate(value: string): string {
  return parseChartDate(value).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
  });
}

function formatSelectedRangeLabel(range: DashboardSendingStatsRange): string {
  if (range.preset !== "custom") {
    return timeRangeLabels[range.preset];
  }

  if (range.from && range.to) {
    return `${formatChartDate(range.from)} - ${formatChartDate(range.to)}`;
  }

  if (range.from) {
    return `Since ${formatChartDate(range.from)}`;
  }

  if (range.to) {
    return `Until ${formatChartDate(range.to)}`;
  }

  return timeRangeLabels.custom;
}

export function ChartAreaInteractive({ data, range, onRangeChange }: ChartAreaInteractiveProps) {
  const selectedRangeLabel = formatSelectedRangeLabel(range);
  const totals = data.reduce(
    (currentTotals, currentPoint) => ({
      sent: currentTotals.sent + currentPoint.sent,
      failed: currentTotals.failed + currentPoint.failed,
    }),
    { sent: 0, failed: 0 },
  );
  const attempted = totals.sent + totals.failed;
  const successRate = attempted === 0 ? 0 : (totals.sent / attempted) * 100;

  function updatePreset(value?: string): void {
    const preset = (value ?? "90d") as DashboardSendingStatsRange["preset"];

    if (preset === "custom") {
      onRangeChange({ preset: "custom", from: range.from, to: range.to });
      return;
    }

    onRangeChange({ preset });
  }

  return (
    <Frame className="@container/card" dense>
      <FramePanel className="p-0!">
        <FrameHeader className="flex-col gap-3 border-b @[767px]/card:flex-row @[767px]/card:items-center @[767px]/card:justify-between">
          <div className="flex flex-col gap-0.5">
            <FrameTitle>Sending volume</FrameTitle>
            <FrameDescription>
              Sent and failed email volume for {selectedRangeLabel.toLowerCase()}.
            </FrameDescription>
          </div>
          <div className="flex flex-col items-stretch gap-2 @[767px]/card:flex-row @[767px]/card:items-center">
            <ToggleGroup
              multiple={false}
              value={range.preset ? [range.preset] : []}
              onValueChange={(value) => updatePreset(value[0])}
              variant="outline"
              className="hidden *:data-[slot=toggle-group-item]:px-4! @[767px]/card:flex"
            >
              <ToggleGroupItem value="90d">Last 3 months</ToggleGroupItem>
              <ToggleGroupItem value="30d">Last 30 days</ToggleGroupItem>
              <ToggleGroupItem value="7d">Last 7 days</ToggleGroupItem>
              <ToggleGroupItem value="custom">Custom</ToggleGroupItem>
            </ToggleGroup>
            <Select
              items={timeRangeItems}
              value={range.preset}
              onValueChange={(value) => {
                if (value !== null) {
                  updatePreset(value);
                }
              }}
            >
              <SelectTrigger
                className="flex w-40 **:data-[slot=select-value]:block **:data-[slot=select-value]:truncate @[767px]/card:hidden"
                size="sm"
                aria-label="Select date range"
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="rounded-xl">
                <SelectGroup>
                  {timeRangeItems.map((item) => (
                    <SelectItem key={item.value} value={item.value} className="rounded-lg">
                      {item.label}
                    </SelectItem>
                  ))}
                </SelectGroup>
              </SelectContent>
            </Select>
            {range.preset === "custom" ? (
              <DateRangePicker
                id="dashboard-sending-date-range"
                value={{ from: range.from, to: range.to }}
                onChange={(value) =>
                  onRangeChange({ preset: "custom", from: value?.from, to: value?.to })
                }
                className="w-40 md:w-60"
                placeholder="Pick dates"
              />
            ) : null}
          </div>
        </FrameHeader>
        <div className="px-2 pt-4 sm:px-6 sm:pt-6">
          <ChartContainer config={chartConfig} className="aspect-auto h-[280px] w-full">
            <AreaChart data={data}>
              <defs>
                <linearGradient id="fillSent" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="var(--color-sent)" stopOpacity={1.0} />
                  <stop offset="95%" stopColor="var(--color-sent)" stopOpacity={0.1} />
                </linearGradient>
                <linearGradient id="fillFailed" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="var(--color-failed)" stopOpacity={0.8} />
                  <stop offset="95%" stopColor="var(--color-failed)" stopOpacity={0.1} />
                </linearGradient>
              </defs>
              <CartesianGrid vertical={false} />
              <XAxis
                dataKey="date"
                tickLine={false}
                axisLine={false}
                tickMargin={8}
                minTickGap={32}
                tickFormatter={(value) => formatChartDate(String(value))}
              />
              <ChartTooltip
                cursor={false}
                content={
                  <ChartTooltipContent
                    labelFormatter={(value) => formatChartDate(String(value))}
                    indicator="dot"
                  />
                }
              />
              <Area
                dataKey="failed"
                type="natural"
                fill="url(#fillFailed)"
                stroke="var(--color-failed)"
                stackId="a"
              />
              <Area
                dataKey="sent"
                type="natural"
                fill="url(#fillSent)"
                stroke="var(--color-sent)"
                stackId="a"
              />
            </AreaChart>
          </ChartContainer>
        </div>
        <FrameFooter className="flex-row flex-wrap items-center justify-between border-t">
          <p className="text-xs text-muted-foreground">
            {attempted.toLocaleString()} delivery attempts in the selected period
          </p>
          <div className="flex items-center gap-2">
            <Badge variant="success-light" radius="full">
              {totals.sent.toLocaleString()} sent
            </Badge>
            <Badge variant={totals.failed > 0 ? "destructive-light" : "outline"} radius="full">
              {successRate.toFixed(1)}% success
            </Badge>
          </div>
        </FrameFooter>
      </FramePanel>
    </Frame>
  );
}
