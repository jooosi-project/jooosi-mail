import { TaskItem } from "@tiptap/extension-list"
import {
  NodeViewContent,
  NodeViewWrapper,
  ReactNodeViewRenderer,
  useEditorState,
  type ReactNodeViewProps,
} from "@tiptap/react"

import { Checkbox } from "@/components/ui/checkbox"

function TaskItemView({ node, editor, updateAttributes }: ReactNodeViewProps) {
  const editable = useEditorState({
    editor,
    selector: ({ editor: current }) => current.isEditable,
  })
  const checked = node.attrs.checked === true
  const task = node.textContent || "Empty task"

  return (
    <NodeViewWrapper className="flex items-start gap-2.5">
      {/* Out of the text flow; a press keeps the caret where it was. */}
      <span
        data-task-toggle=""
        contentEditable={false}
        className="flex h-7 shrink-0 items-center"
        onMouseDown={(event) => event.preventDefault()}
      >
        <Checkbox
          checked={checked}
          disabled={!editable}
          aria-label={task}
          onCheckedChange={(next) =>
            updateAttributes({ checked: next === true })
          }
        />
      </span>
      <NodeViewContent className="min-w-0 flex-1" />
    </NodeViewWrapper>
  )
}

/** Task items that tick with the shadcn Checkbox instead of a native input. */
export const RichTextTaskItem = TaskItem.extend({
  addNodeView() {
    return ReactNodeViewRenderer(TaskItemView, {
      as: "li",
      attrs: ({ node }) => ({
        "data-checked": String(node.attrs.checked === true),
      }),
      // The editor leaves pointer and key events on the checkbox alone.
      stopEvent: ({ event }) =>
        event.target instanceof Element &&
        event.target.closest("[data-task-toggle]") !== null,
    })
  },
})