"use client";

import type { Table } from "@tanstack/react-table";

import type { DataGridFeatures } from "@/components/reui/data-grid/data-grid";
import type { AdminQueueMessage } from "@/lib/admin-api";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import LeftToRightListBulletIcon from "~icons/hugeicons/left-to-right-list-bullet";

const TOGGLEABLE_COLUMNS = {
  id: "ID",
  mailLogId: "Mail",
  status: "Status",
  priority: "Priority",
  attempts: "Attempts",
  dateTime: "Date",
} as const;

export function QueueLogTableViewOptions({
  table,
}: {
  table: Table<DataGridFeatures, AdminQueueMessage>;
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={<Button variant="outline" size="sm" className="ml-auto h-8 rounded-md px-2.5" />}
      >
        <LeftToRightListBulletIcon data-icon="inline-start" />
        View
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-44">
        <DropdownMenuGroup>
          <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
        <DropdownMenuGroup>
          {table
            .getAllColumns()
            .filter((column) => column.id in TOGGLEABLE_COLUMNS)
            .map((column) => (
              <DropdownMenuCheckboxItem
                key={column.id}
                checked={column.getIsVisible()}
                onCheckedChange={() => column.toggleVisibility(!column.getIsVisible())}
              >
                {TOGGLEABLE_COLUMNS[column.id as keyof typeof TOGGLEABLE_COLUMNS]}
              </DropdownMenuCheckboxItem>
            ))}
        </DropdownMenuGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export default QueueLogTableViewOptions;
