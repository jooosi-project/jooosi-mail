import * as React from "react"
import { createRoot } from "react-dom/client"

import { useAdminLogQuery } from "../../resources/hooks/use-admin-log-query"
import { getMailLogs, getQueueLogs, getWebhookLogs } from "../../resources/lib/admin-api"
import { formatAdminDateTime, parseAdminDateTime } from "../../resources/lib/admin-format"

type Query = { search: string; page: number; perPage: number }
type Page = {
  items: Array<{ id: number }>
  pagination: { page: number; perPage: number; total: number; totalPages: number }
}
type Loader = (query: Query, signal?: AbortSignal) => Promise<Page>

function assert(condition: unknown, message: string): void {
  if (!condition) throw new Error(message)
}

async function setup(loader: Loader) {
  const originalFetch = window.fetch
  const originalSetInterval = window.setInterval
  const originalClearInterval = window.clearInterval
  const originalRuntime = window.jooosiMailAdmin
  const requests: Array<{
    url: URL
    signal: AbortSignal
    resolve: (page: number, total?: number) => void
    reject: (error: Error) => void
  }> = []
  let poll: (() => void) | undefined
  let cleared = false
  window.setInterval = ((callback: () => void) => { poll = callback; return 456 }) as typeof window.setInterval
  window.clearInterval = (id) => { cleared = id === 456 }
  window.jooosiMailAdmin = { apiRoot: "https://example.test/admin/", nonce: "test", pluginVersion: "test" }
  window.fetch = (input, init) => new Promise<Response>((resolve, reject) => {
    requests.push({
      url: new URL(String(input)),
      signal: init?.signal as AbortSignal,
      resolve: (page, total = 101) => resolve(new Response(JSON.stringify({
        items: [{ id: total }],
        filters: { statuses: [], connections: [], eventTypes: [] },
        pagination: { page, perPage: 25, total, totalPages: Math.ceil(total / 25) },
      }), { status: 200 })),
      reject,
    })
  })

  let search = ""
  let refreshToken = 0
  let result!: ReturnType<typeof useAdminLogQuery<Query, Page>>
  let pagination!: { pageIndex: number; pageSize: number }
  let changePage!: (index: number) => void

  function Probe() {
    const [state, setPagination] = React.useState({ pageIndex: 0, pageSize: 25 })
    pagination = state
    changePage = (pageIndex) => setPagination((current) => ({ ...current, pageIndex }))
    const query = React.useMemo(() => ({ search, page: state.pageIndex + 1, perPage: state.pageSize }), [search, state])
    result = useAdminLogQuery(loader, query, { refreshToken, setPagination, errorMessage: "Could not load logs." })
    return null
  }

  const container = document.createElement("div")
  document.body.append(container)
  const root = createRoot(container)
  const render = () => React.act(async () => root.render(React.createElement(Probe)))
  await render()

  return {
    requests,
    get result() { return result },
    get pagination() { return pagination },
    poll: () => React.act(async () => { poll?.() }),
    async search(value: string) { search = value; await render() },
    async refresh() { refreshToken += 1; await render() },
    selectPage: (index: number) => changePage(index),
    page: (index: number) => React.act(async () => { changePage(index) }),
    async close() {
      await React.act(async () => root.unmount())
      container.remove()
      window.fetch = originalFetch
      window.setInterval = originalSetInterval
      window.clearInterval = originalClearInterval
      window.jooosiMailAdmin = originalRuntime
      assert(cleared, "Polling timer was not removed")
    },
  }
}

export const adminLogQueryTests: Array<[string, () => Promise<void>]> = []

