import { useId, type ReactNode } from "react"

import { Button } from "@/components/ui/button"
import { Kbd, KbdGroup } from "@/components/ui/kbd"
import {
  Popover,
  PopoverContent,
  PopoverHeader,
  PopoverTitle,
  PopoverTrigger,
} from "@/components/ui/popover"
import { ShortcutKeys } from "./rich-text-toolbar"
import { HugeiconsIcon } from "@hugeicons/react"
import { KeyboardIcon } from "@hugeicons/core-free-icons"

const KEY_SHORTCUTS = [
  { label: "Bold", keys: ["mod", "B"] },
  { label: "Italic", keys: ["mod", "I"] },
  { label: "Underline", keys: ["mod", "U"] },
  { label: "Strikethrough", keys: ["mod", "shift", "S"] },
  { label: "Inline code", keys: ["mod", "E"] },
  { label: "Highlight", keys: ["mod", "shift", "H"] },
  { label: "Link", keys: ["mod", "K"] },
  { label: "Heading 1 to 3", keys: ["mod", "alt", "1"] },
  { label: "Task list", keys: ["mod", "shift", "9"] },
  { label: "Indent a task", keys: ["Tab"] },
] as const

// Typed at the start of a line, then Space, except where the rule closes itself.
const MARKDOWN_SHORTCUTS = [
  { label: "Heading", keys: ["#", "Space"] },
  { label: "Bullet list", keys: ["-", "Space"] },
  { label: "Numbered list", keys: ["1.", "Space"] },
  { label: "Task list", keys: ["[ ]", "Space"] },
  { label: "Quote", keys: [">", "Space"] },
  { label: "Code block", keys: ["```", "Space"] },
  { label: "Divider", keys: ["---"] },
  { label: "Highlight", keys: ["==text=="] },
] as const

/** The shortcut sheet, so the fast paths are learnable from the page itself. */
export function RichTextShortcuts() {
  const titleId = useId()

  return (
    <Popover>
      <PopoverTrigger
        render={
          <Button variant="ghost" size="sm" className="text-muted-foreground" />
        }
      >
        <HugeiconsIcon icon={KeyboardIcon} strokeWidth={2} aria-hidden="true" />
        <span className="max-sm:sr-only">Shortcuts</span>
      </PopoverTrigger>
      <PopoverContent
        align="end"
        side="top"
        aria-labelledby={titleId}
        className="w-80"
      >
        <PopoverHeader>
          <PopoverTitle id={titleId}>Keyboard Shortcuts</PopoverTitle>
        </PopoverHeader>
        {/* The bar stays hidden; the fades alone say there is more to scroll. */}
        <div className="no-scrollbar supports-[animation-timeline:scroll()]:scroll-fade-y flex max-h-80 min-h-0 flex-col gap-4 overflow-y-auto">
          <ShortcutList title="Formatting">
            {KEY_SHORTCUTS.map((shortcut) => (
              <ShortcutRow key={shortcut.label} label={shortcut.label}>
                <ShortcutKeys keys={shortcut.keys} />
              </ShortcutRow>
            ))}
          </ShortcutList>
          <ShortcutList title="Markdown">
            {MARKDOWN_SHORTCUTS.map((shortcut) => (
              <ShortcutRow key={shortcut.label} label={shortcut.label}>
                <KbdGroup>
                  {shortcut.keys.map((key) => (
                    <Kbd key={key}>{key}</Kbd>
                  ))}
                </KbdGroup>
              </ShortcutRow>
            ))}
          </ShortcutList>
        </div>
      </PopoverContent>
    </Popover>
  )
}

function ShortcutList({
  title,
  children,
}: {
  title: string
  children: ReactNode
}) {
  return (
    <section className="flex flex-col gap-1.5">
      <h3 className="text-muted-foreground text-xs font-medium">{title}</h3>
      <dl className="flex flex-col gap-1.5">{children}</dl>
    </section>
  )
}

function ShortcutRow({
  label,
  children,
}: {
  label: string
  children: ReactNode
}) {
  return (
    <div className="flex items-center justify-between gap-4 text-sm">
      <dt>{label}</dt>
      <dd>{children}</dd>
    </div>
  )
}