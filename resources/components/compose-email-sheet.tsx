import * as React from "react"
import { toast } from "sonner"

import { EmailRichTextEditor } from "@/components/email-rich-text-editor"
import { MailLogConnectionHoverCard } from "@/components/mail-log-connection-hover-card"
import { TruncatedTextTooltip } from "@/components/truncated-text-tooltip"
import { ProfileBrandIcon } from "@/components/profile-brand-icon"
import {
  Autocomplete,
  AutocompleteContent,
  AutocompleteInput,
  AutocompleteItem,
  AutocompleteList,
  AutocompleteStatus,
} from "@/components/reui/autocomplete"
import { IconTile } from "@/components/reui/icon-tile"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import {
  Field,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field"
import { Calendar } from "@/components/ui/calendar"
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
  ComboboxTrigger,
  ComboboxValue,
} from "@/components/ui/combobox"
import { Input } from "@/components/ui/input"
import { Separator } from "@/components/ui/separator"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import {
  composeEmail,
  getConnections,
  getMailRecipientSuggestions,
  type AdminConnectionSummary,
  type AdminRecipientSuggestion,
} from "@/lib/admin-api"
import {
  Stepper,
  StepperIndicator,
  StepperItem,
  StepperNav,
  StepperSeparator,
  StepperTitle,
  StepperTrigger,
} from "@/components/reui/stepper"
import CheckIcon from "~icons/tabler/check"
import Loader2Icon from "~icons/tabler/loader-2"
import MailIcon from "~icons/tabler/mail"
import SendIcon from "~icons/tabler/send"
import Cancel01Icon from "~icons/hugeicons/cancel-01"
import Calendar03Icon from "~icons/hugeicons/calendar-03"

const DEFAULT_CONNECTION_VALUE = "default"

type ComposeEmailSheetProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSent?: () => void
}

type ComposeEmailField = "to" | "cc" | "bcc" | "replyTo" | "subject" | "sendAt" | "body"

type ComposeEmailErrors = Partial<Record<ComposeEmailField, string>>
type ComposeStep = 1 | 2
type ConnectionProfileOption = {
  value: string
  label: string
  profileKey: string | null
  profileLabel: string | null
  connectionName: string | null
  isDefault: boolean
}

const COMPOSE_STEPS: { step: ComposeStep; title: string }[] = [
  { step: 1, title: "Recipients" },
  { step: 2, title: "Message" },
]
const RECIPIENT_STEP_FIELDS: ComposeEmailField[] = ["to", "cc", "bcc", "replyTo", "sendAt"]

function Section({ label, children }: { label?: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col gap-3">
      {label ? (
        <h2 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
          {label}
        </h2>
      ) : null}
      {children}
    </section>
  )
}

function ComposeStepNav({
  step,
  canOpenMessage,
  onStepChange,
}: {
  step: ComposeStep
  canOpenMessage: boolean
  onStepChange: (step: ComposeStep) => void
}) {
  return (
    <Stepper
      value={step}
      onValueChange={(nextStep) => {
        if (nextStep === 1 || (nextStep === 2 && canOpenMessage)) {
          onStepChange(nextStep as ComposeStep)
        }
      }}
      aria-label="Compose email steps"
      indicators={{ completed: <CheckIcon className="size-3" aria-hidden="true" /> }}
    >
      <StepperNav className="flex items-center">
        {COMPOSE_STEPS.map((item, index) => (
          <StepperItem
            key={item.step}
            step={item.step}
            disabled={item.step === 2 && !canOpenMessage}
            className="min-w-0"
          >
            <StepperTrigger className="min-h-9 min-w-0 gap-2.5 px-1 py-1">
              <StepperIndicator className="size-7 text-xs">
                {item.step}
              </StepperIndicator>
              <StepperTitle className="truncate text-sm">{item.title}</StepperTitle>
            </StepperTrigger>
            {index < COMPOSE_STEPS.length - 1 ? (
              <StepperSeparator className="mx-3" />
            ) : null}
          </StepperItem>
        ))}
      </StepperNav>
    </Stepper>
  )
}

function splitAddresses(value: string): string[] {
  return value
    .split(/[,;\r\n]+/)
    .map((address) => address.trim())
    .filter(Boolean)
}

function getRecipientSearchTerm(value: string): string {
  return value.split(/[,;\r\n]+/).at(-1)?.trim() ?? ""
}

