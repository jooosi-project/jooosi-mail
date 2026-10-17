import { useId, useRef, useState, type FormEvent, type RefObject } from "react"
import { useEditorState, type Editor } from "@tiptap/react"
import { BubbleMenu } from "@tiptap/react/menus"

import { Button } from "@/components/ui/button"
import { ButtonGroup } from "@/components/ui/button-group"
import { Field, FieldError } from "@/components/ui/field"
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/components/ui/input-group"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { Toggle } from "@/components/ui/toggle"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import type { RichTextSnapshot } from "./rich-text-state"
import { keepEditorFocus, ShortcutKeys } from "./rich-text-toolbar"
import { HugeiconsIcon } from "@hugeicons/react"
import { Link01Icon, Tick02Icon, Unlink03Icon, LinkSquare01Icon, PenIcon } from "@hugeicons/core-free-icons"

const BARE_DOMAIN = /^[\w-]+(\.[\w-]+)+(:\d+)?([/?#]\S*)?$/

/** A typed address as an href, or null when it is not a web, mail or phone link. */
export function normalizeHref(value: string) {
  const href = value.trim()

  if (/^(https?:\/\/|mailto:|tel:)\S+$/i.test(href)) return href
  if (BARE_DOMAIN.test(href)) return `https://${href}`
  return null
}

function removeLink(editor: Editor | null) {
  editor?.chain().focus().extendMarkRange("link").unsetLink().run()
}

interface LinkFormProps {
  editor: Editor | null
  href: string | null
  inputRef: RefObject<HTMLInputElement | null>
  onDone: () => void
}

function LinkForm({ editor, href, inputRef, onDone }: LinkFormProps) {
  const [draft, setDraft] = useState(href ?? "")
  const [invalid, setInvalid] = useState(false)
  const errorId = useId()

  function apply(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    // Enter on an empty field is a no-op, not an error.
    if (!draft.trim()) return

    const next = normalizeHref(draft)

    if (!editor || !next) {
      setInvalid(true)
      return
    }

    const { empty } = editor.state.selection

    // With nothing selected, the address becomes the link text itself.
    if (empty && !editor.isActive("link")) {
      editor
        .chain()
        .focus()
        .insertContent({
          type: "text",
          text: draft.trim(),
          marks: [{ type: "link", attrs: { href: next } }],
        })
        .run()
    } else {
      editor
        .chain()
        .focus()
        .extendMarkRange("link")
        .setLink({ href: next })
        .run()
    }

    onDone()
  }

  return (
    <form onSubmit={apply}>
      <Field data-invalid={invalid || undefined}>
        <InputGroup>
          <InputGroupAddon>
            <HugeiconsIcon icon={Link01Icon} strokeWidth={2} aria-hidden="true" />
          </InputGroupAddon>
          <InputGroupInput
            ref={inputRef}
            value={draft}
            placeholder="Paste or type a link"
            aria-label="Link address"
            aria-invalid={invalid || undefined}
            aria-describedby={invalid ? errorId : undefined}
            onChange={(event) => {
              setDraft(event.target.value)
              setInvalid(false)
            }}
          />
          <InputGroupAddon align="inline-end">
            {/* Announced disabled, never natively disabled: a native one
              dims the whole field with it. */}
            <InputGroupButton
              type="submit"
              size="icon-xs"
              aria-label="Apply link"
              disabled={!draft.trim()}
              focusableWhenDisabled
              className="aria-disabled:pointer-events-none aria-disabled:opacity-50"
            >
              <HugeiconsIcon icon={Tick02Icon} strokeWidth={2} aria-hidden="true" />
            </InputGroupButton>
            {href ? (
              <InputGroupButton
                size="icon-xs"
                aria-label="Remove link"
                onClick={() => {
                  removeLink(editor)
                  onDone()
                }}
              >
                <HugeiconsIcon icon={Unlink03Icon} strokeWidth={2} aria-hidden="true" />
              </InputGroupButton>
            ) : null}
          </InputGroupAddon>
        </InputGroup>
        {invalid ? (
          <FieldError id={errorId}>
            Enter a web address, like example.com
          </FieldError>
        ) : null}
      </Field>
    </form>
  )
}

interface RichTextLinkPopoverProps {
  editor: Editor | null
  state: RichTextSnapshot
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function RichTextLinkPopover({
  editor,
  state,
  open,
  onOpenChange,
}: RichTextLinkPopoverProps) {
  const inputRef = useRef<HTMLInputElement>(null)

  return (
    <Popover open={open} onOpenChange={onOpenChange}>
      <Tooltip>
        {/* The span carries the tooltip, so the trigger keeps its own props. */}
        <TooltipTrigger render={<span className="flex" />}>
          <PopoverTrigger
            render={
              <Toggle
                size="sm"
                aria-label="Link"
                pressed={state.link !== null}
                disabled={!state.canFormat}
                onMouseDown={keepEditorFocus}
                className="px-0"
                data-toolbar-item=""
              />
            }
          >
            <HugeiconsIcon icon={Link01Icon} strokeWidth={2} aria-hidden="true" />
          </PopoverTrigger>
        </TooltipTrigger>
        <TooltipContent>
          Link
          <ShortcutKeys keys={["mod", "K"]} />
        </TooltipContent>
      </Tooltip>
      <PopoverContent
        align="start"
        aria-label="Link"
        className="w-80"
        initialFocus={inputRef}
        finalFocus={() => editor?.view.dom ?? true}
      >
        {/* Mounts per open, so the field always starts from the current link. */}
        <LinkForm
          editor={editor}
          href={state.link}
          inputRef={inputRef}
          onDone={() => onOpenChange(false)}
        />
      </PopoverContent>
    </Popover>
  )
}

const BUBBLE_OPTIONS = { placement: "bottom-start", offset: 6 } as const

function showAtLink({
  editor,
  element,
}: {
  editor: Editor
  element: HTMLElement
}) {
  return (
    editor.isEditable &&
    editor.isActive("link") &&
    (editor.view.hasFocus() || element.contains(document.activeElement))
  )
}

interface RichTextLinkBubbleProps {
  editor: Editor
  onEdit: () => void
}

/** Surfaces the address under the caret, which the text alone never shows. */
export function RichTextLinkBubble({
  editor,
  onEdit,
}: RichTextLinkBubbleProps) {
  const href = useEditorState({
    editor,
    selector: ({ editor: current }) =>
      current.isActive("link")
        ? ((current.getAttributes("link").href as string | undefined) ?? null)
        : null,
  })
  // Stored JSON skips Link's own check, so only a vetted address goes live.
  const safeHref = href ? normalizeHref(href) : null

  return (
    <BubbleMenu
      editor={editor}
      pluginKey="richTextLinkBubble"
      shouldShow={showAtLink}
      options={BUBBLE_OPTIONS}
      className="z-50"
    >
      {/* One outline group: the address, then its two actions. */}
      <ButtonGroup aria-label="Link">
        {safeHref ? (
          <Button
            nativeButton={false}
            variant="outline"
            size="sm"
            className="text-muted-foreground max-w-64 min-w-0 font-normal"
            render={
              <a href={safeHref} target="_blank" rel="noopener noreferrer" />
            }
          >
            <HugeiconsIcon icon={LinkSquare01Icon} strokeWidth={2} aria-hidden="true" />
            <span className="truncate">
              {safeHref.replace(/^https?:\/\//, "")}
            </span>
            <span className="sr-only">(opens in a new tab)</span>
          </Button>
        ) : null}
        <Tooltip>
          <TooltipTrigger
            render={
              <Button
                variant="outline"
                size="icon-sm"
                aria-label="Edit link"
                onClick={onEdit}
              />
            }
          >
            <HugeiconsIcon icon={PenIcon} strokeWidth={2} aria-hidden="true" />
          </TooltipTrigger>
          <TooltipContent>Edit link</TooltipContent>
        </Tooltip>
        <Tooltip>
          <TooltipTrigger
            render={
              <Button
                variant="outline"
                size="icon-sm"
                aria-label="Remove link"
                onClick={() => removeLink(editor)}
              />
            }
          >
            <HugeiconsIcon icon={Unlink03Icon} strokeWidth={2} aria-hidden="true" />
          </TooltipTrigger>
          <TooltipContent>Remove link</TooltipContent>
        </Tooltip>
      </ButtonGroup>
    </BubbleMenu>
  )
}