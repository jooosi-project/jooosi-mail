"use client";

import * as React from "react";
import {
  useTable,
  type ColumnDef,
  type PaginationState,
  type ReactTable as TanstackTable,
  type SortingState,
} from "@tanstack/react-table";

import { MailLogTableFacetedFilter } from "@/components/mail-log-table-faceted-filter";
import { ProfileBrandIcon } from "@/components/profile-brand-icon";
import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
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
import { Input } from "@/components/ui/input";
import {
  Popover,
  PopoverContent,
  PopoverDescription,
  PopoverHeader,
  PopoverTitle,
  PopoverTrigger,
} from "@/components/ui/popover";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import type { AdminConnectionProfile, AdminConnectionSummary } from "@/lib/admin-api";
import {
  type ConnectionOperationalStatus,
  getConnectionOperationalLabel,
  getConnectionOperationalStatus,
  hasEnabledConnectionWebhooks,
  supportsConnectionWebhooks,
} from "@/lib/admin-connections";
import { titleCase } from "@/lib/admin-format";
import ArrowDown01Icon from "~icons/hugeicons/arrow-down-01";
import ArrowLeft01Icon from "~icons/hugeicons/arrow-left-01";
import ArrowLeftDoubleIcon from "~icons/hugeicons/arrow-left-double";
import ArrowRight01Icon from "~icons/hugeicons/arrow-right-01";
import ArrowRightDoubleIcon from "~icons/hugeicons/arrow-right-double";
import ArrowUp01Icon from "~icons/hugeicons/arrow-up-01";
import BookOpen01Icon from "~icons/hugeicons/book-open-01";
import Briefcase02Icon from "~icons/hugeicons/briefcase-02";
import CheckmarkCircle02Icon from "~icons/hugeicons/checkmark-circle-02";
import Globe02Icon from "~icons/hugeicons/globe-02";
import InformationCircleIcon from "~icons/hugeicons/information-circle";
import MultiplicationSignCircleIcon from "~icons/hugeicons/multiplication-sign-circle";
import UnfoldMoreIcon from "~icons/hugeicons/unfold-more";
import WebhookIcon from "~icons/hugeicons/webhook";

type ConnectionsOverviewTableProps = {
  profiles: AdminConnectionProfile[];
  connections: AdminConnectionSummary[];
  onOpenConnection: (connectionId: number | null) => void;
  onCreateConnection: () => void;
  onEnabledChange: (connectionId: number, enabled: boolean) => Promise<void> | void;
  onDefaultChange: (connectionId: number) => Promise<void> | void;
  enabledSavingConnectionIds: ReadonlySet<number>;
  defaultSavingConnectionId: number | null;
};

type ConnectionTableRow = AdminConnectionSummary & {
  statusKey: ConnectionOperationalStatus;
  statusLabel: string;
  searchText: string;
};

type ConnectionSortId =
  | "enabled"
  | "default"
  | "name"
  | "profile"
  | "health"
  | "route"
  | "webhooks";

const PAGE_SIZE_OPTIONS = [10, 20, 25, 30, 40, 50];

function formatProfileMetadataLabel(label: string): string {
  return titleCase(label.replace(/([a-z0-9])([A-Z])/g, "$1 $2"));
}

function formatProfileMetadataValue(value: string | string[]): string {
  return Array.isArray(value) ? value.join(", ") : value;
}

function getHealthVariant(score: number): "default" | "secondary" | "destructive" | "outline" {
  if (score >= 80) {
    return "default";
  }

  if (score >= 60) {
    return "secondary";
  }

  if (score >= 40) {
    return "outline";
  }

  return "destructive";
}

function getConnectionStatusDotClassName(status: ConnectionOperationalStatus): string {
  switch (status) {
    case "available":
      return "bg-primary";
    case "blocked":
      return "bg-destructive";
    case "disabled":
      return "bg-muted-foreground/50";
  }
}

function ConnectionStatusDot({
  status,
  label,
}: {
  status: ConnectionOperationalStatus;
  label: string;
}) {
  return (
    <Popover>
      <PopoverTrigger
        nativeButton={false}
        openOnHover
        delay={100}
        closeDelay={100}
        render={
          <span
            tabIndex={0}
            title={label}
            aria-label={`Status: ${label}`}
            className={`mt-2 inline-flex size-2.5 shrink-0 rounded-full ring-2 ring-background ${getConnectionStatusDotClassName(status)}`}
          />
        }
      />
      <PopoverContent
        side="top"
        align="center"
        sideOffset={6}
        className="w-auto px-2 py-1 text-xs font-medium"
      >
        {label}
      </PopoverContent>
    </Popover>
  );
}

