import * as React from "react"
import type { PaginationState } from "@tanstack/react-table"

import { useAdminQuery } from "./use-admin-query"

type LogPageData = {
  pagination: {
    page: number
    perPage: number
    total: number
    totalPages: number
  }
}

type UseAdminLogQueryOptions = {
  refreshToken: number
  setPagination: React.Dispatch<React.SetStateAction<PaginationState>>
  errorMessage: string
}

export function useAdminLogQuery<TQuery extends { page?: number; perPage?: number }, TData extends LogPageData>(
  loader: (query: TQuery, signal?: AbortSignal) => Promise<TData>,
  query: TQuery,
  { refreshToken, setPagination, errorMessage }: UseAdminLogQueryOptions,
) {
  const reloadKey = React.useMemo(() => [query, refreshToken], [query, refreshToken])
  const load = React.useCallback((signal: AbortSignal) => loader(query, signal), [loader, query])
  const synchronizePagination = React.useCallback((data: TData) => {
    const { page, perPage } = data.pagination
    const pageIndex = Math.max(0, page - 1)
    setPagination((current) => {
      if (
        (query.page !== undefined && current.pageIndex !== query.page - 1) ||
        (query.perPage !== undefined && current.pageSize !== query.perPage)
      ) {
        return current
      }

      return current.pageIndex === pageIndex && current.pageSize === perPage
        ? current
        : { pageIndex, pageSize: perPage }
    })
  }, [query, setPagination])

  return useAdminQuery(load, {
    reloadKey,
    loadingOnRefresh: true,
    errorMessage,
    onSuccess: synchronizePagination,
  })
}