function appendSelectedRecipient(value: string, email: string): string {
  const lastSeparator = Math.max(
    value.lastIndexOf(","),
    value.lastIndexOf(";"),
    value.lastIndexOf("\n"),
    value.lastIndexOf("\r"),
  )
  const previousRecipients = lastSeparator < 0 ? "" : value.slice(0, lastSeparator).trim()

  return `${previousRecipients ? `${previousRecipients}, ` : ""}${email}, `
}

function getRecipientInitials(name: string): string {
  return name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join("")
    .toUpperCase()
}

type RecipientAutocompleteInputProps = {
  id: string
  value: string
  setValue: React.Dispatch<React.SetStateAction<string>>
  enabled: boolean
  disabled: boolean
  placeholder: string
  required?: boolean
  invalid?: boolean
  autoFocus?: boolean
  describedBy?: string
}

function RecipientAutocompleteInput({
  id,
  value,
  setValue,
  enabled,
  disabled,
  placeholder,
  required = false,
  invalid = false,
  autoFocus = false,
  describedBy,
}: RecipientAutocompleteInputProps) {
  const [suggestions, setSuggestions] = React.useState<AdminRecipientSuggestion[]>([])
  const [searchLoading, setSearchLoading] = React.useState(false)
  const [searchError, setSearchError] = React.useState<string | null>(null)
  const searchTerm = getRecipientSearchTerm(value)

  React.useEffect(() => {
    let active = true
    const search = searchTerm.trim()

    if (!enabled || search.length < 2) {
      setSuggestions([])
      setSearchLoading(false)
      setSearchError(null)

      return () => {
        active = false
      }
    }

    const controller = new AbortController()
    setSuggestions([])
    setSearchLoading(true)
    setSearchError(null)

    const timeoutId = window.setTimeout(() => {
      void getMailRecipientSuggestions(search, controller.signal)
        .then((response) => {
          if (active) {
            setSuggestions(response.users)
          }
        })
        .catch(() => {
          if (active && !controller.signal.aborted) {
            setSuggestions([])
            setSearchError("WordPress users could not be loaded.")
          }
        })
        .finally(() => {
          if (active) {
            setSearchLoading(false)
          }
        })
    }, 250)

    return () => {
      active = false
      window.clearTimeout(timeoutId)
      controller.abort()
    }
  }, [enabled, searchTerm])

  return (
    <Autocomplete
      value={value}
      items={suggestions}
      itemToStringValue={(user: AdminRecipientSuggestion) => user.email}
      filter={null}
      autoHighlight
      onValueChange={(nextValue, eventDetails) => {
        if (eventDetails.reason === "item-press") {
          const selectedUser = suggestions.find((user) => user.email === nextValue)

          if (selectedUser) {
            setValue((currentValue) => appendSelectedRecipient(currentValue, selectedUser.email))
          }

          return
        }

        setValue(nextValue)
      }}
    >
      <AutocompleteInput
        id={id}
        size="lg"
        placeholder={placeholder}
        autoComplete="email"
        autoFocus={autoFocus}
        disabled={disabled}
        required={required}
        aria-required={required || undefined}
        aria-invalid={invalid || undefined}
        aria-describedby={describedBy}
      />
      {enabled && searchTerm.trim().length >= 2 ? (
        <AutocompleteContent align="start" sideOffset={4}>
          <AutocompleteStatus>
            {searchLoading
              ? "Searching WordPress users…"
              : searchError ??
                (suggestions.length === 0
                  ? "No matching users. You can still enter any email address."
                  : `${suggestions.length} WordPress users`)}
          </AutocompleteStatus>
          <AutocompleteList>
            {(user: AdminRecipientSuggestion) => (
              <AutocompleteItem
                key={user.id}
                value={user}
                className="min-w-0 cursor-default py-2"
              >
                <Avatar aria-hidden="true">
                  <AvatarImage src={user.avatarUrl} alt="" />
                  <AvatarFallback className="text-xs font-medium">
                    {getRecipientInitials(user.name)}
                  </AvatarFallback>
                </Avatar>
                <span className="flex min-w-0 flex-col gap-0.5">
                  <span className="truncate font-medium">{user.name}</span>
                  <span className="truncate text-xs text-muted-foreground">{user.email}</span>
                </span>
              </AutocompleteItem>
            )}
          </AutocompleteList>
        </AutocompleteContent>
      ) : null}
    </Autocomplete>
  )
}

