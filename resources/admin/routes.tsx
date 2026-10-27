import type { ComponentType, ReactNode } from "react"

import AboutPage from "@/pages/about"
import ConnectionsPage from "@/pages/connections"
import DashboardPage from "@/pages/dashboard"
import MailLogsPage from "@/pages/logs"
import QueueLogsPage from "@/pages/logs-queue"
import SettingsPage from "@/pages/settings"
import WebhookLogsPage from "@/pages/logs-webhooks"
import DashboardSquare01Icon from "~icons/hugeicons/dashboard-square-01"
import Database01Icon from "~icons/hugeicons/database-01"
import File01Icon from "~icons/hugeicons/file-01"
import InformationCircleIcon from "~icons/hugeicons/information-circle"
import Settings05Icon from "~icons/hugeicons/settings-05"

export type AdminRoute = {
  path: string
  title: string
  description: string
  icon: ReactNode
  component: ComponentType
}

export type AdminNavItem = {
  title: string
  href: string
  icon: ReactNode
  isActive: boolean
  items?: {
    title: string
    href: string
    isActive: boolean
  }[]
}

export const DEFAULT_ADMIN_PATH = "/dashboard"
export const DEFAULT_LOGS_PATH = "/logs/mail"

export type AdminHashLocation = {
  path: string
  searchParams: URLSearchParams
}

export const adminRoutes: AdminRoute[] = [
  {
    path: "/dashboard",
    title: "Dashboard",
    description: "Overview of connection health, message volume, and recent activity.",
    icon: <DashboardSquare01Icon />,
    component: DashboardPage,
  },
  {
    path: "/connections",
    title: "Connections",
    description: "Manage providers, routing rules, and webhook endpoints.",
    icon: <Database01Icon />,
    component: ConnectionsPage,
  },
  {
    path: "/connections/new",
    title: "New connection",
    description: "Configure a new email delivery connection.",
    icon: <Database01Icon />,
    component: ConnectionsPage,
  },
  {
    path: "/logs/mail",
    title: "Email Logs",
    description: "Review message lifecycle rows and provider delivery attempts.",
    icon: <File01Icon />,
    component: MailLogsPage,
  },
  {
    path: "/logs/queue",
    title: "Queue Logs",
    description: "Inspect queued, processing, deferred, and failed background jobs.",
    icon: <File01Icon />,
    component: QueueLogsPage,
  },
  {
    path: "/logs/webhooks",
    title: "Webhook Event Logs",
    description: "Inspect normalized provider callbacks recorded by Jooosi Mail.",
    icon: <File01Icon />,
    component: WebhookLogsPage,
  },
  {
    path: "/settings",
    title: "Settings",
    description: "Configure plugin defaults, queue behavior, and security options.",
    icon: <Settings05Icon />,
    component: SettingsPage,
  },
  {
    path: "/about",
    title: "About",
    description: "Review Jooosi Mail version details, capabilities, and support links.",
    icon: <InformationCircleIcon />,
    component: AboutPage,
  },
]

export function normalizeAdminPath(path: string): string {
  const [pathname] = path.replace(/^#/, "").split("?")
  const normalizedPath = `/${pathname
    .trim()
    .replace(/^\/+/, "")
    .replace(/\/+$/, "")}`

  if (normalizedPath === "/") {
    return DEFAULT_ADMIN_PATH
  }

  if (normalizedPath === "/logs") {
    return DEFAULT_LOGS_PATH
  }

  return normalizedPath
}

export function parseAdminHashLocation(hash: string): AdminHashLocation {
  const rawHash = hash.replace(/^#/, "")
  const [rawPath = "", rawSearch = ""] = rawHash.split("?", 2)

  return {
    path: normalizeAdminPath(rawPath),
    searchParams: new URLSearchParams(rawSearch),
  }
}

export function resolveAdminRoute(path: string): AdminRoute {
  const normalizedPath = normalizeAdminPath(path)

  return adminRoutes.find((route) => route.path === normalizedPath) ?? adminRoutes[0]
}

export function toAdminHashHref(path: string): string {
  return `#${normalizeAdminPath(path)}`
}

export function buildAdminHashHref(
  path: string,
  searchParams?: URLSearchParams | Record<string, string | number | null | undefined>,
): string {
  const normalizedPath = normalizeAdminPath(path)

  if (!searchParams) {
    return `#${normalizedPath}`
  }

  const params =
    searchParams instanceof URLSearchParams
      ? new URLSearchParams(searchParams)
      : Object.entries(searchParams).reduce((nextParams, [key, value]) => {
          if (value !== undefined && value !== null && `${value}` !== "") {
            nextParams.set(key, `${value}`)
          }

          return nextParams
        }, new URLSearchParams())

  const search = params.toString()

  return search === "" ? `#${normalizedPath}` : `#${normalizedPath}?${search}`
}

function isAdminPathActive(currentPath: string, path: string): boolean {
  return currentPath === path || currentPath.startsWith(`${path}/`)
}

export function getAdminNavItems(currentPath: string): AdminNavItem[] {
  const definitions = [
    {
      title: "Dashboard",
      path: "/dashboard",
      icon: <DashboardSquare01Icon />,
    },
    {
      title: "Connections",
      path: "/connections",
      icon: <Database01Icon />,
    },
    {
      title: "Logs",
      path: DEFAULT_LOGS_PATH,
      icon: <File01Icon />,
      items: [
        {
          title: "Email",
          path: "/logs/mail",
        },
        {
          title: "Queue",
          path: "/logs/queue",
        },
        {
          title: "Webhooks",
          path: "/logs/webhooks",
        },
      ],
    },
    {
      title: "Settings",
      path: "/settings",
      icon: <Settings05Icon />,
    },
    {
      title: "About",
      path: "/about",
      icon: <InformationCircleIcon />,
    },
  ]

  return definitions.map((definition) => {
    const items = definition.items?.map((item) => ({
      title: item.title,
      href: toAdminHashHref(item.path),
      isActive: isAdminPathActive(currentPath, item.path),
    }))

    return {
      title: definition.title,
      href: toAdminHashHref(definition.path),
      icon: definition.icon,
      isActive: items ? items.some((item) => item.isActive) : isAdminPathActive(currentPath, definition.path),
      items,
    }
  })
}
