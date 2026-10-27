"use client";

import { createContext, useContext, useEffect, useMemo, useRef } from "react";
import type { ReactNode } from "react";
import {
  columnFacetingFeature,
  columnFilteringFeature,
  columnOrderingFeature,
  columnPinningFeature,
  columnResizingFeature,
  columnSizingFeature,
  columnVisibilityFeature,
  createExpandedRowModel,
  createFacetedRowModel,
  createFacetedUniqueValues,
  createFilteredRowModel,
  createPaginatedRowModel,
  createSortedRowModel,
  globalFilteringFeature,
  metaHelper,
  rowExpandingFeature,
  rowPaginationFeature,
  rowPinningFeature,
  rowSelectionFeature,
  rowSortingFeature,
  sortFn_basic,
  sortFn_text,
  tableFeatures,
} from "@tanstack/react-table";
import type {
  Column,
  ColumnFiltersState,
  ReactTable,
  RowData,
  SortingState,
  Table,
  TableFeatures,
} from "@tanstack/react-table";

import { cn } from "@/lib/utils";

/**
 * Per-column extras the grid reads off `columnDef.meta`.
 *
 * TanStack v9 resolves this through the `columnMeta` slot on the feature
 * bundle below instead of a global `declare module` augmentation, so
 * installing the data grid no longer widens `ColumnMeta` for every other
 * table in the consuming app.
 */
export interface DataGridColumnMeta<TData> {
  headerTitle?: string;
  headerClassName?: string;
  cellClassName?: string;
  skeleton?: ReactNode;
  expandedContent?: (row: TData) => ReactNode;
  autoSize?: boolean;
}

/**
 * The batteries-included feature bundle every ReUI data-grid example builds
 * on. v9 requires each table to declare its features up front, and the grid's
 * render path needs the ones registered here: `columnVisibilityFeature` alone
 * gates `row.getVisibleCells()`, so even a grid that never hides a column
 * needs it to render at all.
 *
 * Pass it straight through for the full grid:
 *
 * ```tsx
 * const table = useTable({ features: dataGridFeatures, columns, data })
 * ```
 *
 * Extend it when a grid needs more, keeping each prerequisite feature ahead of
 * the slot that depends on it:
 *
 * ```tsx
 * const features = tableFeatures({
 *   ...dataGridFeatures,
 *   columnGroupingFeature,
 *   groupedRowModel: createGroupedRowModel(),
 * })
 * ```
 *
 * Or drop it entirely and hand `<DataGrid>` a leaner table - the components
 * accept any bundle, so you keep full ownership of the TanStack core.
 */
export const dataGridFeatures = tableFeatures({
  columnVisibilityFeature,
  columnOrderingFeature,
  columnPinningFeature,
  columnSizingFeature,
  // columnResizingFeature requires columnSizingFeature, declared above.
  columnResizingFeature,
  columnFilteringFeature,
  // Powers DataGridColumnFilter's column.getFacetedUniqueValues(). On v8 an
  // unregistered facet silently returned an empty map; on v9 the method would
  // not exist at all, so the faceted row models below are required, not
  // optional.
  columnFacetingFeature,
  // globalFilteringFeature requires columnFilteringFeature, declared above.
  globalFilteringFeature,
  rowSortingFeature,
  rowPaginationFeature,
  rowSelectionFeature,
  rowExpandingFeature,
  rowPinningFeature,
  sortedRowModel: createSortedRowModel(),
  filteredRowModel: createFilteredRowModel(),
  paginatedRowModel: createPaginatedRowModel(),
  expandedRowModel: createExpandedRowModel(),
  facetedRowModel: createFacetedRowModel(),
  facetedUniqueValues: createFacetedUniqueValues(),
  // Only the built-ins the registry names by string. v9 resolves string
  // sortFn names against this map alone, and registering them one by one
  // keeps every other built-in out of the bundle.
  sortFns: { basic: sortFn_basic, text: sortFn_text },
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  columnMeta: metaHelper<DataGridColumnMeta<any>>(),
});

