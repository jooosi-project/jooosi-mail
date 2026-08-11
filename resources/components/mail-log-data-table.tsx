"use client";

import * as React from "react";
import {
  useTable,
  type ColumnDef,
  type ColumnVisibilityState,
  type PaginationState,
  type RowSelectionState,
  type SortingState,
} from "@tanstack/react-table";
import { toast } from "sonner";

import { MailLogDetailsDialog } from "@/components/mail-log-details-dialog";
import { MailLogTablePagination } from "@/components/mail-log-table-pagination";
import { MailLogTableToolbar } from "@/components/mail-log-table-toolbar";
import {
  normalizeMailLogRows,
  type MailLogDateRangeFilter,
  type MailLogTableRow,
} from "@/components/mail-log-table-types";
import { MailLogTableViewOptions } from "@/components/mail-log-table-view-options";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import type { AdminMailLogFilterOption, AdminMailLogQuery } from "@/lib/admin-api";
import { getMailLog, getMailLogs } from "@/lib/admin-api";
import { buildAdminHashHref, parseAdminHashLocation } from "@/admin/routes";
import { formatAdminDateTime, titleCase } from "@/lib/admin-format";
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Separator } from "@/components/ui/separator";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import AlertCircleIcon from "~icons/tabler/alert-circle";
import ArrowDown01Icon from "~icons/hugeicons/arrow-down-01";
import ArrowUp01Icon from "~icons/hugeicons/arrow-up-01";
import CircleCheckIcon from "~icons/tabler/circle-check";
import ClockIcon from "~icons/tabler/clock";
import DatabaseIcon from "~icons/tabler/database";
import Loader2Icon from "~icons/tabler/loader-2";
import MoreVerticalCircle01Icon from "~icons/hugeicons/more-vertical-circle-01";
import UnfoldMoreIcon from "~icons/hugeicons/unfold-more";

type MailLogSortId = "id" | "subject" | "status" | "dateTime" | "connection";

type MailLogDataTableProps = {
  refreshToken?: number;
};

const LOG_TABLE_POLL_MS = 15_000;

function getMailStatusIcon(status: string) {
  switch (status) {
    case "sent":
      return CircleCheckIcon;
    case "processing":
      return Loader2Icon;
    case "pending":
    case "queued":
      return ClockIcon;
    case "failed":
      return AlertCircleIcon;
    default:
      return ClockIcon;
  }
}

function subscribeToHashChange(callback: () => void): () => void {
  if (typeof window === "undefined") {
    return () => undefined;
  }

  window.addEventListener("hashchange", callback);

  return () => window.removeEventListener("hashchange", callback);
}

