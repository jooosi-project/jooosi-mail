import type { ReactNode } from "react"
import type { Editor } from "@tiptap/react"

import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import {
  RICH_TEXT_ALIGNS,
  type RichTextAlign,
  type RichTextBlockType,
  type RichTextSnapshot,
} from "./rich-text-state"
import {
  RichTextButton,
  RichTextToggle,
  RichTextToolbarGroup,
  useShortcutLabel,
} from "./rich-text-toolbar"
import { HugeiconsIcon } from "@hugeicons/react"
import { Undo02Icon, Redo02Icon, TextIcon, Heading01Icon, Heading02Icon, Heading03Icon, ArrowDown01Icon, LeftToRightListBulletIcon, LeftToRightListNumberIcon, CheckListIcon, LeftToRightBlockQuoteIcon, SourceCodeSquareIcon, MinusSignIcon, TextBoldIcon, TextItalicIcon, TextUnderlineIcon, TextStrikethroughIcon, SourceCodeIcon, TextAlignLeftIcon, TextAlignCenterIcon, TextAlignRightIcon, TextAlignJustifyLeftIcon } from "@hugeicons/core-free-icons"

interface ControlProps {
  editor: Editor | null
  state: RichTextSnapshot
}

// Closing a menu hands the caret back to the text, not to the trigger.
function focusEditor(editor: Editor | null) {
  return () => editor?.view.dom ?? true
}

export function RichTextHistory({ editor, state }: ControlProps) {
  return (
    <RichTextToolbarGroup label="History">
      <RichTextButton
        label="Undo"
        shortcut={["mod", "Z"]}
        disabled={!state.canUndo}
        onClick={() => editor?.chain().focus().undo().run()}
      >
        <HugeiconsIcon icon={Undo02Icon} strokeWidth={2} aria-hidden="true" />
      </RichTextButton>
      <RichTextButton
        label="Redo"
        shortcut={["mod", "shift", "Z"]}
        disabled={!state.canRedo}
        onClick={() => editor?.chain().focus().redo().run()}
      >
        <HugeiconsIcon icon={Redo02Icon} strokeWidth={2} aria-hidden="true" />
      </RichTextButton>
    </RichTextToolbarGroup>
  )
}

interface BlockTypeOption {
  value: RichTextBlockType
  label: string
  icon: ReactNode
  keys: readonly string[]
}

// Whole static glyphs per type, so the trigger can mirror the current block.
const BLOCK_TYPES: BlockTypeOption[] = [
  {
    value: "paragraph",
    label: "Text",
    keys: ["mod", "alt", "0"],
    icon: (
      <HugeiconsIcon icon={TextIcon} strokeWidth={2} aria-hidden="true" />
    ),
  },
  {
    value: "heading-1",
    label: "Heading 1",
    keys: ["mod", "alt", "1"],
    icon: (
      <HugeiconsIcon icon={Heading01Icon} strokeWidth={2} aria-hidden="true" />
    ),
  },
  {
    value: "heading-2",
    label: "Heading 2",
    keys: ["mod", "alt", "2"],
    icon: (
      <HugeiconsIcon icon={Heading02Icon} strokeWidth={2} aria-hidden="true" />
    ),
  },
  {
    value: "heading-3",
    label: "Heading 3",
    keys: ["mod", "alt", "3"],
    icon: (
      <HugeiconsIcon icon={Heading03Icon} strokeWidth={2} aria-hidden="true" />
    ),
  },
]

const isBlockType = (value: unknown): value is RichTextBlockType =>
  BLOCK_TYPES.some((option) => option.value === value)

function setBlockType(editor: Editor | null, value: RichTextBlockType) {
  const chain = editor?.chain().focus()

  if (value === "heading-1") chain?.setHeading({ level: 1 }).run()
  else if (value === "heading-2") chain?.setHeading({ level: 2 }).run()
  else if (value === "heading-3") chain?.setHeading({ level: 3 }).run()
  else chain?.setParagraph().run()
}

function MenuShortcut({ keys }: { keys: readonly string[] }) {
  return <DropdownMenuShortcut>{useShortcutLabel(keys)}</DropdownMenuShortcut>
}