/** The feature set `dataGridFeatures` registers. */
export type DataGridFeatures = typeof dataGridFeatures;

/**
 * The grid's internal view of the table.
 *
 * `TFeatures` is invariant in v9 and an unresolved generic one collapses to a
 * union that includes the bare core arm, so no generic signature can call
 * `getVisibleCells()`, `getStartVisibleLeafColumns()` and friends. The public
 * components stay generic so consumers can pass any bundle they like; the
 * table is widened to this concrete type exactly once, on the way into
 * context, and every internal component reads it from there.
 */
export type DataGridTableInstance<TData extends object> = ReactTable<DataGridFeatures, TData>;

/** Label for headers / column visibility: `meta.headerTitle`, string `columnDef.header`, or `column.id`. */
export function getColumnHeaderLabel<TData extends RowData, TValue>(
  column: Column<DataGridFeatures, TData, TValue>,
): string {
  const meta = column.columnDef.meta as { headerTitle?: string } | undefined;
  if (typeof meta?.headerTitle === "string") return meta.headerTitle;
  const defHeader = column.columnDef.header;
  if (typeof defHeader === "string") return defHeader;
  return String(column.id);
}

export type DataGridApiFetchParams = {
  pageIndex: number;
  pageSize: number;
  sorting?: SortingState;
  filters?: ColumnFiltersState;
  searchQuery?: string;
};

export type DataGridApiResponse<T> = {
  data: T[];
  empty: boolean;
  pagination: {
    total: number;
    page: number;
  };
};

/**
 * Everything `<DataGrid>` accepts except the two props the provider consumes
 * itself. Kept feature-agnostic: layout and messaging never depend on which
 * TanStack features the consumer registered.
 */
export type DataGridLayoutProps<TData extends object> = Omit<
  DataGridProps<TableFeatures, TData>,
  "table" | "children"
>;

export interface DataGridContextProps<TData extends object> {
  props: DataGridLayoutProps<TData>;
  table: DataGridTableInstance<TData>;
  recordCount: number;
  isLoading: boolean;
  /**
   * Internal coordinator for `meta.autoSize` columns. Lives at the core level
   * so every table variant and viewport instance shares one application state.
   */
  autoSize?: DataGridAutoSizeController;
}

export type DataGridAutoSizeController = {
  /**
   * Shares the measured free space between visible `meta.autoSize` columns.
   * Reconciles automatic sizing when the viewport or visibility changes, but
   * leaves user-resized columns untouched. Returns true when sizing changed.
   */
  apply: (widthDelta: number) => boolean;
};

