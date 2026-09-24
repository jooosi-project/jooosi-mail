import * as React from "react"

import { useAdminQuery } from "@/hooks/use-admin-query"
import { getLogs } from "@/lib/admin-api"

export function useLogsData() {
  const loadLogs = React.useCallback(() => getLogs(), [])

  return useAdminQuery(loadLogs)
}
