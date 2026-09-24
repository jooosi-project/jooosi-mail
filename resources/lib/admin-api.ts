export type AdminOption = {
  value: string
  label: string
}

export type AdminConfigurationFieldCondition = {
  field: string
  operator: "in" | "not_in"
  values: string[]
}

export type AdminConfigurationField = {
  name: string
  label: string
  type: string
  required: boolean
  secret: boolean
  configured?: boolean
  value?: string | number | null
  default?: string | number | null
  choices: string[]
  visibleWhen: AdminConfigurationFieldCondition[]
  requiredWhen: AdminConfigurationFieldCondition[]
}

export type AdminConnectionProfileMetadata = {
  website?: string
  docsUrl?: string
  useCases?: string[]
  extra?: Record<string, string | string[]>
}

export type AdminConnectionProfile = {
  key: string
  label: string
  description: string
  metadata?: AdminConnectionProfileMetadata
  schemes: string[]
  supportsWebhooks: boolean
  configurationFields: AdminConfigurationField[]
}

export type AdminConnectionProfileInfo = {
  key: string
  label: string
  description?: string
  metadata?: AdminConnectionProfileMetadata
  supportsWebhooks: boolean
  missing?: boolean
}

export type AdminConnectionSummary = {
  id: number | null
  profile: AdminConnectionProfileInfo
  name: string
  enabled: boolean
  default: boolean
  priority: number
  weight: number
  webhookEnabled: boolean
  webhookSecretConfigured: boolean
  dsnOverrideConfigured: boolean
  healthScore: number
  available: boolean
  unavailableReasons: string[]
  nextAvailableAt: string | null
  webhookUrl: string | null
}

export type AdminConnectionSenderPolicy = {
  email: string
  name: string
  forceEmail: boolean
  forceName: boolean
  returnPathMode: string
  returnPathEmail: string
}

export type AdminConnectionDetail = AdminConnectionSummary & {
  dsn: string | null
  configurationFields: AdminConfigurationField[]
  sender: AdminConnectionSenderPolicy
  rateLimits: {
    minute: number | string | null
    hour: number | string | null
    day: number | string | null
  }
  circuitBreaker: {
    threshold: number | string | null
    window: number | string | null
    cooldown: number | string | null
  }
  rateLimitStatus: {
    blocked: boolean
    windows: Record<string, unknown>
  }
  circuitBreakerStatus: {
    enabled: boolean
    recentFailures: number
    blacklistedUntil: string | null
  }
}

export type AdminAttempt = {
  id: number
  mailLogId: number
  connectionId: number
  connectionName: string
  status: string
  errorMessage: string | null
  transportMessageId: string | null
  startedAt: string | null
  finishedAt: string | null
}

export type AdminMailLog = {
  id: number
  source: string
  subject: string
  status: string
  finalConnectionId: number | null
  connectionName: string | null
  connectionProfileKey: string | null
  transportMessageId: string | null
  lastError: string | null
  toAddresses: string[]
  fromAddresses: string[]
  ccAddresses: string[]
  bccAddresses: string[]
  replyToAddresses: string[]
  textBody: string | null
  htmlBody: string | null
  createdAt: string | null
  queuedAt: string | null
  sentAt: string | null
  updatedAt: string | null
}

export type AdminMailLogFilterOption = {
  label: string
  value: string
  count: number
}

export type AdminMailLogQuery = {
  search?: string
  statuses?: string[]
  connectionIds?: string[]
  fromDate?: string
  toDate?: string
  page?: number
  perPage?: number
  sortBy?: "id" | "subject" | "status" | "dateTime" | "connection"
  sortDirection?: "asc" | "desc"
}

export type AdminMailLogPageData = {
  items: AdminMailLog[]
  pagination: {
    page: number
    perPage: number
    total: number
    totalPages: number
  }
  filters: {
    statuses: AdminMailLogFilterOption[]
    connections: AdminMailLogFilterOption[]
  }
}

export type AdminMailLogDetailData = {
  item: AdminMailLog | null
}

export type AdminResendMailResponse = {
  submitted: boolean
  originalMailLogId: number
  mailLogId: number
  message: string
}

export type AdminSendTestEmailPayload = {
  to: string
  subject: string
  connectionId?: number
}

export type AdminSendTestEmailResponse = {
  sent: boolean
  message: string
}

export type AdminWebhookLog = {
  id: number
  connectionId: number | null
  connectionName: string | null
  connectionProfileKey: string | null
  mailLogId: number | null
  eventType: string
  transportMessageId: string | null
  providerEventId: string | null
  payloadJson: string | null
  occurredAt: string | null
  createdAt: string | null
}