function validateAddresses(value: string, required: boolean, label: string): string | null {
  const addresses = splitAddresses(value)

  if (required && addresses.length === 0) {
    return "Enter at least one recipient email address."
  }

  if (addresses.some((address) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(address) === false)) {
    return `Check the email addresses in ${label}.`
  }

  return null
}

function validateForm(values: {
  to: string
  cc: string
  bcc: string
  replyTo: string
  subject: string
  sendAt: string
  body: string
}): ComposeEmailErrors {
  const errors: ComposeEmailErrors = {}
  const toError = validateAddresses(values.to, true, "To")
  const ccError = validateAddresses(values.cc, false, "Cc")
  const bccError = validateAddresses(values.bcc, false, "Bcc")
  const replyToError = validateAddresses(values.replyTo, false, "Reply-To")
  const replyToAddresses = splitAddresses(values.replyTo)

  if (toError) errors.to = toError
  if (ccError) errors.cc = ccError
  if (bccError) errors.bcc = bccError
  if (replyToError) errors.replyTo = replyToError
  if (replyToAddresses.length > 1) errors.replyTo = "Enter one Reply-To email address."
  if (values.subject.trim() === "") errors.subject = "Enter a subject."
  if (values.body.trim() === "") errors.body = "Enter a message."

  if (values.sendAt !== "") {
    const scheduledAt = new Date(values.sendAt).getTime()

    if (Number.isNaN(scheduledAt) || scheduledAt <= Date.now()) {
      errors.sendAt = "Choose a future send time."
    }
  }

  return errors
}

function toLocalDateTimeInputValue(date: Date): string {
  const timezoneOffset = date.getTimezoneOffset() * 60_000

  return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16)
}

function toLocalTimeInputValue(date: Date): string {
  return `${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`
}

function combineLocalDateAndTime(date: Date, time: string): string {
  const [hours, minutes] = time.split(":").map(Number)
  const combinedDate = new Date(date.getFullYear(), date.getMonth(), date.getDate(), hours, minutes)

  return toLocalDateTimeInputValue(combinedDate)
}

function getDefaultScheduleDate(date: Date): Date {
  const now = new Date()
  const isToday =
    date.getFullYear() === now.getFullYear() &&
    date.getMonth() === now.getMonth() &&
    date.getDate() === now.getDate()

  if (!isToday) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate(), 9)
  }

  const nextMinute = new Date(now.getTime() + 60_000)
  nextMinute.setSeconds(0, 0)

  return nextMinute
}

function formatScheduleDateTime(value: string): string {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date)
}

