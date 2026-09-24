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
import { toast } from "sonner";

import { MailLogTablePagination } from "@/components/mail-log-table-pagination";
import { MailLogTableToolbar } from "@/components/mail-log-table-toolbar";
import { MailLogConnectionHoverCard } from "@/components/mail-log-connection-hover-card";
import { WebhookLogDetailsSheet } from "@/components/webhook-log-details-sheet";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import {
  normalizeWebhookLogRows,
  type WebhookLogTableRow,
} from "@/components/webhook-log-table-types";
import { WebhookLogTableViewOptions } from "@/components/webhook-log-table-view-options";
import { buildAdminHashHref } from "@/admin/routes";
import type { AdminWebhookLogQuery } from "@/lib/admin-api";
import { getWebhookLogs } from "@/lib/admin-api";
import { formatAdminDateTime, titleCase } from "@/lib/admin-format";
import { getWebhookEventVariant } from "@/lib/admin-log-helpers";
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Separator } from "@/components/ui/separator";
import type { MailLogDateRangeFilter } from "@/components/mail-log-table-types";
import ArrowDown01Icon from "~icons/hugeicons/arrow-down-01";
import ArrowUp01Icon from "~icons/hugeicons/arrow-up-01";
import MailIcon from "~icons/tabler/mail";
import MoreVerticalCircle01Icon from "~icons/hugeicons/more-vertical-circle-01";
import UnfoldMoreIcon from "~icons/hugeicons/unfold-more";
import AlertCircleIcon from "~icons/hugeicons/alert-circle";

type WebhookLogSortId = "id" | "eventType" | "dateTime" | "connection" | "mailLogId";

type WebhookLogDataTableProps = {
  refreshToken?: number;
};

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

function resolveSortBy(sorting: SortingState): WebhookLogSortId {
  const sortId = sorting[0]?.id;

  if (
    sortId === "id" ||
    sortId === "eventType" ||
    sortId === "connection" ||
    sortId === "mailLogId"
  ) {
    return sortId;
  }

  return "dateTime";
}

