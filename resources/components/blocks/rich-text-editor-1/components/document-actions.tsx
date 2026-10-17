import { useEffect, useRef, useState } from "react"
import type { Editor } from "@tiptap/react"
import { toast } from "sonner"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { DOCUMENT_META } from "./data"
import { TOAST_ERROR_ICON, TOAST_SUCCESS_ICON } from "./icons"
import { HugeiconsIcon } from "@hugeicons/react"
import { MoreHorizontalCircle01Icon, PenIcon, ViewIcon, Tick02Icon, SourceCodeIcon, Copy01Icon, Delete02Icon } from "@hugeicons/core-free-icons"

type Mode = "editing" | "viewing"

type CopyFormat = "html" | "text"

// Long enough to read the swap, short enough to copy again.
const COPIED_MS = 1600

const DOCUMENT_TITLE = `${DOCUMENT_META.title} ${DOCUMENT_META.kind.toLowerCase()}`

const isMode = (value: unknown): value is Mode =>
  value === "editing" || value === "viewing"

async function writeClipboard(value: string) {
  try {
    await navigator.clipboard.writeText(value)
    return true
  } catch {
    toast.error("Copy failed", {
      icon: TOAST_ERROR_ICON,
      description: "The browser blocked clipboard access.",
    })
    return false
  }
}

interface DocumentActionsProps {
  editor: Editor | null
  editable: boolean
  onEditableChange: (editable: boolean) => void
}

/** Mode, export and clear: the document-level actions behind one menu. */
export function DocumentActions({
  editor,
  editable,
  onEditableChange,
}: DocumentActionsProps) {
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [copied, setCopied] = useState<CopyFormat | null>(null)
  const copiedTimer = useRef<number | undefined>(undefined)

  useEffect(() => () => window.clearTimeout(copiedTimer.current), [])

  // A copy confirms in place on the item; only a failure raises a toast.
  async function copyAs(format: CopyFormat) {
    if (!editor) return

    const value =
      format === "html"
        ? editor.getHTML()
        : editor.getText({ blockSeparator: "\n\n" })

    if (await writeClipboard(value)) {
      setCopied(format)
      window.clearTimeout(copiedTimer.current)
      copiedTimer.current = window.setTimeout(() => setCopied(null), COPIED_MS)
    }
  }

  function clearDocument() {
    editor?.commands.clearContent()
    setConfirmOpen(false)
    toast.success("Document cleared", {
      icon: TOAST_SUCCESS_ICON,
      description: DOCUMENT_TITLE,
      action: {
        label: "Undo",
        onClick: () => editor?.chain().focus().undo().run(),
      },
    })
  }

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger
          render={
            <Button
              variant="ghost"
              size="icon-sm"
              aria-label="Document actions"
            />
          }
        >
          <HugeiconsIcon icon={MoreHorizontalCircle01Icon} strokeWidth={2} aria-hidden="true" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-52">
          <DropdownMenuGroup>
            <DropdownMenuLabel>Mode</DropdownMenuLabel>
            <DropdownMenuRadioGroup
              value={editable ? "editing" : "viewing"}
              onValueChange={(value) => {
                if (isMode(value)) onEditableChange(value === "editing")
              }}
            >
              <DropdownMenuRadioItem value="editing" closeOnClick>
                <HugeiconsIcon icon={PenIcon} strokeWidth={2} aria-hidden="true" />
                Editing
              </DropdownMenuRadioItem>
              <DropdownMenuRadioItem value="viewing" closeOnClick>
                <HugeiconsIcon icon={ViewIcon} strokeWidth={2} aria-hidden="true" />
                Viewing
              </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
          <DropdownMenuGroup>
            <DropdownMenuLabel>Export</DropdownMenuLabel>
            {/* Stays open, so the swap to Copied is seen where it was clicked. */}
            <DropdownMenuItem
              closeOnClick={false}
              disabled={!editor}
              onClick={() => copyAs("html")}
            >
              {copied === "html" ? (
                <HugeiconsIcon icon={Tick02Icon} strokeWidth={2} aria-hidden="true" />
              ) : (
                <HugeiconsIcon icon={SourceCodeIcon} strokeWidth={2} aria-hidden="true" />
              )}
              {copied === "html" ? "Copied" : "Copy HTML"}
            </DropdownMenuItem>
            <DropdownMenuItem
              closeOnClick={false}
              disabled={!editor}
              onClick={() => copyAs("text")}
            >
              {copied === "text" ? (
                <HugeiconsIcon icon={Tick02Icon} strokeWidth={2} aria-hidden="true" />
              ) : (
                <HugeiconsIcon icon={Copy01Icon} strokeWidth={2} aria-hidden="true" />
              )}
              {copied === "text" ? "Copied" : "Copy Text"}
            </DropdownMenuItem>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            variant="destructive"
            disabled={!editor || !editable}
            onClick={() => setConfirmOpen(true)}
          >
            <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} aria-hidden="true" />
            Clear Document
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        {/* The menu item that opened it is gone, so focus lands in the page. */}
        <AlertDialogContent
          size="sm"
          finalFocus={() => editor?.view.dom ?? true}
        >
          <AlertDialogHeader>
            <AlertDialogTitle>Clear Document?</AlertDialogTitle>
            <AlertDialogDescription>
              Every line of{" "}
              <span className="text-foreground font-medium">
                {DOCUMENT_TITLE}
              </span>{" "}
              is removed until you undo it.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction variant="destructive" onClick={clearDocument}>
              Clear Document
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}