export type AdminWebhookLogQuery = {
  search?: string
  eventTypes?: string[]
  connectionIds?: string[]
  fromDate?: string
  toDate?: string
  page?: number
  perPage?: number
  sortBy?: "id" | "eventType" | "dateTime" | "connection" | "mailLogId"
  sortDirection?: "asc" | "desc"
}

export type AdminWebhookLogPageData = {
  items: AdminWebhookLog[]
  pagination: {
    page: number
    perPage: number
    total: number
    totalPages: number
  }
  filters: {
    eventTypes: AdminMailLogFilterOption[]
    connections: AdminMailLogFilterOption[]
  }
}

export type AdminWebhookEvent = {
  id: number
  connectionId: number | null
  connectionName: string | null
  mailLogId: number | null
  eventType: string
  transportMessageId: string | null
  providerEventId: string | null
  occurredAt: string | null
  createdAt: string | null
}

export type AdminQueueMessage = {
  id: number
  mailLogId?: number | null
  status: string
  priority: number
  attemptCount: number
  maxAttempts: number
  lastError: string | null
  availableAt: string | null
  claimedAt: string | null
  workerId: string | null
  processedAt: string | null
  createdAt: string | null
  updatedAt: string | null
  attemptHistory?: AdminQueueAttempt[]
}

export type AdminQueueAttempt = {
  sequenceNumber: number
  attemptNumber: number
  outcome: string
  workerId: string | null
  errorMessage: string | null
  retryDelaySeconds: number | null
  startedAt: string
  finishedAt: string | null
}

export type AdminDashboardSendingStat = {
  date: string
  total: number
  sent: number
  failed: number
}

export type AdminDashboardQuery = {
  fromDate?: string
  toDate?: string
}

export type AdminQueueLogQuery = {
  search?: string
  statuses?: string[]
  fromDate?: string
  toDate?: string
  page?: number
  perPage?: number
  sortBy?: "id" | "status" | "priority" | "attempts" | "dateTime"
  sortDirection?: "asc" | "desc"
}

export type AdminQueueLogPageData = {
  items: AdminQueueMessage[]
  pagination: {
    page: number
    perPage: number
    total: number
    totalPages: number
  }
  filters: {
    statuses: AdminMailLogFilterOption[]
  }
}

export type AdminDashboardData = {
  summary: {
    deliveryMode: string
    routingStrategy: string
    interceptEnabled: boolean
    connectionsTotal: number
    activeConnections: number
    availableConnections: number
    temporarilyUnavailableConnections: number
    nextAvailableAt: string | null
    queuePendingReady: number
    queuePendingDeferred: number
    queueProcessing: number
    queueStaleProcessing: number
    queueFailed: number
    queueCompleted: number
    mailPending: number
    mailQueued: number
    mailProcessing: number
    mailSent: number
    mailFailed: number
    mailTotal: number
    webhookEvents: number
  }
  connections: AdminConnectionSummary[]
  sendingStats: AdminDashboardSendingStat[]
  recentAttempts: AdminAttempt[]
  recentWebhooks: AdminWebhookEvent[]
  failedMessages: AdminQueueMessage[]
}

export type AdminConnectionListData = {
  profiles: AdminConnectionProfile[]
  connections: AdminConnectionSummary[]
}

export type AdminConnectionDetailData = {
  connection: AdminConnectionDetail
}

export type AdminLogsData = {
  summary: {
    mail: {
      total: number
      pending: number
      queued: number
      processing: number
      sent: number
      failed: number
    }
    queue: {
      pendingReady: number
      pendingDeferred: number
      processing: number
      staleProcessing: number
      failed: number
      completed: number
    }
    webhookEvents: number
    failedMessages: number
  }
  attempts: AdminAttempt[]
  events: AdminWebhookEvent[]
  failedMessages: AdminQueueMessage[]
  processingMessages: AdminQueueMessage[]
}

export type AdminSettings = {
  mail: {
    intercept: {
      enabled: boolean
    }
    sender: {
      email: string
      name: string
      forceEmail: boolean
      forceName: boolean
      returnPathMode: string
      returnPathEmail: string
    }
  }
  logging: {
    email: {
      enabled: boolean
      retentionDays: number | null
    }
  }
  delivery: {
    mode: string
    strategy: string
  }
  routing: {
    rateLimits: {
      minute: number
      hour: number
      day: number
    }
    circuitBreaker: {
      threshold: number
      windowSeconds: number
      cooldownSeconds: number
    }
  }
  queue: {
    retry: {
      maxRetries: number
      delaySeconds: number
      multiplier: number
      maxDelaySeconds: number
    }
  }
}

export type AdminSettingsData = {
  settings: AdminSettings
  options: {
    deliveryModes: AdminOption[]
    routingStrategies: AdminOption[]
    returnPathModes: AdminOption[]
  }
}

