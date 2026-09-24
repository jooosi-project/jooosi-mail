import * as React from "react"
import { createRoot } from "react-dom/client"

import { useAdminQuery } from "../../resources/hooks/use-admin-query"
import { adminLogQueryTests } from "./admin-log-query.browser"

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true })

function assert(condition: unknown, message: string): void {
  if (!condition) {
    throw new Error(message)
  }
}

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: Error) => void
  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise
    reject = rejectPromise
  })

  return { promise, resolve, reject }
}

async function setup(options: { pollMs?: number; strict?: boolean } = {}) {
  const requests: ReturnType<typeof deferred<string>>[] = []
  const loader = () => {
    const request = deferred<string>()
    requests.push(request)
    return request.promise
  }
  let query!: ReturnType<typeof useAdminQuery<string>>

  function Probe() {
    query = useAdminQuery(loader, options)
    return React.createElement("output", null, JSON.stringify(query))
  }

  const container = document.createElement("div")
  document.body.append(container)
  const root = createRoot(container)
  await React.act(async () => {
    root.render(
      options.strict
        ? React.createElement(React.StrictMode, null, React.createElement(Probe))
        : React.createElement(Probe),
    )
  })

  return {
    requests,
    get query() { return query },
    async close() {
      await React.act(async () => root.unmount())
      container.remove()
    },
  }
}

const tests: Array<[string, () => Promise<void>]> = [
  ...adminLogQueryTests,
  ["older success cannot replace the latest response", async () => {
    const hook = await setup()
    await React.act(async () => { void hook.query.refresh() })
    await React.act(async () => hook.requests[1].resolve("latest"))
    await React.act(async () => hook.requests[0].resolve("outdated"))
    assert(hook.query.data === "latest", "An older response replaced the latest data")
    assert(!hook.query.loading && !hook.query.refreshing, "Completed query remains busy")
    await hook.close()
  }],
  ["older failures cannot clear loading or replace a newer error", async () => {
    const hook = await setup()
    await React.act(async () => { void hook.query.refresh() })
    await React.act(async () => hook.requests[0].reject(new Error("old failure")))
    assert(hook.query.loading, "An older request cleared loading while the latest was pending")
    assert(hook.query.error === null, "An older failure was displayed")
    await React.act(async () => hook.requests[1].reject(new Error("latest failure")))
    assert(hook.query.error === "latest failure", "Latest request error was lost")
    assert(!hook.query.loading, "Latest failure did not finish loading")
    await hook.close()
  }],
  ["refresh keeps previous data and remains busy until the latest response", async () => {
    const hook = await setup()
    await React.act(async () => hook.requests[0].resolve("previous"))
    await React.act(async () => { void hook.query.refresh() })
    await React.act(async () => { void hook.query.refresh() })
    await React.act(async () => hook.requests[1].resolve("outdated"))
    assert(hook.query.data === "previous", "Refreshing replaced previously loaded data too early")
    assert(hook.query.refreshing && !hook.query.loading, "An older response cleared refreshing")
    await React.act(async () => hook.requests[2].resolve("latest"))
    assert(hook.query.data === "latest" && !hook.query.refreshing, "Latest refresh did not finish")
    await hook.close()
  }],
  ["unmounted queries do not start new requests", async () => {
    const hook = await setup()
    const refresh = hook.query.refresh
    await hook.close()
    await React.act(async () => hook.requests[0].resolve("after unmount"))
    assert(await refresh() === null, "An unmounted query returned a new request")
    assert(hook.requests.length === 1, "An unmounted query invoked its loader")
  }],
  ["Strict Mode discards responses from its previous effect lifetime", async () => {
    const hook = await setup({ strict: true })
    assert(hook.requests.length === 2, "Strict Mode did not replay the initial effect")
    await React.act(async () => hook.requests[1].resolve("current lifetime"))
    await React.act(async () => hook.requests[0].resolve("previous lifetime"))
    assert(hook.query.data === "current lifetime", "An earlier effect lifetime replaced current data")
    await hook.close()
  }],
  ["polling waits for a pending request and cleans up on unmount", async () => {
    const originalSetInterval = window.setInterval
    const originalClearInterval = window.clearInterval
    let poll: (() => void) | undefined
    let cleared = false
    window.setInterval = ((callback: () => void) => { poll = callback; return 123 }) as typeof window.setInterval
    window.clearInterval = (id) => { cleared = id === 123 }

    try {
      const hook = await setup({ pollMs: 15_000 })
      await React.act(async () => { poll?.(); poll?.() })
      assert(hook.requests.length === 1, "Slow requests were superseded by background polls")
      await React.act(async () => hook.requests[0].resolve("initial"))
      await React.act(async () => { poll?.() })
      assert(hook.requests.length === 2, "Polling did not resume after completion")
      await React.act(async () => hook.requests[1].resolve("polled"))
      assert(hook.query.data === "polled", "Polling did not update the query")
      await hook.close()
      assert(cleared, "Unmount did not clear the polling interval")
    } finally {
      window.setInterval = originalSetInterval
      window.clearInterval = originalClearInterval
    }
  }],
]

async function run() {
  const results = document.createElement("pre")
  document.body.append(results)
  let failures = 0

  for (const [name, test] of tests) {
    try {
      await test()
      results.textContent += `PASS ${name}\n`
    } catch (error) {
      failures += 1
      results.textContent += `FAIL ${name}: ${error instanceof Error ? error.message : error}\n`
    }
  }

  document.documentElement.dataset.testStatus = failures === 0 ? "passed" : "failed"
}

void run()