export function WebhookLogDataTable({ refreshToken = 0 }: WebhookLogDataTableProps) {
  const [selectedEvent, setSelectedEvent] = React.useState<WebhookLogTableRow | null>(null);
  const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});
  const [columnVisibility, setColumnVisibility] = React.useState<ColumnVisibilityState>({});
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
    "jooosimail:table-filters:v1:webhook-logs:search",
    "",
  );
  const deferredSearchValue = React.useDeferredValue(searchValue);
  const [selectedEventTypes, setSelectedEventTypes] = usePersistentState<string[]>(
    "jooosimail:table-filters:v1:webhook-logs:event-types",
    [],
  );
  const [selectedConnectionIds, setSelectedConnectionIds] = usePersistentState<string[]>(
    "jooosimail:table-filters:v1:webhook-logs:connections",
    [],
  );
  const [dateRange, setDateRange] = usePersistentState<MailLogDateRangeFilter | undefined>(
    "jooosimail:table-filters:v1:webhook-logs:date-range",
    undefined,
  );

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
    selectedEventTypes,
    selectedConnectionIds,
    dateRange?.from,
    dateRange?.to,
    sortBy,
    sortDirection,
    pagination.pageSize,
  ]);

  const query = React.useMemo<AdminWebhookLogQuery>(
    () => ({
      search: deferredSearchValue.trim() || undefined,
      eventTypes: selectedEventTypes.length > 0 ? selectedEventTypes : undefined,
      connectionIds: selectedConnectionIds.length > 0 ? selectedConnectionIds : undefined,
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
      selectedConnectionIds,
      selectedEventTypes,
      sortBy,
      sortDirection,
    ],
  );

  const { data, loading, error } = useAdminLogQuery(getWebhookLogs, query, {
    refreshToken,
    setPagination,
    errorMessage: "The webhook logs could not be loaded.",
  });
  const rows = React.useMemo(() => data ? normalizeWebhookLogRows(data.items) : [], [data]);
  const totalRows = data?.pagination.total ?? 0;
  const pageCount = data?.pagination.totalPages ?? 1;
  const eventTypeOptions = data?.filters.eventTypes ?? [];
  const connectionOptions = data?.filters.connections ?? [];

  const columns = React.useMemo<ColumnDef<DataGridFeatures, WebhookLogTableRow>[]>(
    () => [
      {
        id: "select",
        header: ({ table }) => (
          <Checkbox
            checked={table.getIsAllPageRowsSelected()}
            indeterminate={table.getIsSomePageRowsSelected() && !table.getIsAllPageRowsSelected()}
            onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
            aria-label="Select all webhook logs"
          />
        ),
        cell: ({ row }) => (
          <Checkbox
            checked={row.getIsSelected()}
            onCheckedChange={(value) => row.toggleSelected(!!value)}
            aria-label={`Select webhook event ${row.original.id}`}
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
        cell: ({ row }) => (
          <Button
            type="button"
            variant="link"
            className="h-auto justify-start px-0 text-left"
            onClick={() => setSelectedEvent(row.original)}
          >
            #{row.original.id}
          </Button>
        ),
      },
      {
        accessorKey: "eventType",
        id: "eventType",
        minSize: 95,
        size: 105,
        header: ({ column }) => <SortableHeader column={column} title="Event" />,
        cell: ({ row }) => (
          <Button
            type="button"
            variant="link"
            className="h-auto justify-start px-0 text-left"
            onClick={() => setSelectedEvent(row.original)}
          >
            <Badge variant={getWebhookEventVariant(row.original.eventType)}>
              {titleCase(row.original.eventType)}
            </Badge>
          </Button>
        ),
      },
      {
        accessorFn: (row) => row.mailLogLabel,
        id: "mailLogId",
        minSize: 84,
        size: 90,
        header: ({ column }) => <SortableHeader column={column} title="Mail" />,
        cell: ({ row }) =>
          row.original.mailLogId !== null ? (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => openRelatedMailLog(row.original.mailLogId as number)}
            >
              <MailIcon data-icon="inline-start" />
              {row.original.mailLogLabel}
            </Button>
          ) : (
            row.original.mailLogLabel
          ),
      },
      {
        accessorKey: "transportMessageId",
        id: "transportMessageId",
        minSize: 130,
        size: 150,
        meta: { autoSize: true },
        header: ({ column }) => <SortableHeader column={column} title="Transport Message" />,
        cell: ({ row }) => (
          <span className="block w-full truncate text-sm text-muted-foreground">
            {row.original.transportMessageId ?? "-"}
          </span>
        ),
        enableSorting: false,
      },
      {
        accessorKey: "dateTime",
        id: "dateTime",
        minSize: 175,
        size: 185,
        header: ({ column }) => <SortableHeader column={column} title="Occurred" />,
        cell: ({ row }) => formatAdminDateTime(row.original.dateTime),
      },
      {
        accessorFn: (row) => row.connectionLabel,
        id: "connection",
        minSize: 105,
        size: 115,
        header: ({ column }) => <SortableHeader column={column} title="Connection" />,
        cell: ({ row }) => {
          if (row.original.connectionId === null || row.original.connectionName === null) {
            return null;
          }

          return (
            <MailLogConnectionHoverCard
              connectionId={row.original.connectionId}
              connectionName={row.original.connectionName}
              profileKey={row.original.connectionProfileKey ?? ""}
            />
          );
        },
      },
      {
        id: "actions",
        enableSorting: false,
        enableHiding: false,
        enableResizing: false,
        size: 44,
        cell: ({ row }) => (
          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  className="data-open:bg-muted"
                />
              }
            >
              <MoreVerticalCircle01Icon />
              <span className="sr-only">Open webhook log actions</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
              <DropdownMenuItem onClick={() => setSelectedEvent(row.original)}>
                View details
              </DropdownMenuItem>
              <DropdownMenuItem
                disabled={!row.original.transportMessageId}
                onClick={() => {
                  const transportMessageId = row.original.transportMessageId;

                  if (!transportMessageId) {
                    return;
                  }

                  void navigator.clipboard.writeText(transportMessageId);
                  toast.success("Transport message id copied.");
                }}
              >
                Copy transport id
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        ),
      },
    ],
    [openRelatedMailLog],
  );

  const table = useTable({
    features: dataGridFeatures,
    data: rows,
    columns,
    state: {
      sorting,
      columnVisibility,
      rowSelection,
      pagination,
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
    <>
      <DataGrid
        table={table}
        recordCount={totalRows}
        isLoading={loading}
        emptyMessage="No webhook events match the current search and filters."
        tableLayout={{ columnsResizable: true, headerBackground: true, rowBorder: true }}
      >
        <Frame stacked spacing="sm">
          <FrameHeader>
            <FrameTitle>Webhook events</FrameTitle>
            <FrameDescription>Search and inspect normalized provider callbacks.</FrameDescription>
          </FrameHeader>
          <FramePanel className="p-0! shadow-none!">
            <div className="p-3">
              <MailLogTableToolbar
                searchValue={searchValue}
                onSearchChange={setSearchValue}
                searchPlaceholder="Search webhook logs..."
                statusOptions={eventTypeOptions}
                selectedStatuses={selectedEventTypes}
                onStatusesChange={setSelectedEventTypes}
                connectionOptions={connectionOptions}
                selectedConnectionIds={selectedConnectionIds}
                onConnectionIdsChange={setSelectedConnectionIds}
                dateRange={dateRange}
                onDateRangeChange={setDateRange}
                onReset={() => {
                  setSearchValue("");
                  setSelectedEventTypes([]);
                  setSelectedConnectionIds([]);
                  setDateRange(undefined);
                }}
                viewOptions={<WebhookLogTableViewOptions table={table} />}
              />
            </div>

            {error ? (
              <div className="px-3 pb-3">
                <Alert variant="destructive">
                  <AlertCircleIcon />
                  <AlertTitle>Webhook logs could not be refreshed</AlertTitle>
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

      <WebhookLogDetailsSheet
        event={selectedEvent}
        open={selectedEvent !== null}
        onOpenChange={(open) => {
          if (!open) {
            setSelectedEvent(null);
          }
        }}
      />
    </>
  );
}

export default WebhookLogDataTable;