function getMailLogIdFromHash(): number | null {
  if (typeof window === "undefined") {
    return null;
  }

  const mailLogId = parseAdminHashLocation(window.location.hash).searchParams.get("id");

  if (!mailLogId || /^\d+$/.test(mailLogId) === false) {
    return null;
  }

  return Number(mailLogId);
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

function TruncatedTextTooltip({
  value,
  className,
}: {
  value: string;
  className?: string;
}) {
  const textRef = React.useRef<HTMLSpanElement>(null);
  const [isTruncated, setIsTruncated] = React.useState(false);

  React.useLayoutEffect(() => {
    const text = textRef.current;

    if (!text) {
      return;
    }

    const updateTruncation = () => {
      const nextIsTruncated = text.scrollWidth > text.clientWidth;

      setIsTruncated((currentIsTruncated) =>
        currentIsTruncated === nextIsTruncated ? currentIsTruncated : nextIsTruncated,
      );
    };

    updateTruncation();

    if (typeof ResizeObserver === "undefined") {
      return;
    }

    const observer = new ResizeObserver(updateTruncation);

    observer.observe(text);

    return () => observer.disconnect();
  }, [value]);

  return (
    <Tooltip disabled={!isTruncated}>
      <TooltipTrigger
        render={
          <span
            ref={textRef}
            className={cn("block min-w-0 truncate", className)}
            data-truncated={isTruncated || undefined}
          />
        }
      >
        {value}
      </TooltipTrigger>
      <TooltipContent className="max-w-sm break-all">{value}</TooltipContent>
    </Tooltip>
  );
}

function resolveSortBy(sorting: SortingState): MailLogSortId {
  const sortId = sorting[0]?.id;

  if (sortId === "id" || sortId === "subject" || sortId === "status" || sortId === "connection") {
    return sortId;
  }

  return "dateTime";
}

export function MailLogDataTable({ refreshToken = 0 }: MailLogDataTableProps) {
  const [selectedLog, setSelectedLog] = React.useState<MailLogTableRow | null>(null);
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
  const [searchValue, setSearchValue] = React.useState("");
  const deferredSearchValue = React.useDeferredValue(searchValue);
  const [selectedStatuses, setSelectedStatuses] = React.useState<string[]>([]);
  const [selectedConnectionIds, setSelectedConnectionIds] = React.useState<string[]>([]);
  const [dateRange, setDateRange] = React.useState<MailLogDateRangeFilter | undefined>(undefined);
  const [rows, setRows] = React.useState<MailLogTableRow[]>([]);
  const [statusOptions, setStatusOptions] = React.useState<AdminMailLogFilterOption[]>([]);
  const [connectionOptions, setConnectionOptions] = React.useState<AdminMailLogFilterOption[]>([]);
  const [totalRows, setTotalRows] = React.useState(0);
  const [pageCount, setPageCount] = React.useState(1);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);
  const targetedMailLogId = React.useSyncExternalStore(
    subscribeToHashChange,
    getMailLogIdFromHash,
    () => null,
  );

  const sortBy = resolveSortBy(sorting);
  const sortDirection = sorting[0]?.desc ? "desc" : "asc";

  const openConnection = React.useCallback((connectionId: number) => {
    if (typeof window === "undefined") {
      return;
    }

    window.location.hash = buildAdminHashHref("/connections", {
      id: connectionId,
    }).slice(1);
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
    selectedConnectionIds,
    dateRange?.from,
    dateRange?.to,
    sortBy,
    sortDirection,
    pagination.pageSize,
  ]);

  const query = React.useMemo<AdminMailLogQuery>(
    () => ({
      search: deferredSearchValue.trim() || undefined,
      statuses: selectedStatuses.length > 0 ? selectedStatuses : undefined,
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
      selectedStatuses,
      sortBy,
      sortDirection,
    ],
  );

  React.useEffect(() => {
    let active = true;
    let requestId = 0;

    const loadMailLogs = (showLoading: boolean) => {
      const currentRequestId = requestId + 1;

      requestId = currentRequestId;

      if (showLoading) {
        setLoading(true);
      }

      setError(null);

      void getMailLogs(query)
        .then((response) => {
          if (!active || currentRequestId !== requestId) {
            return;
          }

          setRows(normalizeMailLogRows(response.items));
          setStatusOptions(response.filters.statuses);
          setConnectionOptions(response.filters.connections);
          setTotalRows(response.pagination.total);
          setPageCount(response.pagination.totalPages);
          setPagination((currentPagination) => {
            const nextPageIndex = Math.max(0, response.pagination.page - 1);

            if (
              currentPagination.pageIndex === nextPageIndex &&
              currentPagination.pageSize === response.pagination.perPage
            ) {
              return currentPagination;
            }

            return {
              pageIndex: nextPageIndex,
              pageSize: response.pagination.perPage,
            };
          });
        })
        .catch((caughtError) => {
          if (!active || currentRequestId !== requestId) {
            return;
          }

          setError(
            caughtError instanceof Error
              ? caughtError.message
              : "The email logs could not be loaded.",
          );
        })
        .finally(() => {
          if (active && currentRequestId === requestId) {
            setLoading(false);
          }
        });
    };

    loadMailLogs(true);

    const intervalId = window.setInterval(() => {
      loadMailLogs(false);
    }, LOG_TABLE_POLL_MS);

    return () => {
      active = false;
      window.clearInterval(intervalId);
    };
  }, [query, refreshToken]);

  const columns = React.useMemo<ColumnDef<DataGridFeatures, MailLogTableRow>[]>(
    () => [
      {
        id: "select",
        header: ({ table }) => (
          <Checkbox
            checked={table.getIsAllPageRowsSelected()}
            indeterminate={table.getIsSomePageRowsSelected() && !table.getIsAllPageRowsSelected()}
            onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
            aria-label="Select all mail logs"
          />
        ),
        cell: ({ row }) => (
          <Checkbox
            checked={row.getIsSelected()}
            onCheckedChange={(value) => row.toggleSelected(!!value)}
            aria-label={`Select mail log ${row.original.id}`}
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
            onClick={() => openMailLogDetails(row.original)}
          >
            #{row.original.id}
          </Button>
        ),
      },
      {
        accessorKey: "subject",
        id: "subject",
        minSize: 90,
        size: 95,
        meta: { autoSize: true, cellClassName: "ps-1 pe-0" },
        header: ({ column }) => <SortableHeader column={column} title="Subject" />,
        cell: ({ row }) => (
          <div className="flex min-w-0 flex-col gap-1">
            <Button
              type="button"
              variant="link"
              className="h-auto w-full min-w-0 justify-start overflow-hidden px-0 text-left"
              onClick={() => openMailLogDetails(row.original)}
            >
              <TruncatedTextTooltip value={row.original.subject || "(No subject)"} />
            </Button>
            {row.original.lastError ? (
              <TruncatedTextTooltip
                value={row.original.lastError}
                className="text-xs text-muted-foreground"
              />
            ) : null}
          </div>
        ),
      },
      {
        accessorKey: "toSummary",
        id: "to",
        minSize: 150,
        size: 160,
        header: ({ column }) => <SortableHeader column={column} title="To" />,
        cell: ({ row }) => <TruncatedTextTooltip value={row.original.toSummary} />,
        enableSorting: false,
      },
      {
        accessorKey: "dateTime",
        id: "dateTime",
        minSize: 175,
        size: 185,
        header: ({ column }) => <SortableHeader column={column} title="Date time" />,
        cell: ({ row }) => (
          <TruncatedTextTooltip value={formatAdminDateTime(row.original.dateTime)} />
        ),
      },
      {
        accessorFn: (row) => row.connectionLabel,
        id: "connection",
        minSize: 90,
        size: 92,
        meta: { headerClassName: "px-2", cellClassName: "px-1" },
        header: ({ column }) => <SortableHeader column={column} title="Connection" />,
        cell: ({ row }) => (
          <Button
            type="button"
            variant="secondary"
            size="sm"
            className="px-2"
            disabled={row.original.finalConnectionId === null}
            onClick={() => {
              if (row.original.finalConnectionId === null) {
                return;
              }

              openConnection(row.original.finalConnectionId);
            }}
          >
            <DatabaseIcon data-icon="inline-start" />
            {row.original.connectionLabel}
          </Button>
        ),
      },
      {
        accessorKey: "status",
        id: "status",
        minSize: 75,
        size: 78,
        meta: { headerClassName: "px-1", cellClassName: "px-2" },
        header: ({ column }) => <SortableHeader column={column} title="Status" />,
        cell: ({ row }) => {
          const StatusIcon = getMailStatusIcon(row.original.status);

          return (
            <Badge variant={getLogStatusVariant(row.original.status)}>
              <StatusIcon data-icon="inline-start" />
              {titleCase(row.original.status)}
            </Badge>
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
              <span className="sr-only">Open mail log actions</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
              <DropdownMenuItem onClick={() => openMailLogDetails(row.original)}>
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
    [openConnection],
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

  const syncMailLogHash = React.useCallback((mailLogId: number | null) => {
    if (typeof window === "undefined") {
      return;
    }

    const { searchParams } = parseAdminHashLocation(window.location.hash);

    if (mailLogId === null) {
      searchParams.delete("id");
    } else {
      searchParams.set("id", `${mailLogId}`);
    }

    const nextUrl = new URL(window.location.href);
    nextUrl.hash = buildAdminHashHref("/logs/mail", searchParams);
    window.history.replaceState(window.history.state, "", nextUrl);
  }, []);

  const openMailLogDetails = React.useCallback(
    (mailLog: MailLogTableRow) => {
      setSelectedLog(mailLog);
      syncMailLogHash(mailLog.id);
    },
    [syncMailLogHash],
  );

  React.useEffect(() => {
    if (targetedMailLogId === null) {
      return;
    }

    if (selectedLog?.id === targetedMailLogId) {
      return;
    }

    const currentRow = rows.find((row) => row.id === targetedMailLogId);

    if (currentRow) {
      setSelectedLog(currentRow);
      return;
    }

    let active = true;

    void getMailLog(targetedMailLogId)
      .then((response) => {
        if (!active || !response.item) {
          return;
        }

        const [mailLog] = normalizeMailLogRows([response.item]);

        if (mailLog) {
          setSelectedLog(mailLog);
        }
      })
      .catch(() => undefined);

    return () => {
      active = false;
    };
  }, [rows, selectedLog?.id, targetedMailLogId]);

  return (
    <>
      <DataGrid
        table={table}
        recordCount={totalRows}
        isLoading={loading}
        emptyMessage="No email logs match the current search and filters."
        tableLayout={{ columnsResizable: true, headerBackground: true, rowBorder: true }}
      >
        <Frame stacked spacing="sm">
          <FrameHeader>
            <FrameTitle>Email logs</FrameTitle>
            <FrameDescription>
              Search, filter, and inspect recorded message lifecycles.
            </FrameDescription>
          </FrameHeader>
          <FramePanel className="p-0! shadow-none!">
            <div className="p-3">
              <MailLogTableToolbar
                searchValue={searchValue}
                onSearchChange={setSearchValue}
                statusOptions={statusOptions}
                selectedStatuses={selectedStatuses}
                onStatusesChange={setSelectedStatuses}
                connectionOptions={connectionOptions}
                selectedConnectionIds={selectedConnectionIds}
                onConnectionIdsChange={setSelectedConnectionIds}
                dateRange={dateRange}
                onDateRangeChange={setDateRange}
                onReset={() => {
                  setSearchValue("");
                  setSelectedStatuses([]);
                  setSelectedConnectionIds([]);
                  setDateRange(undefined);
                }}
                viewOptions={<MailLogTableViewOptions table={table} />}
              />
            </div>

            {error ? (
              <div className="px-3 pb-3">
                <Alert variant="destructive">
                  <AlertCircleIcon />
                  <AlertTitle>Email logs could not be refreshed</AlertTitle>
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

      <MailLogDetailsDialog
        log={selectedLog}
        open={selectedLog !== null}
        onOpenChange={(open) => {
          if (!open) {
            setSelectedLog(null);
            syncMailLogHash(null);
          }
        }}
      />
    </>
  );
}

export default MailLogDataTable;