export function ComposeEmailSheet({ open, onOpenChange, onSent }: ComposeEmailSheetProps) {
  const [scrollAreaElement, setScrollAreaElement] = React.useState<HTMLDivElement | null>(null)
  const formId = React.useId()
  const toId = React.useId()
  const ccId = React.useId()
  const bccId = React.useId()
  const replyToId = React.useId()
  const connectionId = React.useId()
  const sendAtId = React.useId()
  const scheduleTimeId = React.useId()
  const subjectId = React.useId()
  const bodyId = React.useId()
  const bodyDescriptionId = React.useId()
  const bodyErrorId = React.useId()
  const [to, setTo] = React.useState("")
  const [cc, setCc] = React.useState("")
  const [bcc, setBcc] = React.useState("")
  const [replyTo, setReplyTo] = React.useState("")
  const [selectedConnectionId, setSelectedConnectionId] = React.useState(DEFAULT_CONNECTION_VALUE)
  const [sendAt, setSendAt] = React.useState("")
  const [schedulePickerOpen, setSchedulePickerOpen] = React.useState(false)
  const [subject, setSubject] = React.useState("")
  const [bodyHtml, setBodyHtml] = React.useState("")
  const [bodyEmailHtml, setBodyEmailHtml] = React.useState("")
  const [bodyText, setBodyText] = React.useState("")
  const [connections, setConnections] = React.useState<AdminConnectionSummary[]>([])
  const [connectionsLoading, setConnectionsLoading] = React.useState(false)
  const [connectionsError, setConnectionsError] = React.useState<string | null>(null)
  const [step, setStep] = React.useState<ComposeStep>(1)
  const [recipientsAttempted, setRecipientsAttempted] = React.useState(false)
  const [submitted, setSubmitted] = React.useState(false)
  const [sending, setSending] = React.useState(false)
  const [serverError, setServerError] = React.useState<string | null>(null)
  const formErrors = validateForm({ to, cc, bcc, replyTo, subject, sendAt, body: bodyText })
  const canOpenMessage = RECIPIENT_STEP_FIELDS.every((field) => formErrors[field] === undefined)
  const errors: ComposeEmailErrors = {}

  if (recipientsAttempted || submitted) {
    errors.to = formErrors.to
    errors.cc = formErrors.cc
    errors.bcc = formErrors.bcc
    errors.replyTo = formErrors.replyTo
    errors.sendAt = formErrors.sendAt
  }

  if (submitted) {
    errors.subject = formErrors.subject
    errors.body = formErrors.body
  }

  const selectedConnection =
    selectedConnectionId === DEFAULT_CONNECTION_VALUE
      ? null
      : connections.find((connection) => `${connection.id}` === selectedConnectionId) ?? null
  const connectionOptions = React.useMemo<ConnectionProfileOption[]>(
    () => [
      {
        value: DEFAULT_CONNECTION_VALUE,
        label: "Use configured routing",
        profileKey: null,
        profileLabel: null,
        connectionName: null,
        isDefault: false,
      },
      ...connections.map((connection) => ({
        value: `${connection.id}`,
        label: `${connection.name} · ${connection.profile.label}${connection.default ? " (default)" : ""}`,
        profileKey: connection.profile.key,
        profileLabel: connection.profile.label,
        connectionName: connection.name,
        isDefault: connection.default,
      })),
    ],
    [connections],
  )
  const selectedConnectionOption =
    connectionOptions.find((option) => option.value === selectedConnectionId) ??
    connectionOptions[0]
  const recipientCount = splitAddresses(`${to},${cc},${bcc}`).length
  const recipientCountLabel = `${recipientCount} recipient${recipientCount === 1 ? "" : "s"}`
  const toSummary = splitAddresses(to).join(", ")
  const additionalRecipientDetails = [
    { label: "Cc", value: cc.trim() ? splitAddresses(cc).join(", ") : "" },
    { label: "Bcc", value: bcc.trim() ? splitAddresses(bcc).join(", ") : "" },
    { label: "Reply-To", value: replyTo.trim() ? splitAddresses(replyTo).join(", ") : "" },
  ].filter((detail) => detail.value !== "")
  const additionalRecipientSummary = additionalRecipientDetails
    .map(({ label, value }) => `${label}: ${value}`)
    .join(" · ")
  const parsedScheduleDate = sendAt === "" ? null : new Date(sendAt)
  const selectedScheduleDate =
    parsedScheduleDate !== null && !Number.isNaN(parsedScheduleDate.getTime())
      ? parsedScheduleDate
      : undefined
  const scheduleTime = selectedScheduleDate ? toLocalTimeInputValue(selectedScheduleDate) : ""
  const minimumScheduleTime =
    selectedScheduleDate?.toDateString() === new Date().toDateString()
      ? toLocalTimeInputValue(getDefaultScheduleDate(selectedScheduleDate))
      : undefined
  const scheduleFooterNote =
    sendAt === "" ? "Send now" : `Scheduled for ${formatScheduleDateTime(sendAt)}`
  const footerNote =
    step === 1
      ? sendAt !== ""
        ? `${recipientCount > 0 ? `${recipientCountLabel} · ` : ""}${scheduleFooterNote}`
        : to.trim() === ""
          ? "Add a recipient to continue."
          : `${recipientCountLabel} · Set delivery options`
      : `${recipientCountLabel} · ${scheduleFooterNote}`

  const handleScheduleDateSelect = (date: Date | undefined) => {
    if (!date) return

    const nextDateTime = selectedScheduleDate
      ? combineLocalDateAndTime(date, scheduleTime)
      : toLocalDateTimeInputValue(getDefaultScheduleDate(date))

    setSendAt(nextDateTime)
  }

  const handleScheduleTimeChange = (time: string) => {
    if (!selectedScheduleDate || time === "") return

    setSendAt(combineLocalDateAndTime(selectedScheduleDate, time))
  }

  const handleClearSchedule = () => {
    setSendAt("")
    setSchedulePickerOpen(false)
  }

  React.useEffect(() => {
    let active = true

    if (!open) {
      return () => {
        active = false
      }
    }

    setConnectionsLoading(true)
    setConnectionsError(null)

    void getConnections()
      .then((response) => {
        if (active) {
          setConnections(response.connections.filter((connection) => connection.enabled && connection.id !== null))
        }
      })
      .catch(() => {
        if (active) {
          setConnections([])
          setConnectionsError("Connection profiles could not be loaded. Configured routing is still available.")
        }
      })
      .finally(() => {
        if (active) {
          setConnectionsLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [open])

  const resetForm = React.useCallback(() => {
    setTo("")
    setCc("")
    setBcc("")
    setReplyTo("")
    setSelectedConnectionId(DEFAULT_CONNECTION_VALUE)
    setSendAt("")
    setSchedulePickerOpen(false)
    setSubject("")
    setBodyHtml("")
    setBodyEmailHtml("")
    setBodyText("")
    setStep(1)
    setRecipientsAttempted(false)
    setSubmitted(false)
    setServerError(null)
  }, [])

  const handleOpenChange = React.useCallback(
    (nextOpen: boolean) => {
      if (sending) return
      if (!nextOpen) resetForm()
      onOpenChange(nextOpen)
    },
    [onOpenChange, resetForm, sending],
  )

  const handleBodyChange = React.useCallback((html: string, text: string, emailHtml: string) => {
    setBodyHtml(html)
    setBodyEmailHtml(emailHtml)
    setBodyText(text)
  }, [])

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setServerError(null)

    const nextErrors = validateForm({ to, cc, bcc, replyTo, subject, sendAt, body: bodyText })

    if (step === 1) {
      setRecipientsAttempted(true)

      if (RECIPIENT_STEP_FIELDS.some((field) => nextErrors[field] !== undefined)) return

      setStep(2)
      return
    }

    setSubmitted(true)

    if (Object.keys(nextErrors).length > 0) return

    setSending(true)

    try {
      const response = await composeEmail({
        to: to.trim(),
        cc: cc.trim(),
        bcc: bcc.trim(),
        replyTo: replyTo.trim(),
        subject: subject.trim(),
        htmlBody: bodyEmailHtml,
        connectionId:
          selectedConnectionId === DEFAULT_CONNECTION_VALUE ? undefined : Number(selectedConnectionId),
        sendAt: sendAt === "" ? undefined : new Date(sendAt).toISOString(),
      })

      toast.success(response.message)
      onSent?.()
      resetForm()
      onOpenChange(false)
    } catch (caughtError) {
      const message = caughtError instanceof Error ? caughtError.message : "The email could not be submitted."
      onSent?.()
      setServerError(message)
      toast.error(message)
    } finally {
      setSending(false)
    }
  }

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      <SheetContent
        side="right"
        showCloseButton={false}
        className="bg-popover inset-y-4 right-4 left-auto flex h-[calc(100svh-2rem)] w-[min(42rem,calc(100vw-2rem))] max-w-none flex-col gap-0 overflow-hidden rounded-xl border border-border/70 p-0 shadow-lg outline-none"
        style={{
          top: "calc(var(--wp-admin--admin-bar--height, 32px) + 1rem)",
          right: "1rem",
          bottom: "1rem",
          height: "calc(100svh - var(--wp-admin--admin-bar--height, 32px) - 2rem)",
          width: "min(42rem, calc(100vw - 2rem))",
          maxWidth: "none",
        }}
      >
        <SheetHeader className="shrink-0 gap-2.5 border-b px-5 py-3">
          <div className="flex items-center justify-between gap-2">
            <div className="flex min-w-0 flex-col gap-0.5">
              <SheetTitle className="min-w-0 truncate text-base font-semibold tracking-tight">
                Compose email
              </SheetTitle>
              <SheetDescription className="text-xs text-muted-foreground">
                {step === 1 ? "Set recipients and delivery details." : "Write and format your message."}
              </SheetDescription>
            </div>
            {!sending ? (
              <SheetClose
                render={
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Close compose email"
                    className="shrink-0"
                  />
                }
              >
                <Cancel01Icon aria-hidden="true" />
              </SheetClose>
            ) : (
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label="Sending email"
                className="shrink-0"
                disabled
              >
                <Cancel01Icon aria-hidden="true" />
              </Button>
            )}
          </div>
          <ComposeStepNav step={step} canOpenMessage={canOpenMessage} onStepChange={setStep} />
        </SheetHeader>

        <form id={formId} onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col" noValidate>
          <div
            ref={setScrollAreaElement}
            className="supports-[animation-timeline:scroll()]:scroll-fade-y no-scrollbar min-h-0 flex-1 scroll-py-10 overflow-y-auto"
          >
            <div className="flex flex-col gap-4 px-5 py-4">
              {step === 1 ? (
                <>
                  <Section label="Recipients">
                    <FieldGroup className="gap-4">
                      <div className="grid gap-4 sm:grid-cols-2">
                        <Field data-invalid={errors.to !== undefined || undefined}>
                          <FieldLabel htmlFor={toId}>
                            To <span aria-hidden="true" className="text-destructive">*</span>
                          </FieldLabel>
                          <RecipientAutocompleteInput
                            id={toId}
                            value={to}
                            setValue={setTo}
                            enabled={open}
                            disabled={sending}
                            required
                            invalid={errors.to !== undefined}
                            autoFocus
                            describedBy={`${toId}-description`}
                            placeholder="name@example.com"
                          />
                          <FieldDescription id={`${toId}-description`}>
                            Type a name or email; separate addresses with commas.
                          </FieldDescription>
                          <FieldError>{errors.to}</FieldError>
                        </Field>

                        <Field data-invalid={errors.cc !== undefined || undefined}>
                          <FieldLabel htmlFor={ccId}>Cc</FieldLabel>
                          <RecipientAutocompleteInput
                            id={ccId}
                            value={cc}
                            setValue={setCc}
                            enabled={open}
                            disabled={sending}
                            placeholder="Optional"
                            invalid={errors.cc !== undefined}
                          />
                          <FieldError>{errors.cc}</FieldError>
                        </Field>

                        <Field data-invalid={errors.bcc !== undefined || undefined}>
                          <FieldLabel htmlFor={bccId}>Bcc</FieldLabel>
                          <RecipientAutocompleteInput
                            id={bccId}
                            value={bcc}
                            setValue={setBcc}
                            enabled={open}
                            disabled={sending}
                            placeholder="Optional"
                            invalid={errors.bcc !== undefined}
                          />
                          <FieldError>{errors.bcc}</FieldError>
                        </Field>

                        <Field data-invalid={errors.replyTo !== undefined || undefined}>
                          <FieldLabel htmlFor={replyToId}>Reply-To</FieldLabel>
                          <Input
                            id={replyToId}
                            type="email"
                            value={replyTo}
                            placeholder="Optional reply address"
                            autoComplete="email"
                            disabled={sending}
                            aria-invalid={errors.replyTo !== undefined || undefined}
                            aria-describedby={`${replyToId}-description`}
                            onChange={(event) => setReplyTo(event.target.value)}
                          />
                          <FieldDescription id={`${replyToId}-description`}>
                            Replies go to this address.
                          </FieldDescription>
                          <FieldError>{errors.replyTo}</FieldError>
                        </Field>
                      </div>
                    </FieldGroup>
                  </Section>

                  <Separator className="opacity-60" />

                  <Section label="Delivery">
                    <FieldGroup className="gap-4">
                      <div className="grid gap-4 sm:grid-cols-2">
                        <Field>
                          <FieldLabel htmlFor={connectionId}>Connection profile</FieldLabel>
                          <Combobox
                            items={connectionOptions}
                            value={selectedConnectionOption}
                            onValueChange={(option) =>
                              setSelectedConnectionId(option?.value ?? DEFAULT_CONNECTION_VALUE)
                            }
                            itemToStringValue={(option) => option?.label ?? ""}
                          >
                            <ComboboxTrigger
                              id={connectionId}
                              render={
                                <Button
                                  variant="outline"
                                  className="w-full justify-between gap-2 font-normal"
                                  disabled={sending || connectionsLoading}
                                />
                              }
                            >
                              <ComboboxValue>
                                {(option: ConnectionProfileOption) => (
                                  <span className="flex min-w-0 items-center gap-2">
                                    {option.profileKey && option.profileLabel ? (
                                      <ProfileBrandIcon
                                        profileKey={option.profileKey}
                                        label={option.profileLabel}
                                        size="inline"
                                      />
                                    ) : (
                                      <MailIcon
                                        aria-hidden="true"
                                        className="size-4 shrink-0 text-muted-foreground"
                                      />
                                    )}
                                    <span className="truncate">{option.label}</span>
                                  </span>
                                )}
                              </ComboboxValue>
                            </ComboboxTrigger>
                            <ComboboxContent
                              side="top"
                              collisionBoundary={scrollAreaElement ?? undefined}
                              collisionAvoidance={{ side: "none", align: "shift" }}
                              className="max-w-(--anchor-width) min-w-(--anchor-width)"
                            >
                              <ComboboxInput
                                showTrigger={false}
                                aria-label="Search connection profiles"
                                placeholder="Search connection profiles"
                              />
                              <ComboboxEmpty className="pt-1 pb-2">
                                No connection profiles found.
                              </ComboboxEmpty>
                              <ComboboxList className="py-0">
                                {(option: ConnectionProfileOption) => (
                                    <ComboboxItem key={option.value} value={option} className="min-h-10">
                                    {option.profileKey && option.profileLabel ? (
                                      <span aria-hidden="true">
                                        <ProfileBrandIcon
                                          profileKey={option.profileKey}
                                          label={option.profileLabel}
                                          size="sm"
                                        />
                                      </span>
                                    ) : (
                                      <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                        <MailIcon aria-hidden="true" className="size-4" />
                                      </span>
                                    )}
                                    <span className="flex min-w-0 flex-col">
                                      <span className="truncate font-medium">
                                        {option.connectionName ?? "Use configured routing"}
                                      </span>
                                      <span className="truncate text-xs text-muted-foreground">
                                        {option.profileLabel ?? "Active routing rules and failover"}
                                        {option.isDefault ? " · Default" : ""}
                                      </span>
                                    </span>
                                  </ComboboxItem>
                                )}
                              </ComboboxList>
                            </ComboboxContent>
                          </Combobox>
                          <FieldDescription>
                            {connectionsError ?? "Tried first; routing can fail over."}
                          </FieldDescription>
                        </Field>

                        <Field data-invalid={errors.sendAt !== undefined || undefined}>
                          <FieldLabel htmlFor={sendAtId}>Send time</FieldLabel>
                          <Popover open={schedulePickerOpen} onOpenChange={setSchedulePickerOpen}>
                            <PopoverTrigger
                              render={
                                <Button
                                  id={sendAtId}
                                  type="button"
                                  variant="outline"
                                  className="w-full justify-start rounded-md px-2.5 text-left font-normal data-[empty=true]:text-muted-foreground"
                                  data-empty={sendAt === ""}
                                  disabled={sending}
                                  aria-invalid={errors.sendAt !== undefined || undefined}
                                  aria-describedby={`${sendAtId}-description`}
                                />
                              }
                            >
                              <Calendar03Icon data-icon="inline-start" />
                              {sendAt === "" ? "Choose date and time" : formatScheduleDateTime(sendAt)}
                            </PopoverTrigger>
                            <PopoverContent
                              align="start"
                              sideOffset={6}
                              className="w-auto gap-0 rounded-md p-0"
                            >
                              <Calendar
                                mode="single"
                                selected={selectedScheduleDate}
                                defaultMonth={selectedScheduleDate ?? new Date()}
                                disabled={{
                                  before: new Date(
                                    new Date().getFullYear(),
                                    new Date().getMonth(),
                                    new Date().getDate(),
                                  ),
                                }}
                                onSelect={handleScheduleDateSelect}
                              />
                              <div className="flex items-center gap-3 border-t px-3 py-2.5">
                                <FieldLabel htmlFor={scheduleTimeId}>Time</FieldLabel>
                                <Input
                                  id={scheduleTimeId}
                                  type="time"
                                  value={scheduleTime}
                                  min={minimumScheduleTime}
                                  disabled={sending || selectedScheduleDate === undefined}
                                  className="ms-auto w-32"
                                  onChange={(event) => handleScheduleTimeChange(event.target.value)}
                                />
                              </div>
                              {sendAt !== "" ? (
                                <div className="border-t px-3 py-2">
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={sending}
                                    onClick={handleClearSchedule}
                                  >
                                    Clear schedule
                                  </Button>
                                </div>
                              ) : null}
                            </PopoverContent>
                          </Popover>
                          <FieldDescription id={`${sendAtId}-description`}>
                            Uses this device’s time zone.
                          </FieldDescription>
                          <FieldError>{errors.sendAt}</FieldError>
                        </Field>
                      </div>
                    </FieldGroup>
                  </Section>
                </>
              ) : (
                <div className="flex flex-col gap-4">
                  <section
                    aria-label="Recipients and delivery summary"
                    className="flex min-w-0 items-center gap-3"
                  >
                    {selectedConnection && selectedConnection.id !== null ? (
                      <MailLogConnectionHoverCard
                        connectionId={selectedConnection.id}
                        connectionName={selectedConnection.name}
                        profileKey={selectedConnection.profile.key}
                        iconOnly
                      />
                    ) : (
                      <IconTile variant="outline" aria-hidden="true">
                        <MailIcon className="size-5" />
                      </IconTile>
                    )}
                    <div className="flex min-w-0 flex-1 flex-col">
                      <TruncatedTextTooltip value={toSummary} className="text-sm font-medium" />
                      {additionalRecipientSummary ? (
                        <TruncatedTextTooltip
                          value={additionalRecipientSummary}
                          className="text-xs text-muted-foreground"
                          tooltipContent={
                            <div className="flex flex-col gap-1.5">
                              {additionalRecipientDetails.map((detail) => (
                                <div
                                  key={detail.label}
                                  className="grid grid-cols-[auto_1fr] gap-2"
                                >
                                  <span className="opacity-70">{detail.label}</span>
                                  <span className="break-all">{detail.value}</span>
                                </div>
                              ))}
                            </div>
                          }
                        />
                      ) : null}
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="ms-auto shrink-0"
                      disabled={sending}
                      aria-label="Change recipients and delivery"
                      onClick={() => setStep(1)}
                    >
                      Change
                    </Button>
                  </section>

                  <Section label="Message">
                    <FieldGroup className="gap-4">
                      <Field data-invalid={errors.subject !== undefined || undefined}>
                        <FieldLabel htmlFor={subjectId}>
                          Subject <span aria-hidden="true" className="text-destructive">*</span>
                        </FieldLabel>
                        <Input
                          id={subjectId}
                          value={subject}
                          autoFocus
                          disabled={sending}
                          required
                          aria-required="true"
                          aria-invalid={errors.subject !== undefined || undefined}
                          onChange={(event) => setSubject(event.target.value)}
                        />
                        <FieldError>{errors.subject}</FieldError>
                      </Field>

                      <Field data-invalid={errors.body !== undefined || undefined}>
                        <FieldLabel htmlFor={bodyId}>
                          Email content <span aria-hidden="true" className="text-destructive">*</span>
                        </FieldLabel>
                        <EmailRichTextEditor
                          id={bodyId}
                          value={bodyHtml}
                          describedBy={errors.body ? `${bodyDescriptionId} ${bodyErrorId}` : bodyDescriptionId}
                          disabled={sending}
                          invalid={errors.body !== undefined}
                          onChange={handleBodyChange}
                        />
                        <FieldDescription id={bodyDescriptionId}>
                          Format text, add headings and lists, align content, and insert links.
                        </FieldDescription>
                        <FieldError id={bodyErrorId}>{errors.body}</FieldError>
                      </Field>
                    </FieldGroup>
                  </Section>
                </div>
              )}

              {serverError ? (
                <Section>
                  <Alert variant="destructive">
                    <AlertDescription>{serverError}</AlertDescription>
                  </Alert>
                </Section>
              ) : null}
            </div>
          </div>

          <div className="bg-muted flex shrink-0 items-center gap-2 border-t px-5 py-3">
            <p
              aria-live="polite"
              className="text-muted-foreground me-auto min-w-0 truncate text-xs"
              title={footerNote}
            >
              {footerNote}
            </p>
            {step === 1 ? (
              <>
                <Button
                  type="button"
                  variant="outline"
                  disabled={sending}
                  onClick={() => handleOpenChange(false)}
                >
                  Cancel
                </Button>
                <Button type="submit" form={formId} disabled={sending}>
                  Continue
                </Button>
              </>
            ) : (
              <>
                <Button
                  type="button"
                  variant="outline"
                  disabled={sending}
                  onClick={() => setStep(1)}
                >
                  Back
                </Button>
                <Button type="submit" form={formId} disabled={sending}>
                  {sending ? <Loader2Icon data-icon="inline-start" className="animate-spin" /> : <SendIcon data-icon="inline-start" />}
                  {sending ? "Sending…" : sendAt === "" ? "Send email" : "Schedule email"}
                </Button>
              </>
            )}
          </div>
        </form>
      </SheetContent>
    </Sheet>
  )
}
