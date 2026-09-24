export function parseAdminDateTime(value: string | null | undefined): Date | null {
  if (!value) {
    return null
  }

  // SQL timestamps from the admin API are UTC even though they omit an offset.
  const normalizedValue = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(value)
    ? `${value.replace(" ", "T")}Z`
    : value
  const parsedValue = new Date(normalizedValue)

  return Number.isNaN(parsedValue.getTime()) ? null : parsedValue
}

export function formatAdminDateTime(value: string | null | undefined): string {
  const parsedValue = parseAdminDateTime(value)

  if (parsedValue === null) {
    return value || "-"
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(parsedValue)
}

export function formatAdminNumber(value: number): string {
  return new Intl.NumberFormat().format(value)
}

export function titleCase(value: string): string {
  return value
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (character) => character.toUpperCase())
}
