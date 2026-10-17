import type { KeyboardEvent, MouseEvent } from "react"
import { EditorContent, type Editor } from "@tiptap/react"
import { cn } from "cn"

import { Skeleton } from "@/components/ui/skeleton"

import { HIGHLIGHT_COLORS } from "./rich-text-highlight"

/** Document typography from host tokens, scoped to .tiptap so getHTML() stays
 * class free and floating menus mounted beside the text stay unstyled. */
export const RICH_TEXT_PROSE = cn(
  "text-foreground flex min-h-full flex-col text-base/7",
  "[&_.tiptap]:flex-1 [&_.tiptap]:break-words [&_.tiptap]:outline-none",
  "[&_.tiptap>*+*]:mt-3",
  "[&_.tiptap_h1]:text-2xl/9 [&_.tiptap_h1]:font-semibold [&_.tiptap_h1]:tracking-tight",
  "[&_.tiptap_h2]:text-xl/8 [&_.tiptap_h2]:font-semibold [&_.tiptap_h2]:tracking-tight [&_.tiptap>h2:not(:first-child)]:mt-8",
  "[&_.tiptap_h3]:text-lg/7 [&_.tiptap_h3]:font-semibold [&_.tiptap>h3:not(:first-child)]:mt-6",
  "[&_.tiptap_ul]:list-disc [&_.tiptap_ul]:ps-6 [&_.tiptap_ol]:list-decimal [&_.tiptap_ol]:ps-6 [&_.tiptap_li+li]:mt-1 [&_.tiptap_li::marker]:text-muted-foreground",
  "[&_.tiptap_ul[data-type=taskList]]:list-none [&_.tiptap_ul[data-type=taskList]]:ps-0.5",
  "[&_.tiptap_li[data-checked=true]>div>[data-node-view-content]]:text-muted-foreground [&_.tiptap_li[data-checked=true]>div>[data-node-view-content]]:line-through",
  "[&_.tiptap_blockquote]:text-muted-foreground [&_.tiptap_blockquote]:border-s-2 [&_.tiptap_blockquote]:ps-4",
  "[&_.tiptap_pre]:bg-muted [&_.tiptap_pre]:rounded-md [&_.tiptap_pre]:px-4 [&_.tiptap_pre]:py-3 [&_.tiptap_pre]:font-mono [&_.tiptap_pre]:text-sm",
  "[&_.tiptap_:not(pre)>code]:bg-muted [&_.tiptap_:not(pre)>code]:rounded-sm [&_.tiptap_:not(pre)>code]:px-1 [&_.tiptap_:not(pre)>code]:py-0.5 [&_.tiptap_:not(pre)>code]:font-mono [&_.tiptap_:not(pre)>code]:text-sm",
  "[&_.tiptap_a]:text-primary [&_.tiptap_a]:underline [&_.tiptap_a]:underline-offset-4",
  "[&_.tiptap_hr]:border-border [&_.tiptap_hr]:my-6",
  "[&_.tiptap_mark]:px-0.5 [&_.tiptap_mark]:text-inherit",
  HIGHLIGHT_COLORS.map((color) => color.mark),
  "[&_.tiptap_.selection]:bg-primary/15",
  "[&_.tiptap_.ProseMirror-selectednode]:outline-ring/50 [&_.tiptap_.ProseMirror-selectednode]:outline-2",
  // Core paints the gap cursor black in an unlayered sheet, hence the !.
  "[&_.ProseMirror-gapcursor]:after:border-t-foreground!",
  "[&_.tiptap_.is-empty]:before:text-muted-foreground [&_.tiptap_.is-empty]:before:pointer-events-none [&_.tiptap_.is-empty]:before:float-start [&_.tiptap_.is-empty]:before:h-0 [&_.tiptap_.is-empty]:before:content-[attr(data-placeholder)]"
)

interface RichTextContentProps {
  editor: Editor | null
  /** The page inset; the skeleton shares it, so nothing shifts on mount. */
  className?: string
  /** Mod-K: Link ships no shortcut of its own. */
  onLinkShortcut?: () => void
}

export function RichTextContent({
  editor,
  className,
  onLinkShortcut,
}: RichTextContentProps) {
  if (!editor) {
    return <RichTextSkeleton className={className} />
  }

  function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    const mod = event.metaKey || event.ctrlKey

    if (mod && !event.altKey && event.key.toLowerCase() === "k") {
      event.preventDefault()
      onLinkShortcut?.()
    }
  }

  // A press on the inset around the text lands in it, as on a page.
  function handleMouseDown(event: MouseEvent<HTMLDivElement>) {
    if (event.target === event.currentTarget) {
      event.preventDefault()
      editor?.commands.focus("end")
    }
  }

  return (
    <EditorContent
      editor={editor}
      onKeyDown={handleKeyDown}
      onMouseDown={handleMouseDown}
      className={cn(RICH_TEXT_PROSE, className)}
    />
  )
}

/** Holds the page's shape for the frame before the editor mounts. */
function RichTextSkeleton({ className }: { className?: string }) {
  return (
    <div aria-hidden="true" className={cn("flex flex-col gap-8", className)}>
      <div className="flex flex-col gap-4">
        <Skeleton className="h-8 w-2/3" />
        <div className="flex flex-col gap-2.5">
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-11/12" />
          <Skeleton className="h-4 w-3/5" />
        </div>
      </div>
      <div className="flex flex-col gap-4">
        <Skeleton className="h-6 w-1/4" />
        <div className="flex flex-col gap-2.5">
          <Skeleton className="h-4 w-5/6" />
          <Skeleton className="h-4 w-3/4" />
        </div>
      </div>
    </div>
  )
}