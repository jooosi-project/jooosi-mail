"use client"

/**
 * Tiptap document editor that fills its host: header, toolbar, scrolling page,
 * counts. The rich-text-* files are the kit; this file owns the document and save.
 * Customize: pass content and onSave, or swap DOCUMENT and DOCUMENT_META in data.ts.
 */
import { useEffect, useRef, useState } from "react"
import { Frame, FramePanel } from "@/components/reui/frame"
import { useEditor, type JSONContent } from "@tiptap/react"
import { cn } from "cn"

import { TooltipProvider } from "@/components/ui/tooltip"

// customize: the demo document and its title
import { DOCUMENT, DOCUMENT_META } from "./data"
import { DocumentFooter } from "./document-footer"
import { DocumentHeader, type SaveState } from "./document-header"
import { FormattingToolbar } from "./formatting-toolbar"
import { RichTextContent } from "./rich-text-content"
import { createRichTextExtensions } from "./rich-text-extensions"
import { RichTextLinkBubble } from "./rich-text-link"
import { useRichTextSelector } from "./rich-text-state"

// Module scope: useEditor compares these by identity on every render.
const EXTENSIONS = createRichTextExtensions({
  placeholder: "Write something, or type # and Space for a heading",
})

const EDITOR_PROPS = {
  attributes: {
    "aria-label": `${DOCUMENT_META.title} ${DOCUMENT_META.kind.toLowerCase()}`,
    "aria-multiline": "true",
  },
}

// How long typing has to pause before the draft is handed to onSave.
const SAVE_DELAY_MS = 900

interface RichTextEditorProps {
  className?: string
  /** The document to open; read once, when the editor is created. */
  content?: JSONContent
  /** Receives the document after each pause in typing. */
  onSave?: (content: JSONContent) => void
}

export function RichTextEditor({
  className,
  content = DOCUMENT,
  onSave,
}: RichTextEditorProps) {
  const [saveState, setSaveState] = useState<SaveState>("saved")
  const [edited, setEdited] = useState(false)
  const [linkOpen, setLinkOpen] = useState(false)
  const saveTimer = useRef<number | undefined>(undefined)

  const editor = useEditor({
    extensions: EXTENSIONS,
    content,
    editorProps: EDITOR_PROPS,
    immediatelyRender: false,
    onUpdate: ({ editor: current, transaction }) => {
      // Switching modes emits an update too, but only edits need saving.
      if (!transaction.docChanged) return

      setEdited(true)
      setSaveState("saving")
      window.clearTimeout(saveTimer.current)
      // customize: persist here, or pass onSave
      saveTimer.current = window.setTimeout(() => {
        onSave?.(current.getJSON())
        setSaveState("saved")
      }, SAVE_DELAY_MS)
    },
  })

  const editable = useRichTextSelector(
    editor,
    (current) => current?.isEditable ?? true
  )

  useEffect(() => () => window.clearTimeout(saveTimer.current), [])

  // Mod-K follows the toolbar: no link where marks are refused (code blocks).
  function openLinkFromKeyboard() {
    setLinkOpen(editable && Boolean(editor?.can().toggleBold()))
  }

  return (
    <TooltipProvider delay={300}>
      <Frame
        dense
        spacing="default"
        className={cn("h-full min-h-0 w-full", className)}
      >
        <DocumentHeader
          editor={editor}
          editable={editable}
          edited={edited}
          saveState={saveState}
          onEditableChange={(next) => editor?.setEditable(next)}
        />
        <FramePanel className="flex min-h-0 flex-col p-0">
          <FormattingToolbar
            editor={editor}
            linkOpen={linkOpen}
            onLinkOpenChange={setLinkOpen}
          />
          <div className="supports-[animation-timeline:scroll()]:scroll-fade-y relative min-h-0 flex-1 overflow-y-auto">
            <RichTextContent
              editor={editor}
              onLinkShortcut={openLinkFromKeyboard}
              className="mx-auto w-full max-w-3xl px-4 py-6 sm:px-8 sm:py-10"
            />
            {editor ? (
              <RichTextLinkBubble
                editor={editor}
                onEdit={() => setLinkOpen(true)}
              />
            ) : null}
          </div>
        </FramePanel>
        <DocumentFooter editor={editor} />
      </Frame>
    </TooltipProvider>
  )
}