export type ConnectionSecretAction = "keep" | "replace" | "clear"

export type ConnectionSavePayload = {
  profile: string
  name: string
  dsn: string
  enabled: boolean
  default: boolean
  priority: number
  weight: number
  webhookEnabled: boolean
  webhookSecretAction: ConnectionSecretAction
  webhookSecret: string
  sender: AdminConnectionSenderPolicy
  rateLimits?: Partial<Record<"minute" | "hour" | "day", number>>
  circuitBreaker?: Partial<Record<"threshold" | "window" | "cooldown", number>>
  configuration: Record<string, string>
  secretConfiguration: Record<string, { action: ConnectionSecretAction; value: string }>
}

export type JooosiMailAdminRuntime = {
  apiRoot: string
  nonce: string
  pluginVersion: string
}

declare global {
  interface Window {
    jooosiMailAdmin?: JooosiMailAdminRuntime
  }
}

export class AdminApiError extends Error {
  public readonly status: number

  public readonly payload: unknown

  public constructor(message: string, status: number, payload: unknown) {
    super(message)
    this.name = "AdminApiError"
    this.status = status
    this.payload = payload
  }
}

function getRuntime(): JooosiMailAdminRuntime {
  const runtime = window.jooosiMailAdmin

  if (!runtime) {
    throw new Error("Jooosi Mail admin runtime is not available.")
  }

  return runtime
}

export function getAdminRuntime(): JooosiMailAdminRuntime {
  return getRuntime()
}

async function adminFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const runtime = getRuntime()
  const headers = new Headers(init.headers)

  headers.set("Accept", "application/json")
  headers.set("X-WP-Nonce", runtime.nonce)

  if (init.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json")
  }

  const response = await fetch(new URL(path.replace(/^\//, ""), runtime.apiRoot), {
    ...init,
    cache: init.cache ?? "no-store",
    headers,
    credentials: "same-origin",
  })
  const payload = await response.json().catch(() => null)

  if (!response.ok) {
    throw new AdminApiError(resolveErrorMessage(payload), response.status, payload)
  }

  return payload as T
}

function resolveErrorMessage(payload: unknown): string {
  if (typeof payload === "object" && payload !== null && "message" in payload) {
    const message = payload.message

    if (typeof message === "string" && message !== "") {
      return message
    }
  }

  return "The request could not be completed."
}

export async function getDashboard(query: AdminDashboardQuery = {}, signal?: AbortSignal): Promise<AdminDashboardData> {
  const searchParams = new URLSearchParams()

  if (query.fromDate) {
    searchParams.set("fromDate", query.fromDate)
  }

  if (query.toDate) {
    searchParams.set("toDate", query.toDate)
  }

  const suffix = searchParams.size > 0 ? `?${searchParams.toString()}` : ""

  return adminFetch<AdminDashboardData>(`dashboard${suffix}`, { signal })
}

export async function getConnections(): Promise<AdminConnectionListData> {
  return adminFetch<AdminConnectionListData>("connections")
}

export async function getConnection(connectionId: number): Promise<AdminConnectionDetailData> {
  return adminFetch<AdminConnectionDetailData>(`connections/${connectionId}`)
}

export async function saveConnection(payload: ConnectionSavePayload, connectionId?: number): Promise<AdminConnectionDetailData> {
  return adminFetch<AdminConnectionDetailData>(
    connectionId === undefined ? "connections" : `connections/${connectionId}`,
    {
      method: connectionId === undefined ? "POST" : "PUT",
      body: JSON.stringify(payload),
    },
  )
}

export async function deleteConnection(connectionId: number): Promise<{ deleted: boolean }> {
  return adminFetch<{ deleted: boolean }>(`connections/${connectionId}`, {
    method: "DELETE",
  })
}

export async function makeDefaultConnection(connectionId: number): Promise<AdminConnectionDetailData> {
  return adminFetch<AdminConnectionDetailData>(`connections/${connectionId}/default`, {
    method: "POST",
  })
}

export async function setConnectionEnabled(
  connectionId: number,
  enabled: boolean,
): Promise<AdminConnectionDetailData> {
  return adminFetch<AdminConnectionDetailData>(`connections/${connectionId}/enabled`, {
    method: "POST",
    body: JSON.stringify({ enabled }),
  })
}

export async function getLogs(): Promise<AdminLogsData> {
  return adminFetch<AdminLogsData>("logs")
}