export function RichTextBlockTypeMenu({ editor, state }: ControlProps) {
  const current =
    BLOCK_TYPES.find((option) => option.value === state.blockType) ??
    BLOCK_TYPES[0]

  // Fixed width on desktop, so the bar never shifts as the label changes;
  // the current block's glyph alone below sm, so the marks stay in view.
  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button
            variant="ghost"
            size="sm"
            aria-label={`Block type, ${current.label}`}
            disabled={!state.editable || state.codeBlock}
            className="shrink-0 justify-between sm:w-28"
            data-toolbar-item=""
          />
        }
      >
        <span className="flex sm:hidden">{current.icon}</span>
        <span className="truncate max-sm:hidden">{current.label}</span>
        <HugeiconsIcon icon={ArrowDown01Icon} strokeWidth={2} aria-hidden="true" className="opacity-60" />
      </DropdownMenuTrigger>
      <DropdownMenuContent
        align="start"
        className="w-auto"
        finalFocus={focusEditor(editor)}
      >
        <DropdownMenuGroup>
          <DropdownMenuLabel>Turn Into</DropdownMenuLabel>
          <DropdownMenuRadioGroup
            value={state.blockType}
            onValueChange={(value) => {
              if (isBlockType(value)) setBlockType(editor, value)
            }}
          >
            {BLOCK_TYPES.map((option) => (
              <DropdownMenuRadioItem
                key={option.value}
                value={option.value}
                disabled={option.value !== "paragraph" && !state.canHeading}
                closeOnClick
              >
                {option.icon}
                {option.label}
                <MenuShortcut keys={option.keys} />
              </DropdownMenuRadioItem>
            ))}
          </DropdownMenuRadioGroup>
        </DropdownMenuGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

