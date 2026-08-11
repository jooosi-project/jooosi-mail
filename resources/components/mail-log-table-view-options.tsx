"use client";

import type { Table } from "@tanstack/react-table";

import type { MailLogTableRow } from "@/components/mail-log-table-types";
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
  subject: "Subject",
  to: "To Email Address",
  status: "Status",
  dateTime: "Date Time",
  connection: "Connection",
} as const;

export function MailLogTableViewOptions({
  table,
}: {
  table: Table<DataGridFeatures, MailLogTableRow>;
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

export default MailLogTableViewOptions;