export async function getMailLogs(query: AdminMailLogQuery = {}, signal?: AbortSignal): Promise<AdminMailLogPageData> {
  const searchParams = new URLSearchParams()

  if (query.search && query.search.trim() !== "") {
    searchParams.set("search", query.search.trim())
  }

  if (query.statuses && query.statuses.length > 0) {
    for (const status of query.statuses) {
      searchParams.append("statuses[]", status)
    }
  }

  if (query.connectionIds && query.connectionIds.length > 0) {
    for (const connectionId of query.connectionIds) {
      searchParams.append("connectionIds[]", connectionId)
    }
  }

  if (query.fromDate) {
    searchParams.set("fromDate", query.fromDate)
  }

  if (query.toDate) {
    searchParams.set("toDate", query.toDate)
  }

  if (query.page) {
    searchParams.set("page", String(query.page))
  }

  if (query.perPage) {
    searchParams.set("perPage", String(query.perPage))
  }

  if (query.sortBy) {
    searchParams.set("sortBy", query.sortBy)
  }

  if (query.sortDirection) {
    searchParams.set("sortDirection", query.sortDirection)
  }

  const suffix = searchParams.size > 0 ? `?${searchParams.toString()}` : ""

  return adminFetch<AdminMailLogPageData>(`logs/mail${suffix}`, { signal })
}

export async function getMailLog(mailLogId: number): Promise<AdminMailLogDetailData> {
  return adminFetch<AdminMailLogDetailData>(`logs/mail/${mailLogId}`)
}

export async function resendMailLog(mailLogId: number): Promise<AdminResendMailResponse> {
  return adminFetch<AdminResendMailResponse>(`logs/mail/${mailLogId}/resend`, {
    method: "POST",
  })
}

export async function sendTestEmail(payload: AdminSendTestEmailPayload): Promise<AdminSendTestEmailResponse> {
  return adminFetch<AdminSendTestEmailResponse>("logs/mail/test", {
    method: "POST",
    body: JSON.stringify(payload),
  })
}

export async function getWebhookLogs(query: AdminWebhookLogQuery = {}, signal?: AbortSignal): Promise<AdminWebhookLogPageData> {
  const searchParams = new URLSearchParams()

  if (query.search && query.search.trim() !== "") {
    searchParams.set("search", query.search.trim())
  }

  if (query.eventTypes && query.eventTypes.length > 0) {
    for (const eventType of query.eventTypes) {
      searchParams.append("eventTypes[]", eventType)
    }
  }

  if (query.connectionIds && query.connectionIds.length > 0) {
    for (const connectionId of query.connectionIds) {
      searchParams.append("connectionIds[]", connectionId)
    }
  }

  if (query.fromDate) {
    searchParams.set("fromDate", query.fromDate)
  }

  if (query.toDate) {
    searchParams.set("toDate", query.toDate)
  }

  if (query.page) {
    searchParams.set("page", String(query.page))
  }

  if (query.perPage) {
    searchParams.set("perPage", String(query.perPage))
  }

  if (query.sortBy) {
    searchParams.set("sortBy", query.sortBy)
  }

  if (query.sortDirection) {
    searchParams.set("sortDirection", query.sortDirection)
  }

  const suffix = searchParams.size > 0 ? `?${searchParams.toString()}` : ""

  return adminFetch<AdminWebhookLogPageData>(`logs/webhooks${suffix}`, { signal })
}

export async function getQueueLogs(query: AdminQueueLogQuery = {}, signal?: AbortSignal): Promise<AdminQueueLogPageData> {
  const searchParams = new URLSearchParams()

  if (query.search && query.search.trim() !== "") {
    searchParams.set("search", query.search.trim())
  }

  if (query.statuses && query.statuses.length > 0) {
    for (const status of query.statuses) {
      searchParams.append("statuses[]", status)
    }
  }

  if (query.fromDate) {
    searchParams.set("fromDate", query.fromDate)
  }

  if (query.toDate) {
    searchParams.set("toDate", query.toDate)
  }

  if (query.page) {
    searchParams.set("page", String(query.page))
  }

  if (query.perPage) {
    searchParams.set("perPage", String(query.perPage))
  }

  if (query.sortBy) {
    searchParams.set("sortBy", query.sortBy)
  }

  if (query.sortDirection) {
    searchParams.set("sortDirection", query.sortDirection)
  }

  const suffix = searchParams.size > 0 ? `?${searchParams.toString()}` : ""

  return adminFetch<AdminQueueLogPageData>(`logs/queue${suffix}`, { signal })
}

export async function getSettings(): Promise<AdminSettingsData> {
  return adminFetch<AdminSettingsData>("settings")
}

export async function updateSettings(settings: AdminSettings): Promise<AdminSettingsData> {
  return adminFetch<AdminSettingsData>("settings", {
    method: "PUT",
    body: JSON.stringify({ settings }),
  })
}
