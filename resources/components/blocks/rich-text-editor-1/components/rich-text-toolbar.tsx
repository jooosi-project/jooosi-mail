import {
  useLayoutEffect,
  useRef,
  useSyncExternalStore,
  type ComponentProps,
  type FocusEvent,
  type KeyboardEvent,
  type ReactNode,
} from "react"
import { cn } from "cn"

import { Button } from "@/components/ui/button"
import { Kbd, KbdGroup } from "@/components/ui/kbd"
import { Separator } from "@/components/ui/separator"
import { Toggle } from "@/components/ui/toggle"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"

// Every control carries this, so arrow keys can walk the bar in DOM order.
const TOOLBAR_ITEM = "[data-toolbar-item]"

const subscribeToPlatform = () => () => {}

/** The server has no navigator, so it renders Mac keys and the client corrects. */
export function useIsApplePlatform() {
  return useSyncExternalStore(
    subscribeToPlatform,
    () => /Mac|iPhone|iPad/.test(navigator.userAgent),
    () => true
  )
}

const APPLE_KEYS: Record<string, string> = {
  mod: "⌘",
  alt: "⌥",
  shift: "⇧",
}

const OTHER_KEYS: Record<string, string> = {
  mod: "Ctrl",
  alt: "Alt",
  shift: "Shift",
}

/** Menu shortcuts read as one compact string: ⌘⇧L on Apple, Ctrl+Shift+L elsewhere. */
export function useShortcutLabel(keys: readonly string[]) {
  const apple = useIsApplePlatform()
  const names = apple ? APPLE_KEYS : OTHER_KEYS

  return keys.map((key) => names[key] ?? key).join(apple ? "" : "+")
}

export function ShortcutKeys({ keys }: { keys: readonly string[] }) {
  const names = useIsApplePlatform() ? APPLE_KEYS : OTHER_KEYS

  return (
    <KbdGroup>
      {keys.map((key) => (
        <Kbd key={key}>{names[key] ?? key}</Kbd>
      ))}
    </KbdGroup>
  )
}

function isEnabled(item: HTMLElement) {
  return !item.hasAttribute("disabled") && !item.hasAttribute("data-disabled")
}

/** Arrows wrap at the ends, as the toolbar pattern expects. */
function stepFrom(key: string, current: number, last: number) {
  switch (key) {
    case "ArrowRight":
      return current === last ? 0 : current + 1
    case "ArrowLeft":
      return current === 0 ? last : current - 1
    case "Home":
      return 0
    case "End":
      return last
    default:
      return null
  }
}

/** One tab stop for the whole bar; arrows, Home and End move inside it. */
export function RichTextToolbar({
  className,
  children,
  ...props
}: ComponentProps<"div">) {
  const ref = useRef<HTMLDivElement>(null)
  const activeRef = useRef<HTMLElement | null>(null)

  function items() {
    return Array.from(
      ref.current?.querySelectorAll<HTMLElement>(TOOLBAR_ITEM) ?? []
    )
  }

  function rove(active: HTMLElement | null) {
    const all = items()
    const enabled = all.filter(isEnabled)
    const stop = active && enabled.includes(active) ? active : enabled[0]

    for (const item of all) {
      item.tabIndex = item === stop ? 0 : -1
    }
  }

  // Controls enable and disable with the caret, so the tab stop re-settles
  // after every render instead of pointing at a disabled button.
  useLayoutEffect(() => {
    rove(activeRef.current)
  })

  function handleFocus(event: FocusEvent<HTMLDivElement>) {
    if (event.target.matches(TOOLBAR_ITEM)) {
      activeRef.current = event.target
      rove(event.target)
    }
  }

  function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    const enabled = items().filter(isEnabled)
    const current = enabled.findIndex((item) => item === event.target)

    if (current === -1) return

    const next = stepFrom(event.key, current, enabled.length - 1)

    if (next === null) return

    event.preventDefault()
    enabled[next]?.focus()
    enabled[next]?.scrollIntoView({ block: "nearest", inline: "nearest" })
  }

  return (
    <div
      ref={ref}
      role="toolbar"
      aria-orientation="horizontal"
      onFocus={handleFocus}
      onKeyDown={handleKeyDown}
      className={cn(
        "no-scrollbar supports-[animation-timeline:scroll()]:scroll-fade-x flex items-center gap-1 overflow-x-auto overflow-y-hidden",
        className
      )}
      {...props}
    >
      {children}
    </div>
  )
}

export function RichTextToolbarGroup({
  label,
  children,
}: {
  label: string
  children: ReactNode
}) {
  return (
    <div
      role="group"
      aria-label={label}
      className="flex shrink-0 items-center gap-0.5"
    >
      {children}
    </div>
  )
}

export function RichTextToolbarSeparator() {
  return (
    <Separator
      orientation="vertical"
      className="h-4 shrink-0 data-vertical:self-center"
    />
  )
}

// A pointer press must not pull focus out of the text, or the caret blinks
// away and the selection the command needs is painted as lost.
export function keepEditorFocus(event: { preventDefault: () => void }) {
  event.preventDefault()
}

interface RichTextToggleProps {
  label: string
  shortcut?: readonly string[]
  pressed: boolean
  disabled?: boolean
  /** Commands toggle themselves, so the next pressed state is not needed. */
  onToggle: () => void
  children: ReactNode
}

export function RichTextToggle({
  label,
  shortcut,
  pressed,
  disabled,
  onToggle,
  children,
}: RichTextToggleProps) {
  // An icon has no label to pad, so the toggle stays square (px-0).
  return (
    <Tooltip>
      <TooltipTrigger
        render={
          <Toggle
            size="sm"
            aria-label={label}
            pressed={pressed}
            disabled={disabled}
            onPressedChange={onToggle}
            onMouseDown={keepEditorFocus}
            className="px-0"
            data-toolbar-item=""
          />
        }
      >
        {children}
      </TooltipTrigger>
      <TooltipContent>
        {label}
        {shortcut ? <ShortcutKeys keys={shortcut} /> : null}
      </TooltipContent>
    </Tooltip>
  )
}

interface RichTextButtonProps {
  label: string
  shortcut?: readonly string[]
  disabled?: boolean
  onClick: () => void
  children: ReactNode
}

export function RichTextButton({
  label,
  shortcut,
  disabled,
  onClick,
  children,
}: RichTextButtonProps) {
  return (
    <Tooltip>
      <TooltipTrigger
        render={
          <Button
            variant="ghost"
            size="icon-sm"
            aria-label={label}
            disabled={disabled}
            onClick={onClick}
            onMouseDown={keepEditorFocus}
            data-toolbar-item=""
          />
        }
      >
        {children}
      </TooltipTrigger>
      <TooltipContent>
        {label}
        {shortcut ? <ShortcutKeys keys={shortcut} /> : null}
      </TooltipContent>
    </Tooltip>
  )
}