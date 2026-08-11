"use client";

import type { Table } from "@tanstack/react-table";

import type { WebhookLogTableRow } from "@/components/webhook-log-table-types";
import type { DataGridFeatures } from "@/components/reui/data-grid/data-grid";
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
  eventType: "Event",
  connection: "Connection",
  mailLogId: "Mail",
  transportMessageId: "Transport Message",
  dateTime: "Occurred",
} as const;

export function WebhookLogTableViewOptions({
  table,
}: {
  table: Table<DataGridFeatures, WebhookLogTableRow>;
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={<Button variant="outline" size="sm" className="ml-auto h-8 rounded-md px-2.5" />}
      >
        <LeftToRightListBulletIcon data-icon="inline-start" />
        View
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-48">
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

export default WebhookLogTableViewOptions;
