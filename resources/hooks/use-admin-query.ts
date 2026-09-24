import * as React from "react"

type UseAdminQueryOptions<T> = {
  reloadKey?: unknown
  loadingOnRefresh?: boolean
  errorMessage?: string
  onSuccess?: (data: T) => void
}

export function useAdminQuery<T>(
  loader: (signal: AbortSignal) => Promise<T>,
  options: UseAdminQueryOptions<T> = {},
) {
  const [data, setData] = React.useState<T | null>(null)
  const [loading, setLoading] = React.useState(true)
  const [refreshing, setRefreshing] = React.useState(false)
  const [error, setError] = React.useState<string | null>(null)
  const loaderRef = React.useRef(loader)
  const optionsRef = React.useRef(options)
  const dataRef = React.useRef<T | null>(null)
  const mountedRef = React.useRef(false)
  const requestIdRef = React.useRef(0)
  const controllerRef = React.useRef<AbortController | null>(null)

  React.useEffect(() => {
    loaderRef.current = loader
    optionsRef.current = options
  }, [loader, options])

  React.useEffect(() => {
    dataRef.current = data
  }, [data])

  const load = React.useCallback(async () => {
    if (!mountedRef.current) {
      return null
    }

    const requestId = ++requestIdRef.current
    const initialLoad = dataRef.current === null
    const isCurrentRequest = () => mountedRef.current && requestId === requestIdRef.current
    controllerRef.current?.abort()
    const controller = new AbortController()
    controllerRef.current = controller

    const showLoading = initialLoad || optionsRef.current.loadingOnRefresh === true
    setLoading(showLoading)
    setRefreshing(!showLoading)

    setError(null)

    try {
      const nextData = await loaderRef.current(controller.signal)

      if (isCurrentRequest()) {
        dataRef.current = nextData
        setData(nextData)
        optionsRef.current.onSuccess?.(nextData)
      }

      return nextData
    } catch (caughtError) {
      const message = caughtError instanceof Error
        ? caughtError.message
        : optionsRef.current.errorMessage ?? "An unexpected error occurred."

      if (isCurrentRequest()) {
        setError(message)
      }

      return null
    } finally {
      if (isCurrentRequest()) {
        controllerRef.current = null
        setLoading(false)
        setRefreshing(false)
      }
    }
  }, [])

  const refresh = React.useCallback(() => load(), [load])

  React.useEffect(() => {
    mountedRef.current = true
    void refresh()

    return () => {
      mountedRef.current = false
      requestIdRef.current += 1
      controllerRef.current?.abort()
      controllerRef.current = null
    }
  }, [options.reloadKey, refresh])

  return {
    data,
    setData,
    loading,
    refreshing,
    error,
    refresh,
  }
}