function createDataGridAutoSizeController<TData extends object>(
  /**
   * A getter, not the table itself.
   *
   * v8 handed back one stable table whose state mutated in place, so a
   * controller could close over it. v9 returns a NEW table wrapper on every
   * state change, and a captured one keeps reporting the state it was built
   * with - here that meant `columnSizing` looked permanently empty, the
   * applied-once guard re-armed on every measurement, and the fill overwrote
   * whatever width the user had just dragged the column to.
   */
  getTable: () => DataGridTableInstance<TData>,
): DataGridAutoSizeController {
  const applied = new Map<string, { base: number; grown: number }>();

  return {
    apply(widthDelta: number) {
      const table = getTable();
      const columnSizing = table.state.columnSizing;
      const autoSizeColumns = table
        .getVisibleLeafColumns()
        .filter((column) => column.columnDef.meta?.autoSize && column.getCanResize());
      const visibleAutoSizeColumnIds = new Set(autoSizeColumns.map((column) => column.id));
      const sizingUpdates = new Map<string, number | null>();

      applied.forEach((sizing, columnId) => {
        const column = table.getColumn(columnId);

        if (!column) {
          applied.delete(columnId);
          return;
        }

        const currentSize = columnSizing[columnId] ?? column.getSize();

        // A size change the controller did not write belongs to the user.
        if (currentSize !== sizing.grown) {
          applied.delete(columnId);
          return;
        }

        if (!visibleAutoSizeColumnIds.has(columnId) && currentSize !== sizing.base) {
          sizingUpdates.set(columnId, null);
          applied.set(columnId, { ...sizing, grown: sizing.base });
        }
      });

      const managedColumns = autoSizeColumns.flatMap((column) => {
        const sizing = applied.get(column.id);
        const currentSize = columnSizing[column.id] ?? column.getSize();

        if (sizing) {
          if (currentSize !== sizing.grown) {
            applied.delete(column.id);
            return [];
          }

          return [{ column, base: sizing.base, current: currentSize }];
        }

        // Existing sizing state is user-owned, including state restored after
        // a remount where this controller no longer knows its prior writes.
        if (columnSizing[column.id] !== undefined) return [];

        const base = column.getSize();
        applied.set(column.id, { base, grown: base });

        return [{ column, base, current: base }];
      });

      const nextSizes = new Map(managedColumns.map(({ column, current }) => [column.id, current]));
      let remainingDelta = widthDelta;
      let adjustableColumns = managedColumns.filter(({ column, base, current }) => {
        const maxSize = column.columnDef.maxSize ?? Number.POSITIVE_INFINITY;

        return remainingDelta >= 0 ? current < maxSize : current > base;
      });

      // Distribute space evenly, redistributing any remainder when a column
      // reaches its max size or its original size while shrinking.
      while (adjustableColumns.length > 0 && Math.abs(remainingDelta) > 0.01) {
        const deltaPerColumn = remainingDelta / adjustableColumns.length;
        let distributedDelta = 0;
        const remainingColumns: typeof adjustableColumns = [];

        adjustableColumns.forEach((item) => {
          const current = nextSizes.get(item.column.id) ?? item.current;
          const maxSize = item.column.columnDef.maxSize ?? Number.POSITIVE_INFINITY;
          const next = Math.min(maxSize, Math.max(item.base, current + deltaPerColumn));

          nextSizes.set(item.column.id, next);
          distributedDelta += next - current;

          if (remainingDelta >= 0 ? next < maxSize : next > item.base) {
            remainingColumns.push(item);
          }
        });

        if (Math.abs(distributedDelta) <= 0.01) break;

        remainingDelta -= distributedDelta;
        adjustableColumns = remainingColumns;
      }

      managedColumns.forEach(({ column, base, current }) => {
        const grown = Math.max(base, nextSizes.get(column.id) ?? current);
        const nextSize = grown > base ? grown : null;
        const sizing = applied.get(column.id);

        if (sizing) {
          applied.set(column.id, { ...sizing, grown });
        }

        if (grown !== current) {
          sizingUpdates.set(column.id, nextSize);
        }
      });

      if (sizingUpdates.size === 0) return false;

      table.setColumnSizing((old) => {
        const next = { ...old };

        sizingUpdates.forEach((size, columnId) => {
          if (size === null) {
            delete next[columnId];
          } else {
            next[columnId] = size;
          }
        });

        return next;
      });

      return true;
    },
  };
}

export type DataGridRequestParams = {
  pageIndex: number;
  pageSize: number;
  sorting?: SortingState;
  columnFilters?: ColumnFiltersState;
};

