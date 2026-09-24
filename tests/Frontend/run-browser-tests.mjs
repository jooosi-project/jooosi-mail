import { spawnSync } from "node:child_process"
import { existsSync } from "node:fs"
import { mkdtemp, rm, writeFile } from "node:fs/promises"
import { tmpdir } from "node:os"
import { join } from "node:path"
import { fileURLToPath, pathToFileURL } from "node:url"
import { build } from "vite-plus"

const chrome = [
  process.env.CHROME_PATH,
  "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
  "/usr/bin/google-chrome",
  "/usr/bin/chromium",
  "/usr/bin/chromium-browser",
].find((path) => path && existsSync(path))

if (!chrome) {
  throw new Error("Chrome is required for browser regression tests; set CHROME_PATH to its executable.")
}

const directory = await mkdtemp(join(tmpdir(), "jooosi-mail-browser-tests-"))

try {
  await build({
    configFile: false,
    root: fileURLToPath(new URL("../../", import.meta.url)),
    logLevel: "error",
    define: { "process.env.NODE_ENV": JSON.stringify("development") },
    build: {
      outDir: directory,
      emptyOutDir: false,
      minify: false,
      lib: {
        entry: fileURLToPath(new URL("./use-admin-query.browser.tsx", import.meta.url)),
        formats: ["iife"],
        name: "AdminQueryTests",
        fileName: () => "tests.js",
      },
    },
  })
  for (const timezone of ["UTC", "Asia/Jakarta", "America/Los_Angeles"]) {
    await writeFile(join(directory, "index.html"), `<!doctype html><html data-test-timezone="${timezone}"><body><script src="tests.js"></script></body></html>`)
    const result = spawnSync(chrome, [
      "--headless=new",
      "--disable-gpu",
      "--no-first-run",
      "--no-default-browser-check",
      `--user-data-dir=${join(directory, `profile-${timezone.replaceAll("/", "-")}`)}`,
      "--dump-dom",
      "--virtual-time-budget=10000",
      pathToFileURL(join(directory, "index.html")).href,
    ], { encoding: "utf8", timeout: 30_000, maxBuffer: 1_048_576, env: { ...process.env, TZ: timezone } })

    const output = result.stdout ?? ""
    const passed = output.includes('data-test-status="passed"')
    process.stdout.write(`${timezone}\n${output.match(/<pre>([\s\S]*?)<\/pre>/)?.[1] ?? output}`)

    if (!passed) {
      process.stderr.write(result.error?.message || result.stderr || `Browser tests did not complete (exit ${result.status}, signal ${result.signal}).\n`)
      process.exitCode = 1
    }
  }
} finally {
  await rm(directory, { recursive: true, force: true })
}
