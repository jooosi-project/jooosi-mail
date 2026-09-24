"use client";

import * as React from "react";
import { useAdminLogQuery } from "@/hooks/use-admin-log-query";
import { usePersistentState } from "@/hooks/use-persistent-state";
import {
  useTable,
  type ColumnDef,
  type ColumnVisibilityState,
  type PaginationState,
  type RowSelectionState,
  type SortingState,
} from "@tanstack/react-table";

import { buildAdminHashHref } from "@/admin/routes";
import { MailLogTablePagination } from "@/components/mail-log-table-pagination";
import { MailLogTableToolbar } from "@/components/mail-log-table-toolbar";
import { QueueLogTableViewOptions } from "@/components/queue-log-table-view-options";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import type { MailLogDateRangeFilter } from "@/components/mail-log-table-types";
import type {
  AdminQueueAttempt,
  AdminQueueLogQuery,
  AdminQueueMessage,
} from "@/lib/admin-api";
import { getQueueLogs } from "@/lib/admin-api";
import { formatAdminDateTime, parseAdminDateTime, titleCase } from "@/lib/admin-format";
import { getLogStatusVariant } from "@/lib/admin-log-helpers";
import { Badge } from "@/components/reui/badge";
import {
  DataGrid,
  dataGridFeatures,
  type DataGridFeatures,
} from "@/components/reui/data-grid/data-grid";
import { DataGridTable } from "@/components/reui/data-grid/data-grid-table";
import {
  Frame,
  FrameDescription,
  FrameFooter,
  FrameHeader,
  FramePanel,
  FrameTitle,
} from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Separator } from "@/components/ui/separator";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import ArrowDown01Icon from "~icons/hugeicons/arrow-down-01";
import ArrowUp01Icon from "~icons/hugeicons/arrow-up-01";
import MailIcon from "~icons/tabler/mail";
import UnfoldMoreIcon from "~icons/hugeicons/unfold-more";
import AlertCircleIcon from "~icons/hugeicons/alert-circle";

type QueueLogSortId = "id" | "status" | "priority" | "attempts" | "dateTime";

type QueueLogDataTableProps = {
  refreshToken?: number;
};

const QUEUE_CLAIM_STALE_AFTER_SECONDS = 300;

function getQueueMessageDateTime(message: AdminQueueMessage): string | null {
  return (
    message.updatedAt ??
    message.processedAt ??
    message.claimedAt ??
    message.availableAt ??
    message.createdAt
  );
}

function formatDurationLabel(totalSeconds: number): string {
  if (totalSeconds < 60) {
    return `${totalSeconds}s`;
  }

  const totalMinutes = Math.floor(totalSeconds / 60);

  if (totalMinutes < 60) {
    return `${totalMinutes}m`;
  }

  const totalHours = Math.floor(totalMinutes / 60);

  if (totalHours < 24) {
    const remainingMinutes = totalMinutes % 60;

    return remainingMinutes === 0 ? `${totalHours}h` : `${totalHours}h ${remainingMinutes}m`;
  }

  const totalDays = Math.floor(totalHours / 24);
  const remainingHours = totalHours % 24;

  return remainingHours === 0 ? `${totalDays}d` : `${totalDays}d ${remainingHours}h`;
}

function getClaimAgeLabel(claimedAt: string | null | undefined, now: number): string | null {
  const claimedAtDate = parseAdminDateTime(claimedAt);

  if (claimedAtDate === null) {
    return null;
  }

  return formatDurationLabel(Math.max(0, Math.floor((now - claimedAtDate.getTime()) / 1000)));
}

function isStaleClaim(message: AdminQueueMessage, now: number): boolean {
  if (message.status !== "processing") {
    return false;
  }

  const claimedAtDate = parseAdminDateTime(message.claimedAt);

  if (claimedAtDate === null) {
    return false;
  }

  return now - claimedAtDate.getTime() >= QUEUE_CLAIM_STALE_AFTER_SECONDS * 1000;
}

function formatClaimedTooltipValue(message: AdminQueueMessage, now: number): string {
  const claimedAtLabel = formatAdminDateTime(message.claimedAt);
  const claimAgeLabel = getClaimAgeLabel(message.claimedAt, now);

  if (!claimAgeLabel) {
    return claimedAtLabel;
  }

  return `${claimedAtLabel} (Age ${claimAgeLabel})`;
}