export interface DataGridProps<TFeatures extends TableFeatures, TData extends object> {
  className?: string;
  table?: Table<TFeatures, TData>;
  recordCount: number;
  children?: ReactNode;
  onRowClick?: (row: TData) => void;
  isLoading?: boolean;
  loadingMode?: "skeleton" | "spinner";
  loadingMessage?: ReactNode | string;
  fetchingMoreMessage?: ReactNode | string;
  allRowsLoadedMessage?: ReactNode | string;
  emptyMessage?: ReactNode | string;
  tableLayout?: {
    dense?: boolean;
    cellBorder?: boolean;
    rowBorder?: boolean;
    rowRounded?: boolean;
    stripped?: boolean;
    headerBackground?: boolean;
    footerBackground?: boolean;
    headerBorder?: boolean;
    headerSticky?: boolean;
    width?: "auto" | "fixed";
    columnsVisibility?: boolean;
    columnsResizable?: boolean;
    columnsResizeMode?: "onChange" | "onEnd";
    columnsPinnable?: boolean;
    columnsMovable?: boolean;
    columnsDraggable?: boolean;
    rowsDraggable?: boolean;
    rowsPinnable?: boolean;
  };
  tableClassNames?: {
    base?: string;
    header?: string;
    headerRow?: string;
    headerSticky?: string;
    body?: string;
    bodyRow?: string;
    footer?: string;
    edgeCell?: string;
  };
}

const DataGridContext = createContext<
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  DataGridContextProps<any> | undefined
>(undefined);

/**
 * Reads the grid context. Pass `TData` from the calling component when the
 * table, a row or a cell is handed on to something typed against that row
 * shape: v9 declares `TData` invariant, so the default `any` no longer
 * unifies with a concrete row type the way it did on v8.
 */
function useDataGrid<
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  TData extends object = any,
>(): DataGridContextProps<TData> {
  const context = useContext(DataGridContext) as DataGridContextProps<TData> | undefined;
  if (!context) {
    throw new Error("useDataGrid must be used within a DataGridProvider");
  }
  return context;
}

