"use client";

import type { ReactTable, RowData } from "@tanstack/react-table";
import type { DataGridFeatures } from "@/components/reui/data-grid/data-grid";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import ArrowLeft01Icon from "~icons/hugeicons/arrow-left-01";
import ArrowLeftDoubleIcon from "~icons/hugeicons/arrow-left-double";
import ArrowRight01Icon from "~icons/hugeicons/arrow-right-01";
import ArrowRightDoubleIcon from "~icons/hugeicons/arrow-right-double";

const PAGE_SIZE_OPTIONS = [10, 20, 25, 30, 40, 50];

export function MailLogTablePagination<TData extends RowData>({
  table,
  totalRows,
}: {
  table: ReactTable<DataGridFeatures, TData>;
  totalRows: number;
}) {
  return (
    <div className="flex flex-col gap-4 px-2 lg:flex-row lg:items-center lg:justify-between">
      <div className="flex-1 text-sm text-muted-foreground">
        {table.getSelectedRowModel().rows.length} of {totalRows} row(s) selected.
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
            items={PAGE_SIZE_OPTIONS.map((pageSize) => ({
              label: `${pageSize}`,
              value: `${pageSize}`,
            }))}
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
            variant="outline"
            size="icon-sm"
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage()}
          >
            <span className="sr-only">Go to previous page</span>
            <ArrowLeft01Icon />
          </Button>
          <Button
            variant="outline"
            size="icon-sm"
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage()}
          >
            <span className="sr-only">Go to next page</span>
            <ArrowRight01Icon />
          </Button>
          <Button
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

export default MailLogTablePagination;