function normalizeConnectionRows(connections: AdminConnectionSummary[]): ConnectionTableRow[] {
  return connections.map((connection) => {
    const statusKey = getConnectionOperationalStatus(connection);
    const supportsWebhooks = supportsConnectionWebhooks(connection);
    const webhookEnabled = hasEnabledConnectionWebhooks(connection);

    return {
      ...connection,
      statusKey,
      statusLabel: getConnectionOperationalLabel(connection),
      searchText: [
        connection.name,
        connection.profile.label,
        connection.profile.key,
        connection.enabled ? "enabled" : "disabled",
        statusKey,
        connection.default ? "default" : "",
        webhookEnabled ? "webhooks enabled" : supportsWebhooks ? "webhooks disabled" : "",
        connection.unavailableReasons.join(" "),
      ]
        .join(" ")
        .toLowerCase(),
    };
  });
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

function ConnectionProfileHoverCard({
  profile,
  fullProfile,
}: {
  profile: AdminConnectionSummary["profile"];
  fullProfile: AdminConnectionProfile | undefined;
}) {
  const description =
    fullProfile?.description ?? profile.description ?? "No profile description is available.";
  const metadata = fullProfile?.metadata ?? profile.metadata;
  const supportsWebhooks = fullProfile?.supportsWebhooks ?? profile.supportsWebhooks;
  const useCases = metadata?.useCases ?? [];
  const extraEntries = Object.entries(metadata?.extra ?? {});

  return (
    <Popover>
      <PopoverTrigger
        openOnHover
        delay={150}
        closeDelay={120}
        render={
          <button
            type="button"
            className="inline-flex max-w-56 items-center gap-2 rounded-sm text-left font-medium underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
          />
        }
      >
        <ProfileBrandIcon profileKey={profile.key} label={profile.label} size="sm" />
        <span className="truncate">{profile.label}</span>
      </PopoverTrigger>
      <PopoverContent
        side="right"
        align="start"
        sideOffset={8}
        className="w-96 max-w-[calc(100vw-2rem)] overflow-hidden p-0"
      >
        <PopoverHeader className="border-b p-4">
          <div className="flex items-start gap-3">
            <ProfileBrandIcon profileKey={profile.key} label={profile.label} />
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <PopoverTitle>{profile.label}</PopoverTitle>
                {profile.missing ? <Badge variant="destructive">Missing</Badge> : null}
              </div>
              <PopoverDescription className="mt-1 leading-relaxed">
                {description}
              </PopoverDescription>
            </div>
          </div>
          {metadata?.website || metadata?.docsUrl ? (
            <div className="mt-4 flex flex-wrap gap-2">
              {metadata?.website ? (
                <a
                  href={metadata.website}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex h-8 items-center gap-2 rounded-md border bg-background px-2.5 text-sm font-medium shadow-xs transition-colors hover:bg-muted"
                >
                  <Globe02Icon aria-hidden="true" />
                  Provider site
                </a>
              ) : null}
              {metadata?.docsUrl ? (
                <a
                  href={metadata.docsUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex h-8 items-center gap-2 rounded-md border bg-background px-2.5 text-sm font-medium shadow-xs transition-colors hover:bg-muted"
                >
                  <BookOpen01Icon aria-hidden="true" />
                  Setup docs
                </a>
              ) : null}
            </div>
          ) : null}
        </PopoverHeader>

        <dl className="grid gap-px bg-border text-sm">
          {useCases.length > 0 ? (
            <div className="bg-card p-4">
              <dt className="flex items-center gap-2 text-muted-foreground">
                <Briefcase02Icon aria-hidden="true" />
                Best suited for
              </dt>
              <dd className="mt-2 font-medium text-foreground">
                {useCases.map((useCase) => titleCase(useCase)).join(", ")}
              </dd>
            </div>
          ) : null}
          <div className="bg-card p-4">
            <dt className="flex items-center gap-2 text-muted-foreground">
              <WebhookIcon aria-hidden="true" />
              Webhook support
            </dt>
            <dd className="mt-2 font-medium text-foreground">
              {supportsWebhooks ? "Available" : "Unavailable"}
            </dd>
          </div>
          {extraEntries.map(([label, value]) => (
            <div key={label} className="bg-card p-4">
              <dt className="flex items-center gap-2 text-muted-foreground">
                <InformationCircleIcon aria-hidden="true" />
                {formatProfileMetadataLabel(label)}
              </dt>
              <dd className="mt-2 font-medium text-foreground">
                {formatProfileMetadataValue(value)}
              </dd>
            </div>
          ))}
        </dl>
      </PopoverContent>
    </Popover>
  );
}

function resolveSortBy(sorting: SortingState): ConnectionSortId {
  const sortId = sorting[0]?.id;

  if (
    sortId === "name" ||
    sortId === "enabled" ||
    sortId === "default" ||
    sortId === "profile" ||
    sortId === "health" ||
    sortId === "route" ||
    sortId === "webhooks"
  ) {
    return sortId;
  }

  return "name";
}

function ConnectionsTablePagination<TData extends object>({
  table,
  totalRows,
}: {
  table: TanstackTable<DataGridFeatures, TData>;
  totalRows: number;
}) {
  return (
    <div className="flex flex-col gap-4 px-2 lg:flex-row lg:items-center lg:justify-between">
      <div className="flex-1 text-sm text-muted-foreground">
        {totalRows} connection{totalRows === 1 ? "" : "s"}
      </div>
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-end sm:gap-6 lg:gap-8">
        <div className="flex items-center gap-2">
          <p className="text-sm font-medium">Rows per page</p>
          <Select
            value={`${table.state.pagination.pageSize}`}
            onValueChange={(value) => {
              if (value) {
                table.setPageSize(Number(value));
              }
            }}
          >
            <SelectTrigger className="h-8 w-[72px]">
              <SelectValue placeholder={table.state.pagination.pageSize} />
            </SelectTrigger>
            <SelectContent side="top">
              <SelectGroup>
                {PAGE_SIZE_OPTIONS.map((pageSize) => (
                  <SelectItem key={pageSize} value={`${pageSize}`}>
                    {pageSize}
                  </SelectItem>
                ))}
              </SelectGroup>
            </SelectContent>
          </Select>
        </div>

        <div className="flex w-[108px] items-center justify-center text-sm font-medium">
          Page {table.state.pagination.pageIndex + 1} of {Math.max(1, table.getPageCount())}
        </div>

        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="icon-sm"
            className="hidden lg:flex"
            onClick={() => table.setPageIndex(0)}
            disabled={!table.getCanPreviousPage()}
          >
            <span className="sr-only">Go to first page</span>
            <ArrowLeftDoubleIcon />
          </Button>
          <Button
            type="button"
            variant="outline"
            size="icon-sm"
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage()}
          >
            <span className="sr-only">Go to previous page</span>
            <ArrowLeft01Icon />
          </Button>
          <Button
            type="button"
            variant="outline"
            size="icon-sm"
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage()}
          >
            <span className="sr-only">Go to next page</span>
            <ArrowRight01Icon />
          </Button>
          <Button
            type="button"
            variant="outline"
            size="icon-sm"
            className="hidden lg:flex"
            onClick={() => table.setPageIndex(table.getPageCount() - 1)}
            disabled={!table.getCanNextPage()}
          >
            <span className="sr-only">Go to last page</span>
            <ArrowRightDoubleIcon />
          </Button>
        </div>
      </div>
    </div>
  );
}