function QueueAttemptHistory({ message }: { message: AdminQueueMessage }) {
  const attemptHistory = message.attemptHistory ?? [];
  const attemptCountLabel = `${message.attemptCount} / ${message.maxAttempts}`;

  if (attemptHistory.length === 0) {
    return <span>{attemptCountLabel}</span>;
  }

  return (
    <Tooltip>
      <TooltipTrigger
        render={<span className="cursor-help underline decoration-dotted underline-offset-2" />}
      >
        {attemptCountLabel}
      </TooltipTrigger>
      <TooltipContent className="max-h-80 max-w-sm flex-col items-stretch gap-2 overflow-y-auto p-3">
        <div className="text-sm font-semibold">Attempt history</div>
        {attemptHistory.map((attempt: AdminQueueAttempt, index) => (
          <div
            key={`${attempt.attemptNumber}-${attempt.startedAt}-${index}`}
            className="flex flex-col gap-1 border-t border-background/20 pt-2 first:border-t-0 first:pt-0"
          >
            <div className="flex items-center justify-between gap-3">
              <span className="font-medium">
                Run {attempt.sequenceNumber} · attempt {attempt.attemptNumber}
              </span>
              <Badge variant={getLogStatusVariant(attempt.outcome)}>
                {titleCase(attempt.outcome)}
              </Badge>
            </div>
            <p className="break-all text-background/70">
              Claimed by {attempt.workerId ?? "Unknown"}
            </p>
            <p className="text-background/70">Started {formatAdminDateTime(attempt.startedAt)}</p>
            {attempt.finishedAt ? (
              <p className="text-background/70">Finished {formatAdminDateTime(attempt.finishedAt)}</p>
            ) : null}
            {attempt.retryDelaySeconds !== null ? (
              <p className="text-background/70">
                Retry delay {formatDurationLabel(attempt.retryDelaySeconds)}
              </p>
            ) : null}
            {attempt.errorMessage ? (
              <p className="whitespace-pre-wrap break-words">{attempt.errorMessage}</p>
            ) : null}
          </div>
        ))}
      </TooltipContent>
    </Tooltip>
  );
}

function QueueLastWorker({ message }: { message: AdminQueueMessage }) {
  const latestAttempt = message.attemptHistory?.[message.attemptHistory.length - 1];
  const workerId =
    (message.status === "processing" ? message.workerId?.trim() : null) ||
    latestAttempt?.workerId?.trim();

  if (!workerId) {
    if (message.status !== "processing") {
      return <span className="text-muted-foreground">—</span>;
    }

    return (
      <Tooltip>
        <TooltipTrigger
          render={<span className="cursor-help text-muted-foreground underline decoration-dotted underline-offset-2" />}
        >
          Unknown
        </TooltipTrigger>
        <TooltipContent>Worker identity was not recorded for the active claim.</TooltipContent>
      </Tooltip>
    );
  }

  return (
    <Tooltip>
      <TooltipTrigger
        render={<span className="block max-w-40 cursor-help truncate underline decoration-dotted underline-offset-2" />}
      >
        {workerId}
      </TooltipTrigger>
      <TooltipContent className="max-w-sm break-all">{workerId}</TooltipContent>
    </Tooltip>
  );
}

function QueueDateValue({ message, now }: { message: AdminQueueMessage; now: number }) {
  const primaryDate = getQueueMessageDateTime(message);
  const stale = isStaleClaim(message, now);

  if (!primaryDate) {
    return "-";
  }

  return (
    <div className="flex flex-col gap-1">
      <Tooltip>
        <TooltipTrigger
          render={<span className="cursor-help underline decoration-dotted underline-offset-2" />}
        >
          {formatAdminDateTime(primaryDate)}
        </TooltipTrigger>
        <TooltipContent className="max-w-sm">
          <div className="flex flex-col gap-1.5">
            <div className="grid grid-cols-[auto_1fr] gap-2">
              <span className="opacity-70">Available</span>
              <span>{formatAdminDateTime(message.availableAt)}</span>
            </div>
            <div className="grid grid-cols-[auto_1fr] gap-2">
              <span className="opacity-70">Claimed</span>
              <span>{formatClaimedTooltipValue(message, now)}</span>
            </div>
            <div className="grid grid-cols-[auto_1fr] gap-2">
              <span className="opacity-70">Updated</span>
              <span>{formatAdminDateTime(primaryDate)}</span>
            </div>
          </div>
        </TooltipContent>
      </Tooltip>
      {stale ? (
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <Badge variant="destructive">Stale</Badge>
        </div>
      ) : null}
    </div>
  );
}

