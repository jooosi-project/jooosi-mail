import { useEditorState, type Editor } from "@tiptap/react"

export type RichTextBlockType =
  "paragraph" | "heading-1" | "heading-2" | "heading-3"

export type RichTextAlign = "left" | "center" | "right" | "justify"

export const RICH_TEXT_ALIGNS = [
  "left",
  "center",
  "right",
  "justify",
] as const satisfies readonly RichTextAlign[]

export interface RichTextSnapshot {
  editable: boolean
  blockType: RichTextBlockType
  bold: boolean
  italic: boolean
  underline: boolean
  strike: boolean
  code: boolean
  bulletList: boolean
  orderedList: boolean
  taskList: boolean
  blockquote: boolean
  codeBlock: boolean
  align: RichTextAlign
  highlight: string | null
  link: string | null
  canUndo: boolean
  canRedo: boolean
  /** Code blocks refuse marks and inline code excludes the rest, so they step aside. */
  canFormat: boolean
  /** Inline code stays toggleable while active, so it can be turned off. */
  canCode: boolean
  /** A heading is refused inside list and task items. */
  canHeading: boolean
}

export interface RichTextCount {
  words: number
  characters: number
  minutes: number
}

const IDLE_SNAPSHOT: RichTextSnapshot = {
  editable: false,
  blockType: "paragraph",
  bold: false,
  italic: false,
  underline: false,
  strike: false,
  code: false,
  bulletList: false,
  orderedList: false,
  taskList: false,
  blockquote: false,
  codeBlock: false,
  align: "left",
  highlight: null,
  link: null,
  canUndo: false,
  canRedo: false,
  canFormat: false,
  canCode: false,
  canHeading: false,
}

const IDLE_COUNT: RichTextCount = { words: 0, characters: 0, minutes: 0 }

const WORDS_PER_MINUTE = 230

function readBlockType(editor: Editor): RichTextBlockType {
  if (editor.isActive("heading", { level: 1 })) return "heading-1"
  if (editor.isActive("heading", { level: 2 })) return "heading-2"
  if (editor.isActive("heading", { level: 3 })) return "heading-3"
  return "paragraph"
}

function readSnapshot(editor: Editor | null): RichTextSnapshot {
  if (!editor) return IDLE_SNAPSHOT

  const editable = editor.isEditable
  const highlight = editor.isActive("highlight")
    ? (editor.getAttributes("highlight").color ?? "yellow")
    : null
  const link = editor.isActive("link")
    ? (editor.getAttributes("link").href ?? null)
    : null

  return {
    editable,
    blockType: readBlockType(editor),
    bold: editor.isActive("bold"),
    italic: editor.isActive("italic"),
    underline: editor.isActive("underline"),
    strike: editor.isActive("strike"),
    code: editor.isActive("code"),
    bulletList: editor.isActive("bulletList"),
    orderedList: editor.isActive("orderedList"),
    taskList: editor.isActive("taskList"),
    blockquote: editor.isActive("blockquote"),
    codeBlock: editor.isActive("codeBlock"),
    align:
      RICH_TEXT_ALIGNS.find((align) => editor.isActive({ textAlign: align })) ??
      "left",
    highlight,
    link,
    canUndo: editable && editor.can().undo(),
    canRedo: editable && editor.can().redo(),
    canFormat: editable && editor.can().toggleBold(),
    canCode: editable && editor.can().toggleCode(),
    canHeading: editable && editor.can().setHeading({ level: 1 }),
  }
}

// Tiptap's snapshot keeps a null editor until the first transaction after mount,
// so every selector falls back to the instance it was handed.
export function useRichTextSelector<T>(
  editor: Editor | null,
  select: (editor: Editor | null) => T
) {
  return useEditorState({
    editor,
    selector: ({ editor: current }) => select(current ?? editor),
  }) as T
}

/** One formatting snapshot per transaction, compared deeply, for the toolbar. */
export function useRichTextState(editor: Editor | null) {
  return useRichTextSelector(editor, readSnapshot)
}

function readCount(editor: Editor | null): RichTextCount {
  if (!editor) return IDLE_COUNT

  const words = editor.storage.characterCount.words()

  return {
    words,
    characters: editor.storage.characterCount.characters(),
    minutes: words === 0 ? 0 : Math.ceil(words / WORDS_PER_MINUTE),
  }
}

/** Counts live apart from formatting, so a keystroke re-renders only them. */
export function useRichTextCount(editor: Editor | null) {
  return useRichTextSelector(editor, readCount)
}