export function ConnectionsOverviewTable({
  profiles,
  connections,
  onOpenConnection,
  onCreateConnection,
  onEnabledChange,
  onDefaultChange,
  enabledSavingConnectionIds,
  defaultSavingConnectionId,
}: ConnectionsOverviewTableProps) {
  const [searchValue, setSearchValue] = React.useState("");
  const deferredSearchValue = React.useDeferredValue(searchValue);
  const [selectedStatuses, setSelectedStatuses] = React.useState<string[]>([]);
  const [selectedProfiles, setSelectedProfiles] = React.useState<string[]>([]);
  const [sorting, setSorting] = React.useState<SortingState>([
    {
      id: "name",
      desc: false,
    },
  ]);
  const [pagination, setPagination] = React.useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });

  const rows = React.useMemo(() => normalizeConnectionRows(connections), [connections]);
  const profilesByKey = React.useMemo(
    () => new Map(profiles.map((profile) => [profile.key, profile])),
    [profiles],
  );
  const statusOptions = React.useMemo(
    () =>
      [
        {
          label: "Available",
          value: "available",
          count: rows.filter((row) => row.statusKey === "available").length,
        },
        {
          label: "Blocked",
          value: "blocked",
          count: rows.filter((row) => row.statusKey === "blocked").length,
        },
        {
          label: "Disabled",
          value: "disabled",
          count: rows.filter((row) => row.statusKey === "disabled").length,
        },
      ].filter((option) => option.count > 0),
    [rows],
  );
  const profileOptions = React.useMemo(() => {
    const counts = new Map<string, { label: string; value: string; count: number }>();

    rows.forEach((row) => {
      const existing = counts.get(row.profile.key);

      if (existing) {
        existing.count += 1;
        return;
      }

      counts.set(row.profile.key, {
        label: row.profile.label,
        value: row.profile.key,
        count: 1,
      });
    });

    return Array.from(counts.values()).sort((left, right) => left.label.localeCompare(right.label));
  }, [rows]);
  const filteredRows = React.useMemo(() => {
    const normalizedQuery = deferredSearchValue.trim().toLowerCase();

    return rows.filter((row) => {
      const matchesSearch = normalizedQuery === "" || row.searchText.includes(normalizedQuery);
      const matchesStatus =
        selectedStatuses.length === 0 || selectedStatuses.includes(row.statusKey);
      const matchesProfile =
        selectedProfiles.length === 0 || selectedProfiles.includes(row.profile.key);

      return matchesSearch && matchesStatus && matchesProfile;
    });
  }, [deferredSearchValue, rows, selectedProfiles, selectedStatuses]);
  const sortBy = resolveSortBy(sorting);

  React.useEffect(() => {
    setPagination((currentPagination) =>
      currentPagination.pageIndex === 0
        ? currentPagination
        : {
            ...currentPagination,
            pageIndex: 0,
          },
    );
  }, [deferredSearchValue, selectedProfiles, selectedStatuses, sortBy, pagination.pageSize]);

  const columns = React.useMemo<ColumnDef<DataGridFeatures, ConnectionTableRow>[]>(
    () => [
      {
        accessorFn: (row) => (row.enabled ? "enabled" : "disabled"),
        id: "enabled",
        header: () => <span className="text-xs font-medium">Enabled</span>,
        cell: ({ row }) => {
          const connectionId = row.original.id;

          return (
            <Switch
              size="sm"
              checked={row.original.enabled}
              disabled={connectionId === null || enabledSavingConnectionIds.has(connectionId)}
              aria-label={`${row.original.enabled ? "Disable" : "Enable"} ${row.original.name}`}
              onCheckedChange={(checked) => {
                if (connectionId !== null) {
                  void onEnabledChange(connectionId, checked === true);
                }
              }}
            />
          );
        },
      },
      {
        accessorKey: "name",
        id: "name",
        header: ({ column }) => <SortableHeader column={column} title="Connection" />,
        cell: ({ row }) => (
          <div className="flex min-w-0 items-start gap-2">
            <ConnectionStatusDot status={row.original.statusKey} label={row.original.statusLabel} />
            <div className="flex min-w-0 flex-col gap-1">
              <Button
                type="button"
                variant="link"
                className="h-auto justify-start px-0 text-left"
                onClick={() => onOpenConnection(row.original.id)}
                disabled={row.original.id === null}
              >
                {row.original.name}
              </Button>
              {row.original.unavailableReasons.length > 0 ? (
                <span className="max-w-80 truncate text-xs text-muted-foreground">
                  {row.original.unavailableReasons.join(", ")}
                </span>
              ) : null}
            </div>
          </div>
        ),
      },
      {
        accessorFn: (row) => row.profile.label,
        id: "profile",
        header: ({ column }) => <SortableHeader column={column} title="Profile" />,
        cell: ({ row }) => (
          <ConnectionProfileHoverCard
            profile={row.original.profile}
            fullProfile={profilesByKey.get(row.original.profile.key)}
          />
        ),
      },
      {
        accessorKey: "healthScore",
        id: "health",
        header: ({ column }) => <SortableHeader column={column} title="Health" />,
        cell: ({ row }) => (
          <Badge variant={getHealthVariant(row.original.healthScore)}>
            {row.original.healthScore}
          </Badge>
        ),
      },
      {
        accessorFn: (row) => `${row.priority}-${row.weight}`,
        id: "route",
        header: ({ column }) => <SortableHeader column={column} title="Route" />,
        cell: ({ row }) => (
          <div className="text-sm text-muted-foreground">
            P{row.original.priority} / W{row.original.weight}
          </div>
        ),
      },
      {
        accessorFn: (row) => {
          if (!supportsConnectionWebhooks(row)) {
            return "";
          }

          return hasEnabledConnectionWebhooks(row) ? "enabled" : "disabled";
        },
        id: "webhooks",
        header: ({ column }) => <SortableHeader column={column} title="Webhooks" />,
        cell: ({ row }) =>
          !supportsConnectionWebhooks(row.original) ? null : hasEnabledConnectionWebhooks(
              row.original,
            ) ? (
            <Badge variant="secondary">
              <CheckmarkCircle02Icon data-icon="inline-start" />
              Enabled
            </Badge>
          ) : (
            <Badge variant="outline">
              <MultiplicationSignCircleIcon data-icon="inline-start" />
              Disabled
            </Badge>
          ),
      },
      {
        accessorFn: (row) => (row.default ? "default" : "secondary"),
        id: "default",
        header: () => <span className="text-xs font-medium">Default</span>,
        cell: ({ row }) => {
          const connectionId = row.original.id;
          const disabled =
            connectionId === null || !row.original.enabled || defaultSavingConnectionId !== null;

          return (
            <RadioGroupItem
              value={connectionId === null ? "" : `${connectionId}`}
              disabled={disabled}
              aria-label={
                row.original.default
                  ? `${row.original.name} is the default connection`
                  : `Make ${row.original.name} the default connection`
              }
              className="mx-auto"
            />
          );
        },
      },
    ],
    [
      defaultSavingConnectionId,
      enabledSavingConnectionIds,
      onEnabledChange,
      onOpenConnection,
      profilesByKey,
    ],
  );

  const table = useTable({
    features: dataGridFeatures,
    data: filteredRows,
    columns,
    state: {
      sorting,
      pagination,
    },
    onSortingChange: setSorting,
    onPaginationChange: setPagination,
  });

  const isFiltered =
    searchValue.trim() !== "" || selectedStatuses.length > 0 || selectedProfiles.length > 0;
  const defaultConnectionId = filteredRows.find((row) => row.default)?.id;

  if (connections.length === 0) {
    return (
      <Frame>
        <FramePanel className="p-0!">
          <Alert variant="info">
            <InformationCircleIcon />
            <AlertTitle>No connections yet</AlertTitle>
            <AlertDescription>
              Create your first provider route to start routing mail through Jooosi Mail.
            </AlertDescription>
            <AlertAction>
              <Button type="button" size="xs" onClick={onCreateConnection}>
                New connection
              </Button>
            </AlertAction>
          </Alert>
        </FramePanel>
      </Frame>
    );
  }

  return (
    <DataGrid
      table={table}
      recordCount={filteredRows.length}
      emptyMessage="No connections match the current search and filters."
      tableLayout={{ headerBackground: true, rowBorder: true }}
    >
      <Frame stacked spacing="sm">
        <FrameHeader>
          <FrameTitle>Configured routes</FrameTitle>
          <FrameDescription>
            Search, prioritize, and monitor every provider connection.
          </FrameDescription>
        </FrameHeader>
        <FramePanel className="p-0! shadow-none!">
          <div className="flex flex-wrap items-center gap-2 p-3">
            <Input
              placeholder="Search connections..."
              value={searchValue}
              onChange={(event) => setSearchValue(event.target.value)}
              className="h-8 w-full md:w-[220px]"
            />

            {statusOptions.length > 0 ? (
              <MailLogTableFacetedFilter
                title="Status"
                options={statusOptions}
                selectedValues={selectedStatuses}
                onChange={setSelectedStatuses}
              />
            ) : null}

            {profileOptions.length > 0 ? (
              <MailLogTableFacetedFilter
                title="Profile"
                options={profileOptions}
                selectedValues={selectedProfiles}
                onChange={setSelectedProfiles}
              />
            ) : null}

            {isFiltered ? (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => {
                  setSearchValue("");
                  setSelectedStatuses([]);
                  setSelectedProfiles([]);
                }}
              >
                Reset
              </Button>
            ) : null}
          </div>

          <Separator />

          <RadioGroup
            value={
              defaultConnectionId === null || defaultConnectionId === undefined
                ? ""
                : `${defaultConnectionId}`
            }
            onValueChange={(value) => {
              if (/^\d+$/.test(value)) {
                void onDefaultChange(Number(value));
              }
            }}
            className="contents"
          >
            <DataGridTable />
          </RadioGroup>
        </FramePanel>
        <FrameFooter>
          <ConnectionsTablePagination table={table} totalRows={filteredRows.length} />
        </FrameFooter>
      </Frame>
    </DataGrid>
  );
}

export default ConnectionsOverviewTable;
