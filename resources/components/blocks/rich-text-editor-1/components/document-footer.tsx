import { FrameFooter } from "@/components/reui/frame"
import type { Editor } from "@tiptap/react"

import { Dot } from "./document-header"
import { RichTextShortcuts } from "./rich-text-shortcuts"
import { useRichTextCount } from "./rich-text-state"

const NUMBER = new Intl.NumberFormat("en-US")

export function DocumentFooter({ editor }: { editor: Editor | null }) {
  const { words, characters, minutes } = useRichTextCount(editor)

  return (
    <FrameFooter className="flex-row items-center justify-between gap-2">
      <p className="text-muted-foreground flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs tabular-nums">
        <span>
          <span className="text-foreground font-medium">
            {NUMBER.format(words)}
          </span>{" "}
          {words === 1 ? "word" : "words"}
        </span>
        <span className="inline-flex items-center gap-1.5 max-sm:hidden">
          <Dot />
          <span>
            <span className="text-foreground font-medium">
              {NUMBER.format(characters)}
            </span>{" "}
            characters
          </span>
        </span>
        {minutes > 0 ? (
          <span className="inline-flex items-center gap-1.5">
            <Dot />
            {minutes} min read
          </span>
        ) : null}
      </p>
      <RichTextShortcuts />
    </FrameFooter>
  )
}