function DataGridProvider<TData extends object>({
  children,
  table,
  ...props
}: DataGridLayoutProps<TData> & {
  table: DataGridTableInstance<TData>;
  children?: ReactNode;
}) {
  // Latest-props ref: context reads always resolve fresh props through the
  // getter below without the memoized context value depending on unstable
  // ReactNode/function prop identities (inline emptyMessage/onRowClick would
  // otherwise publish a new context value on every consumer render - at
  // mousemove rate during a resize drag, piercing the body-rows memo).
  const propsRef = useRef(props);
  propsRef.current = props;

  // Same treatment for the table itself, which v9 - unlike v8 - re-creates on
  // every state change. Depending on it directly would republish the context
  // on each resize tick, which is exactly what the memo below exists to
  // prevent; the getter still hands every consumer the current instance.
  const tableRef = useRef(table);
  tableRef.current = table;

  // Re-assert an explicit tableLayout resize mode so consumer-level useTable
  // options cannot flip it back between drags. v9 makes `table.options`
  // readonly, so this goes through setOptions in an effect rather than a
  // render-phase mutation. Without an explicit mode, the consumer's own
  // tanstack columnResizeMode (default "onEnd") is honored.
  const resizeMode =
    props.tableLayout?.columnsResizable && props.tableLayout.columnsResizeMode
      ? props.tableLayout.columnsResizeMode
      : undefined;

  useEffect(() => {
    if (!resizeMode) return;
    if (table.options.columnResizeMode === resizeMode) return;
    table.setOptions((old) => ({ ...old, columnResizeMode: resizeMode }));
  }, [table, resizeMode]);

  // One autoSize coordinator per table instance so split header/body viewports
  // cannot apply the growth twice. Keyed on `table.store`, which v9 keeps
  // stable for the life of the table, rather than on `table` itself: the
  // wrapper is re-created on every state change, and re-creating the
  // controller with it would reset its applied-once bookkeeping mid-drag.
  const autoSize = useMemo(
    () => createDataGridAutoSizeController(() => tableRef.current),
    [table.store],
  );

  const tableState = table.state;

  // Memoize context value so consumers don't re-render during column resize.
  // Column sizing state is intentionally excluded from deps -- CSS variables
  // on the <table> element handle width updates without React re-renders.
  // ReactNode/function props (messages, onRowClick) are also excluded: they
  // are served fresh through the props getter, so unstable inline identities
  // cannot invalidate the context value.
  const value = useMemo(
    () => ({
      get props() {
        return propsRef.current;
      },
      get table() {
        return tableRef.current;
      },
      recordCount: props.recordCount,
      isLoading: props.isLoading || false,
      autoSize,
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [
      autoSize,
      props.recordCount,
      props.isLoading,
      props.loadingMode,
      props.className,
      // eslint-disable-next-line react-hooks/exhaustive-deps
      JSON.stringify(props.tableLayout),
      // eslint-disable-next-line react-hooks/exhaustive-deps
      JSON.stringify(props.tableClassNames),
      tableState.sorting,
      tableState.pagination,
      tableState.columnFilters,
      tableState.rowSelection,
      tableState.rowPinning,
      tableState.expanded,
      tableState.columnVisibility,
      tableState.columnOrder,
      tableState.columnPinning,
      tableState.globalFilter,
    ],
  );

  return (
    // One React context serves every TData, but v9 declares both TFeatures and
    // TData invariant, so a `DataGridContextProps<any>` context cannot accept a
    // `DataGridContextProps<TData>` value structurally. The erasure happens
    // here and is undone by the TData generic on each consumer component.
    <DataGridContext.Provider value={value as unknown as DataGridContextProps<TData>}>
      {children}
    </DataGridContext.Provider>
  );
}

function DataGrid<TFeatures extends TableFeatures, TData extends object>({
  children,
  table,
  ...props
}: DataGridProps<TFeatures, TData>) {
  const defaultProps: Partial<DataGridProps<TFeatures, TData>> = {
    loadingMode: "skeleton",
    tableLayout: {
      dense: false,
      cellBorder: false,
      rowBorder: true,
      rowRounded: false,
      stripped: false,
      headerSticky: false,
      headerBackground: false,
      footerBackground: false,
      headerBorder: true,
      width: "fixed",
      columnsVisibility: false,
      columnsResizable: false,
      // columnsResizeMode has no default on purpose: when unset, the
      // consumer's tanstack columnResizeMode (default "onEnd") is honored.
      columnsPinnable: false,
      columnsMovable: false,
      columnsDraggable: false,
      rowsDraggable: false,
      rowsPinnable: false,
    },
    tableClassNames: {
      base: "",
      header: "",
      headerRow: "",
      // z-40 keeps the sticky header above pinned body cells (zIndex 30 in
      // getPinningStyles), which would otherwise paint over it while
      // scrolling vertically with columnsPinnable enabled.
      headerSticky: "sticky top-0 z-40 bg-background/90 backdrop-blur-xs",
      body: "",
      bodyRow: "",
      footer: "",
      edgeCell: "",
    },
  };

  const mergedProps: DataGridProps<TFeatures, TData> = {
    ...defaultProps,
    ...props,
    tableLayout: {
      ...defaultProps.tableLayout,
      ...props.tableLayout,
    },
    tableClassNames: {
      ...defaultProps.tableClassNames,
      ...props.tableClassNames,
    },
  };

  // Ensure table is provided
  if (!table) {
    throw new Error('DataGrid requires a "table" prop');
  }

  // The single widening point. Consumers own the TanStack core and may hand
  // over any feature bundle; internals need a concrete one to resolve the
  // feature-gated APIs they call, and v9's invariant TFeatures rules out
  // expressing that with a generic constraint.
  const internalTable = table as unknown as DataGridTableInstance<TData>;
  const internalProps = mergedProps as unknown as DataGridLayoutProps<TData>;

  return (
    <DataGridProvider table={internalTable} {...internalProps}>
      {children}
    </DataGridProvider>
  );
}

function DataGridContainer({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
  /** Accepted for backwards compatibility; currently has no effect. */
  border?: boolean;
}) {
  return (
    <div data-slot="data-grid" className={cn("w-full overflow-hidden", className)}>
      {children}
    </div>
  );
}

export { useDataGrid, DataGridProvider, DataGrid, DataGridContainer };