export function RichTextListToggles({ editor, state }: ControlProps) {
  return (
    <RichTextToolbarGroup label="Lists">
      <RichTextToggle
        label="Bullet list"
        shortcut={["mod", "shift", "8"]}
        pressed={state.bulletList}
        disabled={!state.editable}
        onToggle={() => editor?.chain().focus().toggleBulletList().run()}
      >
        <HugeiconsIcon icon={LeftToRightListBulletIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextToggle>
      <RichTextToggle
        label="Numbered list"
        shortcut={["mod", "shift", "7"]}
        pressed={state.orderedList}
        disabled={!state.editable}
        onToggle={() => editor?.chain().focus().toggleOrderedList().run()}
      >
        <HugeiconsIcon icon={LeftToRightListNumberIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextToggle>
      <RichTextToggle
        label="Task list"
        shortcut={["mod", "shift", "9"]}
        pressed={state.taskList}
        disabled={!state.editable}
        onToggle={() => editor?.chain().focus().toggleTaskList().run()}
      >
        <HugeiconsIcon icon={CheckListIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextToggle>
    </RichTextToolbarGroup>
  )
}

export function RichTextBlockToggles({ editor, state }: ControlProps) {
  return (
    <RichTextToolbarGroup label="Blocks">
      <RichTextToggle
        label="Quote"
        shortcut={["mod", "shift", "B"]}
        pressed={state.blockquote}
        disabled={!state.editable}
        onToggle={() => editor?.chain().focus().toggleBlockquote().run()}
      >
        <HugeiconsIcon icon={LeftToRightBlockQuoteIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextToggle>
      <RichTextToggle
        label="Code block"
        shortcut={["mod", "alt", "C"]}
        pressed={state.codeBlock}
        disabled={!state.editable}
        onToggle={() => editor?.chain().focus().toggleCodeBlock().run()}
      >
        <HugeiconsIcon icon={SourceCodeSquareIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextToggle>
      <RichTextButton
        label="Divider"
        disabled={!state.editable}
        onClick={() => editor?.chain().focus().setHorizontalRule().run()}
      >
        <HugeiconsIcon icon={MinusSignIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextButton>
    </RichTextToolbarGroup>
  )
}

export function RichTextMarkToggles({ editor, state }: ControlProps) {
  const disabled = !state.canFormat

  return (
    <RichTextToolbarGroup label="Text style">
      <RichTextToggle
        label="Bold"
        shortcut={["mod", "B"]}
        pressed={state.bold}
        disabled={disabled}
        onToggle={() => editor?.chain().focus().toggleBold().run()}
      >
        <HugeiconsIcon icon={TextBoldIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextToggle>
      <RichTextToggle
        label="Italic"
        shortcut={["mod", "I"]}
        pressed={state.italic}
        disabled={disabled}
        onToggle={() => editor?.chain().focus().toggleItalic().run()}
      >
        <HugeiconsIcon icon={TextItalicIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextToggle>
      <RichTextToggle
        label="Underline"
        shortcut={["mod", "U"]}
        pressed={state.underline}
        disabled={disabled}
        onToggle={() => editor?.chain().focus().toggleUnderline().run()}
      >
        <HugeiconsIcon icon={TextUnderlineIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextToggle>
      <RichTextToggle
        label="Strikethrough"
        shortcut={["mod", "shift", "S"]}
        pressed={state.strike}
        disabled={disabled}
        onToggle={() => editor?.chain().focus().toggleStrike().run()}
      >
        <HugeiconsIcon icon={TextStrikethroughIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextToggle>
      <RichTextToggle
        label="Inline code"
        shortcut={["mod", "E"]}
        pressed={state.code}
        disabled={!state.canCode}
        onToggle={() => editor?.chain().focus().toggleCode().run()}
      >
        <HugeiconsIcon icon={SourceCodeIcon} strokeWidth={2} aria-hidden="true" />
      </RichTextToggle>
    </RichTextToolbarGroup>
  )
}

const ALIGN_LABELS: Record<RichTextAlign, string> = {
  left: "Align left",
  center: "Align center",
  right: "Align right",
  justify: "Justify",
}

const ALIGN_KEYS: Record<RichTextAlign, string> = {
  left: "L",
  center: "E",
  right: "R",
  justify: "J",
}

// Whole static nodes per key, so the trigger can mirror the current value.
const ALIGN_ICONS: Record<RichTextAlign, ReactNode> = {
  left: (
    <HugeiconsIcon icon={TextAlignLeftIcon} strokeWidth={2} aria-hidden="true" />
  ),
  center: (
    <HugeiconsIcon icon={TextAlignCenterIcon} strokeWidth={2} aria-hidden="true" />
  ),
  right: (
    <HugeiconsIcon icon={TextAlignRightIcon} strokeWidth={2} aria-hidden="true" />
  ),
  justify: (
    <HugeiconsIcon icon={TextAlignJustifyLeftIcon} strokeWidth={2} aria-hidden="true" />
  ),
}

const isAlign = (value: unknown): value is RichTextAlign =>
  typeof value === "string" && value in ALIGN_LABELS

export function RichTextAlignMenu({ editor, state }: ControlProps) {
  // Two glyphs and no label read as an icon button, so the text inset is trimmed.
  return (
    <DropdownMenu>
      <Tooltip>
        {/* The span carries the tooltip, so the trigger keeps its own props. */}
        <TooltipTrigger render={<span className="flex shrink-0" />}>
          <DropdownMenuTrigger
            render={
              <Button
                variant="ghost"
                size="sm"
                aria-label={`Alignment, ${ALIGN_LABELS[state.align]}`}
                disabled={!state.editable}
                className="gap-0.5 px-1.5"
                data-toolbar-item=""
              />
            }
          >
            {ALIGN_ICONS[state.align]}
            <HugeiconsIcon icon={ArrowDown01Icon} strokeWidth={2} aria-hidden="true" className="opacity-60" />
          </DropdownMenuTrigger>
        </TooltipTrigger>
        <TooltipContent>Alignment</TooltipContent>
      </Tooltip>
      <DropdownMenuContent
        align="start"
        className="w-auto"
        finalFocus={focusEditor(editor)}
      >
        <DropdownMenuGroup>
          <DropdownMenuLabel>Alignment</DropdownMenuLabel>
          <DropdownMenuRadioGroup
            value={state.align}
            onValueChange={(value) => {
              if (isAlign(value)) {
                editor?.chain().focus().setTextAlign(value).run()
              }
            }}
          >
            {RICH_TEXT_ALIGNS.map((align) => (
              <DropdownMenuRadioItem key={align} value={align} closeOnClick>
                {ALIGN_ICONS[align]}
                {ALIGN_LABELS[align]}
                <MenuShortcut keys={["mod", "shift", ALIGN_KEYS[align]]} />
              </DropdownMenuRadioItem>
            ))}
          </DropdownMenuRadioGroup>
        </DropdownMenuGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}