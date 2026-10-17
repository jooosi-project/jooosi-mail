import type { Editor } from "@tiptap/react"

import {
  RichTextAlignMenu,
  RichTextBlockToggles,
  RichTextBlockTypeMenu,
  RichTextHistory,
  RichTextListToggles,
  RichTextMarkToggles,
} from "./rich-text-controls"
import { RichTextHighlightPopover } from "./rich-text-highlight"
import { RichTextLinkPopover } from "./rich-text-link"
import { useRichTextState } from "./rich-text-state"
import {
  RichTextToolbar,
  RichTextToolbarGroup,
  RichTextToolbarSeparator,
} from "./rich-text-toolbar"

interface FormattingToolbarProps {
  editor: Editor | null
  linkOpen: boolean
  onLinkOpenChange: (open: boolean) => void
}

/** Style follows structure so a phone shows the marks without scrolling. */
export function FormattingToolbar({
  editor,
  linkOpen,
  onLinkOpenChange,
}: FormattingToolbarProps) {
  const state = useRichTextState(editor)

  return (
    <RichTextToolbar
      aria-label="Formatting"
      className="shrink-0 border-b px-(--frame-panel-header-px) py-(--frame-panel-header-py)"
    >
      <RichTextHistory editor={editor} state={state} />
      <RichTextToolbarSeparator />
      <RichTextBlockTypeMenu editor={editor} state={state} />
      <RichTextToolbarSeparator />
      <RichTextMarkToggles editor={editor} state={state} />
      <RichTextToolbarGroup label="Annotate">
        <RichTextHighlightPopover editor={editor} state={state} />
        <RichTextLinkPopover
          editor={editor}
          state={state}
          open={linkOpen}
          onOpenChange={onLinkOpenChange}
        />
      </RichTextToolbarGroup>
      <RichTextToolbarSeparator />
      <RichTextListToggles editor={editor} state={state} />
      <RichTextBlockToggles editor={editor} state={state} />
      <RichTextToolbarSeparator />
      <RichTextAlignMenu editor={editor} state={state} />
    </RichTextToolbar>
  )
}