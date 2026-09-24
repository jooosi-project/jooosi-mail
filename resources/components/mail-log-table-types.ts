import type { AdminMailLog } from "@/lib/admin-api"
import { parseAdminDateTime } from "@/lib/admin-format"

export type MailLogDateRangeFilter = {
  from?: string
  to?: string
}

export type MailLogTableRow = AdminMailLog & {
  dateTime: string | null
  connectionLabel: string
  toSummary: string
  searchText: string
}

function stripHtmlTags(value: string): string {
  return value.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim()
}

function resolveMailLogDateTime(log: AdminMailLog): string | null {
  return log.sentAt ?? log.queuedAt ?? log.createdAt ?? log.updatedAt
}

function formatAddressSummary(addresses: string[]): string {
  if (addresses.length === 0) {
    return "-"
  }

  if (addresses.length === 1) {
    return addresses[0]
  }

  return `${addresses[0]} +${addresses.length - 1} more`
}

function buildSearchText(log: AdminMailLog): string {
  return [
    log.subject,
    log.toAddresses.join(" "),
    log.textBody ?? "",
    log.htmlBody ? stripHtmlTags(log.htmlBody) : "",
  ]
    .join(" ")
    .toLowerCase()
}

export function normalizeMailLogRows(data: AdminMailLog[]): MailLogTableRow[] {
  return data.map((log) => ({
    ...log,
    dateTime: resolveMailLogDateTime(log),
    connectionLabel: log.connectionName ?? "Unassigned",
    toSummary: formatAddressSummary(log.toAddresses),
    searchText: buildSearchText(log),
  }))
}

function parseDateBoundary(value: string | undefined, endOfDay: boolean): number | null {
  if (!value) {
    return null
  }

  const suffix = endOfDay ? "T23:59:59.999" : "T00:00:00.000"
  return parseAdminDateTime(`${value}${suffix}`)?.getTime() ?? null
}

export function isWithinMailLogDateRange(
  row: MailLogTableRow,
  range?: MailLogDateRangeFilter,
): boolean {
  if (!range?.from && !range?.to) {
    return true
  }

  if (!row.dateTime) {
    return false
  }

  const rowDate = parseAdminDateTime(row.dateTime)

  if (rowDate === null) {
    return false
  }

  const fromBoundary = parseDateBoundary(range.from, false)
  const toBoundary = parseDateBoundary(range.to, true)

  if (fromBoundary !== null && rowDate.getTime() < fromBoundary) {
    return false
  }

  if (toBoundary !== null && rowDate.getTime() > toBoundary) {
    return false
  }

  return true
}

export function formatAddressList(addresses: string[]): string {
  return addresses.length > 0 ? addresses.join(", ") : "-"
}
