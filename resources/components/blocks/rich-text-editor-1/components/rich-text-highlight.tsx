import { useState } from "react"
import { Highlight } from "@tiptap/extension-highlight"
import type { Editor } from "@tiptap/react"
import { cn } from "cn"

import { Button } from "@/components/ui/button"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { Separator } from "@/components/ui/separator"
import { Toggle } from "@/components/ui/toggle"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import type { RichTextSnapshot } from "./rich-text-state"
import { keepEditorFocus, ShortcutKeys } from "./rich-text-toolbar"
import { HugeiconsIcon } from "@hugeicons/react"
import { HighlighterIcon, Tick02Icon, UnavailableIcon } from "@hugeicons/core-free-icons"

interface HighlightColor {
  id: string
  label: string
  /** The swatch in the picker. */
  swatch: string
  /** The painted mark inside the document, as a prose descendant class. */
  mark: string
}

// Light and dark pairs per color, so a highlight stays legible in both themes.
export const HIGHLIGHT_COLORS = [
  {
    id: "yellow",
    label: "Yellow",
    swatch: "bg-yellow-200 dark:bg-yellow-400/40",
    mark: "[&_.tiptap_mark]:bg-yellow-200 dark:[&_.tiptap_mark]:bg-yellow-400/30",
  },
  {
    id: "green",
    label: "Green",
    swatch: "bg-emerald-200 dark:bg-emerald-400/40",
    mark: "[&_.tiptap_mark[data-color=green]]:bg-emerald-200 dark:[&_.tiptap_mark[data-color=green]]:bg-emerald-400/30",
  },
  {
    id: "blue",
    label: "Blue",
    swatch: "bg-sky-200 dark:bg-sky-400/40",
    mark: "[&_.tiptap_mark[data-color=blue]]:bg-sky-200 dark:[&_.tiptap_mark[data-color=blue]]:bg-sky-400/30",
  },
  {
    id: "pink",
    label: "Pink",
    swatch: "bg-pink-200 dark:bg-pink-400/40",
    mark: "[&_.tiptap_mark[data-color=pink]]:bg-pink-200 dark:[&_.tiptap_mark[data-color=pink]]:bg-pink-400/30",
  },
  {
    id: "violet",
    label: "Violet",
    swatch: "bg-violet-200 dark:bg-violet-400/40",
    mark: "[&_.tiptap_mark[data-color=violet]]:bg-violet-200 dark:[&_.tiptap_mark[data-color=violet]]:bg-violet-400/30",
  },
] as const satisfies readonly HighlightColor[]

export type HighlightColorId = (typeof HIGHLIGHT_COLORS)[number]["id"]

/** Stores the color id, never inline CSS, so the prose classes theme it. */
export const RichTextHighlight = Highlight.extend({
  addAttributes() {
    return {
      color: {
        default: null,
        parseHTML: (element) => element.getAttribute("data-color"),
        renderHTML: (attributes) =>
          attributes.color ? { "data-color": attributes.color } : {},
      },
    }
  },
})

interface RichTextHighlightPopoverProps {
  editor: Editor | null
  state: RichTextSnapshot
}

export function RichTextHighlightPopover({
  editor,
  state,
}: RichTextHighlightPopoverProps) {
  const [open, setOpen] = useState(false)

  function apply(color: HighlightColorId | null) {
    const chain = editor?.chain().focus()

    if (color) {
      chain?.setHighlight({ color }).run()
    } else {
      chain?.unsetHighlight().run()
    }

    setOpen(false)
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <Tooltip>
        {/* The span carries the tooltip, so the trigger keeps its own props. */}
        <TooltipTrigger render={<span className="flex" />}>
          <PopoverTrigger
            render={
              <Toggle
                size="sm"
                aria-label="Highlight"
                pressed={state.highlight !== null}
                disabled={!state.canFormat}
                onMouseDown={keepEditorFocus}
                className="px-0"
                data-toolbar-item=""
              />
            }
          >
            <HugeiconsIcon icon={HighlighterIcon} strokeWidth={2} aria-hidden="true" />
          </PopoverTrigger>
        </TooltipTrigger>
        <TooltipContent>
          Highlight
          <ShortcutKeys keys={["mod", "shift", "H"]} />
        </TooltipContent>
      </Tooltip>
      <PopoverContent
        align="start"
        aria-label="Highlight color"
        className="w-auto"
        finalFocus={() => editor?.view.dom ?? true}
      >
        <div className="flex items-center gap-1">
          {HIGHLIGHT_COLORS.map((color) => {
            const selected = state.highlight === color.id

            return (
              <Button
                key={color.id}
                variant="ghost"
                size="icon-sm"
                aria-label={`${color.label} highlight`}
                aria-pressed={selected}
                onClick={() => apply(color.id)}
              >
                <span
                  aria-hidden="true"
                  className={cn(
                    "text-foreground flex size-5 items-center justify-center rounded-full",
                    color.swatch
                  )}
                >
                  {selected ? (
                    <HugeiconsIcon icon={Tick02Icon} strokeWidth={2} aria-hidden="true" />
                  ) : null}
                </span>
              </Button>
            )
          })}
          <Separator
            orientation="vertical"
            className="h-4 data-vertical:self-center"
          />
          <Button
            variant="ghost"
            size="icon-sm"
            aria-label="Remove highlight"
            disabled={state.highlight === null}
            onClick={() => apply(null)}
          >
            <HugeiconsIcon icon={UnavailableIcon} strokeWidth={2} aria-hidden="true" />
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  )
}