for (const [endpoint, loader] of [
  ["mail", getMailLogs],
  ["queue", getQueueLogs],
  ["webhooks", getWebhookLogs],
] as const) {
  adminLogQueryTests.push([`${endpoint}: slow responses settle, polling stays in background, and refresh is foreground`, async () => {
    const hook = await setup(loader)
    try {
      assert(hook.requests[0].url.pathname === `/admin/logs/${endpoint}`, "Wrong API endpoint")
      await hook.poll()
      await hook.poll()
      assert(hook.requests.length === 1, "Slow request was replaced by polling")
      await React.act(async () => hook.requests[0].resolve(1))
      assert(hook.result.data?.items[0].id === 101 && !hook.result.loading, "Slow response was not rendered")
      await hook.poll()
      assert(hook.requests.length === 2, "Polling did not resume")
      assert(!hook.result.loading && hook.result.refreshing, "Polling hid existing rows")
      await hook.refresh()
      assert(hook.requests[1].signal.aborted, "Refresh did not cancel a pending poll")
      assert(hook.result.loading && hook.result.data?.items[0].id === 101, "Refresh lost foreground loading or existing rows")
      await React.act(async () => hook.requests[2].resolve(1, 102))
      await React.act(async () => hook.requests[1].resolve(1, 99))
      assert(hook.result.data?.items[0].id === 102 && !hook.result.loading, "Old poll replaced refreshed rows")
      await hook.poll()
    } finally {
      await hook.close()
    }
    assert(hook.requests.at(-1)?.signal.aborted, "Unmount did not cancel the pending request")
  }])

  adminLogQueryTests.push([`${endpoint}: rapid filters cancel old requests, preserve errors, and apply server pagination`, async () => {
    const hook = await setup(loader)
    try {
      await hook.search("old filter")
      await hook.search("current filter")
      assert(hook.requests[0].signal.aborted && hook.requests[1].signal.aborted, "Filter changes did not cancel requests")
      assert(hook.requests[2].url.searchParams.get("search") === "current filter", "Latest filter was not requested")
      await React.act(async () => hook.requests[2].resolve(1))
      await React.act(async () => hook.requests[0].resolve(1, 77))
      await React.act(async () => hook.requests[1].reject(new Error("old failure")))
      assert(hook.result.data?.items[0].id === 101 && hook.result.error === null, "Old filter changed current rows or error")
      await hook.page(8)
      assert(hook.requests[3].url.searchParams.get("page") === "9", "Requested page was not sent")
      await React.act(async () => hook.requests[3].resolve(2, 30))
      assert(hook.pagination.pageIndex === 1, "Server-clamped page was not synchronized")
      assert(hook.requests[4].url.searchParams.get("page") === "2", "Next request did not use the clamped page")
      await React.act(async () => hook.requests[4].reject(new Error("current failure")))
      assert(hook.result.error === "current failure", "Latest error was not displayed")
      assert(hook.result.data?.pagination.total === 30 && !hook.result.loading, "Error removed previous page or left loading active")
      await hook.refresh()
      assert(hook.result.error === null, "Retry did not reset the error")
      await React.act(async () => hook.requests[5].resolve(2, 31))
      assert(hook.result.data?.pagination.total === 31, "Retry did not recover")
    } finally {
      await hook.close()
    }
  }])

  adminLogQueryTests.push([`${endpoint}: a completed poll cannot undo a page selected in the same render`, async () => {
    for (const selectFirst of [false, true]) {
      const hook = await setup(loader)
      try {
        await React.act(async () => hook.requests[0].resolve(1))
        await hook.poll()
        await React.act(async () => {
          if (selectFirst) hook.selectPage(1)
          hook.requests[1].resolve(1, 102)
          for (let index = 0; index < 10; index += 1) await Promise.resolve()
          if (!selectFirst) hook.selectPage(1)
        })
        assert(hook.pagination.pageIndex === 1, `A completed poll reset the newly selected page (select first: ${selectFirst})`)
        assert(hook.requests.at(-1)?.url.searchParams.get("page") === "2", "Latest request reverted to the previous page")
      } finally {
        await hook.close()
      }
    }
  }])
}

adminLogQueryTests.push(["SQL timestamps use UTC while ISO offsets and invalid-value fallbacks are preserved", async () => {
  const timezone = document.documentElement.dataset.testTimezone
  if (timezone) {
    assert(Intl.DateTimeFormat().resolvedOptions().timeZone === timezone, `Browser did not use the requested timezone: ${timezone}`)
  }
  const expected = Date.UTC(2026, 8, 7, 12, 34, 56)
  assert(parseAdminDateTime("2026-09-07 12:34:56")?.getTime() === expected, "SQL timestamp was parsed as local time")
  assert(parseAdminDateTime("2026-09-07T12:34:56")?.getTime() === expected, "Offset-free ISO timestamp was parsed as local time")
  assert(parseAdminDateTime("2026-09-07 12:34:56.123")?.getTime() === expected + 123, "Fractional seconds were lost")
  assert(parseAdminDateTime("2026-09-07T19:34:56+07:00")?.getTime() === expected, "Positive ISO offset was changed")
  assert(parseAdminDateTime("2026-09-07T05:34:56-07:00")?.getTime() === expected, "Negative ISO offset was changed")
  assert(parseAdminDateTime("2026-09-07T12:34:56Z")?.getTime() === expected, "UTC ISO timestamp was changed")
  assert(parseAdminDateTime("invalid") === null && parseAdminDateTime(null) === null, "Invalid timestamp was accepted")
  assert(formatAdminDateTime("invalid") === "invalid" && formatAdminDateTime(null) === "-", "Display fallbacks changed")
  const display = new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(new Date(expected))
  assert(formatAdminDateTime("2026-09-07 12:34:56") === display, "SQL display did not convert UTC to browser time")
  assert(expected + 300_000 - (parseAdminDateTime("2026-09-07 12:34:56")?.getTime() ?? 0) === 300_000, "Queue claim age depends on browser timezone")
}])
