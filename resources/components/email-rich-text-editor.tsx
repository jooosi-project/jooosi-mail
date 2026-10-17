import * as React from "react"
import { useEditor } from "@tiptap/react"

import { Frame, FramePanel } from "@/components/reui/frame"
import { TooltipProvider } from "@/components/ui/tooltip"
import { cn } from "@/lib/utils"
import { FormattingToolbar } from "@/components/blocks/rich-text-editor-1/components/formatting-toolbar"
import { RichTextContent } from "@/components/blocks/rich-text-editor-1/components/rich-text-content"
import { createRichTextExtensions } from "@/components/blocks/rich-text-editor-1/components/rich-text-extensions"
import { RichTextLinkBubble } from "@/components/blocks/rich-text-editor-1/components/rich-text-link"
import { toEmailSafeHtml } from "@/lib/email-safe-html"

type EmailRichTextEditorProps = {
  id: string
  value: string
  describedBy: string
  disabled: boolean
  invalid: boolean
  onChange: (html: string, text: string, emailHtml: string) => void
}

const EMAIL_EXTENSIONS = createRichTextExtensions({
  placeholder: "Write your message…",
})

export function EmailRichTextEditor({
  id,
  value,
  describedBy,
  disabled,
  invalid,
  onChange,
}: EmailRichTextEditorProps) {
  const [linkOpen, setLinkOpen] = React.useState(false)
  const editor = useEditor({
    extensions: EMAIL_EXTENSIONS,
    content: value,
    editable: !disabled,
    immediatelyRender: false,
    editorProps: {
      attributes: {
        id,
        role: "textbox",
        "aria-label": "Email message",
        "aria-multiline": "true",
        "aria-required": "true",
        "aria-invalid": invalid ? "true" : "false",
        "aria-describedby": describedBy,
      },
    },
    onUpdate: ({ editor: current, transaction }) => {
      if (!transaction.docChanged) return

      const html = current.getHTML()
      onChange(
        html,
        current.getText({ blockSeparator: "\n" }),
        toEmailSafeHtml(html, current.view.dom),
      )
    },
  })

  React.useEffect(() => {
    if (!editor) return
    editor.setEditable(!disabled)
  }, [disabled, editor])

  React.useEffect(() => {
    if (!editor || editor.getHTML() === value) return

    if (value === "") {
      setLinkOpen(false)
      editor.commands.clearContent(false)
      return
    }

    editor.commands.setContent(value, { emitUpdate: false })
  }, [editor, value])

  function openLinkFromKeyboard() {
    if (!editor?.isEditable) return

    setLinkOpen(Boolean(editor.can().toggleBold()))
  }

  return (
    <TooltipProvider delay={300}>
      <Frame dense spacing="default" className={cn("w-full", disabled && "opacity-60")}>
        <FramePanel className="flex min-h-0 flex-col p-0">
          <FormattingToolbar
            editor={editor}
            linkOpen={linkOpen}
            onLinkOpenChange={setLinkOpen}
          />
          <div className="relative min-h-52 flex-1">
            <RichTextContent
              editor={editor}
              onLinkShortcut={openLinkFromKeyboard}
              className="px-4 py-3 text-sm leading-6"
            />
            {editor ? (
              <RichTextLinkBubble
                editor={editor}
                onEdit={() => setLinkOpen(true)}
              />
            ) : null}
          </div>
        </FramePanel>
      </Frame>
    </TooltipProvider>
  )
}