function SortableHeader({
  column,
  title,
}: {
  column: {
    getIsSorted: () => false | "asc" | "desc";
    toggleSorting: (desc?: boolean) => void;
  };
  title: string;
}) {
  const direction = column.getIsSorted();

  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      className="-ml-3 h-8"
      onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
    >
      {title}
      {direction === "asc" ? <ArrowUp01Icon data-icon="inline-end" /> : null}
      {direction === "desc" ? <ArrowDown01Icon data-icon="inline-end" /> : null}
      {direction === false ? <UnfoldMoreIcon data-icon="inline-end" /> : null}
    </Button>
  );
}

function resolveSortBy(sorting: SortingState): QueueLogSortId {
  const sortId = sorting[0]?.id;

  if (sortId === "id" || sortId === "status" || sortId === "priority" || sortId === "attempts") {
    return sortId;
  }

  return "dateTime";
}

export function QueueLogDataTable({ refreshToken = 0 }: QueueLogDataTableProps) {
  const [columnVisibility, setColumnVisibility] = React.useState<ColumnVisibilityState>({});
  const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});
  const [sorting, setSorting] = React.useState<SortingState>([
    {
      id: "dateTime",
      desc: true,
    },
  ]);
  const [pagination, setPagination] = React.useState<PaginationState>({
    pageIndex: 0,
    pageSize: 25,
  });
  const [searchValue, setSearchValue] = usePersistentState(
    "jooosimail:table-filters:v1:queue-logs:search",
    "",
  );
  const deferredSearchValue = React.useDeferredValue(searchValue);
  const [selectedStatuses, setSelectedStatuses] = usePersistentState<string[]>(
    "jooosimail:table-filters:v1:queue-logs:statuses",
    [],
  );
  const [dateRange, setDateRange] = usePersistentState<MailLogDateRangeFilter | undefined>(
    "jooosimail:table-filters:v1:queue-logs:date-range",
    undefined,
  );
  const [now, setNow] = React.useState(() => Date.now());

  const openRelatedMailLog = React.useCallback((mailLogId: number) => {
    if (typeof window === "undefined") {
      return;
    }

    window.location.hash = buildAdminHashHref("/logs/mail", {
      id: mailLogId,
    }).slice(1);
  }, []);

  const sortBy = resolveSortBy(sorting);
  const sortDirection = sorting[0]?.desc ? "desc" : "asc";

  React.useEffect(() => {
    const intervalId = window.setInterval(() => {
      setNow(Date.now());
    }, 60_000);

    return () => {
      window.clearInterval(intervalId);
    };
  }, []);

  React.useEffect(() => {
    setPagination((currentPagination) =>
      currentPagination.pageIndex === 0
        ? currentPagination
        : {
            ...currentPagination,
            pageIndex: 0,
          },
    );
  }, [
    deferredSearchValue,
    selectedStatuses,
    dateRange?.from,
    dateRange?.to,
    sortBy,
    sortDirection,
    pagination.pageSize,
  ]);

  const query = React.useMemo<AdminQueueLogQuery>(
    () => ({
      search: deferredSearchValue.trim() || undefined,
      statuses: selectedStatuses.length > 0 ? selectedStatuses : undefined,
      fromDate: dateRange?.from,
      toDate: dateRange?.to,
      page: pagination.pageIndex + 1,
      perPage: pagination.pageSize,
      sortBy,
      sortDirection,
    }),
    [
      dateRange?.from,
      dateRange?.to,
      deferredSearchValue,
      pagination.pageIndex,
      pagination.pageSize,
      selectedStatuses,
      sortBy,
      sortDirection,
    ],
  );

  const { data, loading, error } = useAdminLogQuery(getQueueLogs, query, {
    refreshToken,
    setPagination,
    errorMessage: "The queue logs could not be loaded.",
  });
  const rows = React.useMemo(() => data ? data.items : [], [data]);
  const totalRows = data?.pagination.total ?? 0;
  const pageCount = data?.pagination.totalPages ?? 1;
  const statusOptions = data?.filters.statuses ?? [];

  const columns = React.useMemo<ColumnDef<DataGridFeatures, AdminQueueMessage>[]>(
    () => [
      {
        id: "select",
        header: ({ table }) => (
          <Checkbox
            checked={table.getIsAllPageRowsSelected()}
            indeterminate={table.getIsSomePageRowsSelected() && !table.getIsAllPageRowsSelected()}
            onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
            aria-label="Select all queue messages"
          />
        ),
        cell: ({ row }) => (
          <Checkbox
            checked={row.getIsSelected()}
            onCheckedChange={(value) => row.toggleSelected(!!value)}
            aria-label={`Select queue message ${row.original.id}`}
          />
        ),
        enableSorting: false,
        enableHiding: false,
        enableResizing: false,
        size: 36,
        meta: {
          headerClassName: "ps-2",
          cellClassName: "px-2",
        },
      },
      {
        accessorFn: (row) => `#${row.id}`,
        id: "id",
        minSize: 52,
        size: 52,
        header: ({ column }) => <SortableHeader column={column} title="ID" />,
        cell: ({ row }) => <span className="font-medium">#{row.original.id}</span>,
        enableHiding: false,
      },
      {
        accessorFn: (row) => row.mailLogId ?? -1,
        id: "mailLogId",
        minSize: 80,
        size: 88,
        header: () => <span>Mail</span>,
        cell: ({ row }) =>
          typeof row.original.mailLogId === "number" ? (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => openRelatedMailLog(row.original.mailLogId as number)}
            >
              <MailIcon data-icon="inline-start" />#{row.original.mailLogId}
            </Button>
          ) : (
            "-"
          ),
        enableSorting: false,
      },
      {
        accessorKey: "status",
        id: "status",
        minSize: 100,
        size: 110,
        header: ({ column }) => <SortableHeader column={column} title="Status" />,
        cell: ({ row }) => (
          <Badge variant={getLogStatusVariant(row.original.status)}>
            {titleCase(row.original.status)}
          </Badge>
        ),
      },
      {
        accessorKey: "workerId",
        id: "workerId",
        minSize: 130,
        size: 170,
        header: () => <span>Last worker</span>,
        cell: ({ row }) => <QueueLastWorker message={row.original} />,
        enableSorting: false,
      },
      {
        accessorKey: "priority",
        id: "priority",
        minSize: 80,
        size: 88,
        header: ({ column }) => <SortableHeader column={column} title="Priority" />,
        cell: ({ row }) => row.original.priority,
      },
      {
        accessorFn: (row) => row.attemptCount,
        id: "attempts",
        minSize: 92,
        size: 100,
        header: ({ column }) => <SortableHeader column={column} title="Attempts" />,
        cell: ({ row }) => <QueueAttemptHistory message={row.original} />,
      },
      {
        accessorFn: (row) => getQueueMessageDateTime(row) ?? "",
        id: "dateTime",
        minSize: 160,
        size: 180,
        meta: { autoSize: true },
        header: ({ column }) => <SortableHeader column={column} title="Date" />,
        cell: ({ row }) => <QueueDateValue message={row.original} now={now} />,
      },
    ],
    [now, openRelatedMailLog],
  );

  const table = useTable({
    features: dataGridFeatures,
    data: rows,
    columns,
    state: {
      sorting,
      pagination,
      columnVisibility,
      rowSelection,
    },
    pageCount,
    getRowId: (row) => row.id.toString(),
    enableRowSelection: true,
    manualPagination: true,
    manualSorting: true,
    onRowSelectionChange: setRowSelection,
    onSortingChange: setSorting,
    onColumnVisibilityChange: setColumnVisibility,
    onPaginationChange: setPagination,
  });

  return (
    <DataGrid
      table={table}
      recordCount={totalRows}
      isLoading={loading}
      emptyMessage="No queue messages match the current search and filters."
      tableLayout={{ columnsResizable: true, headerBackground: true, rowBorder: true }}
    >
      <Frame stacked spacing="sm">
        <FrameHeader>
          <FrameTitle>Queue messages</FrameTitle>
          <FrameDescription>Search and inspect asynchronous delivery jobs.</FrameDescription>
        </FrameHeader>
        <FramePanel className="p-0! shadow-none!">
          <div className="p-3">
            <MailLogTableToolbar
              searchValue={searchValue}
              onSearchChange={setSearchValue}
              searchPlaceholder="Search queue logs..."
              statusOptions={statusOptions}
              selectedStatuses={selectedStatuses}
              onStatusesChange={setSelectedStatuses}
              connectionOptions={[]}
              selectedConnectionIds={[]}
              onConnectionIdsChange={() => undefined}
              dateRange={dateRange}
              onDateRangeChange={setDateRange}
              onReset={() => {
                setSearchValue("");
                setSelectedStatuses([]);
                setDateRange(undefined);
              }}
              viewOptions={<QueueLogTableViewOptions table={table} />}
            />
          </div>

          {error ? (
            <div className="px-3 pb-3">
              <Alert variant="destructive">
                <AlertCircleIcon />
                <AlertTitle>Queue logs could not be refreshed</AlertTitle>
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            </div>
          ) : null}

          <Separator />
          <DataGridTable />
        </FramePanel>
        <FrameFooter>
          <MailLogTablePagination table={table} totalRows={totalRows} />
        </FrameFooter>
      </Frame>
    </DataGrid>
  );
}

export default QueueLogDataTable;
