import { TaskList } from "@tiptap/extension-list"
import { TextAlign } from "@tiptap/extension-text-align"
import { CharacterCount, Placeholder, Selection } from "@tiptap/extensions"
import {
  NodeSelection,
  Plugin,
  PluginKey,
  type EditorState,
} from "@tiptap/pm/state"
import { Decoration, DecorationSet } from "@tiptap/pm/view"
import { StarterKit } from "@tiptap/starter-kit"

import { RichTextHighlight } from "./rich-text-highlight"
import { RichTextTaskItem } from "./rich-text-task-item"

interface RichTextExtensionOptions {
  /** Hint on the empty line under the caret. */
  placeholder: string
}

// Keeps the selection painted while focus sits in a popover field. A click back
// into the text puts the caret where it lands instead of restoring the range.
const RichTextSelection = Selection.extend({
  addProseMirrorPlugins() {
    const { editor, options } = this
    // The painted range, held from a press into the blurred editor until the
    // press's own selection reaches the state.
    let pressed: EditorState["selection"] | null = null
    let fallback: ReturnType<typeof setTimeout> | undefined
    const release = () => {
      clearTimeout(fallback)
      pressed = null
    }
    // A swallowed press lets go now; a click that never moves the selection
    // lets go shortly after.
    const settle = () => {
      if (!editor.isFocused) release()
      else fallback = setTimeout(release, 150)
    }
    const isRange = ({ selection }: EditorState) =>
      !selection.empty &&
      !(selection instanceof NodeSelection) &&
      editor.isEditable

    return [
      new Plugin({
        key: new PluginKey("selection"),
        view: () => ({
          update(view) {
            if (pressed && !pressed.eq(view.state.selection)) release()
          },
        }),
        props: {
          decorations(state) {
            if (!isRange(state) || editor.view.dragging) return null
            // Held until the click's caret lands: dropping it redraws, and the
            // redraw writes the old range back over the click.
            if (editor.isFocused && !pressed?.eq(state.selection)) return null
            return DecorationSet.create(state.doc, [
              Decoration.inline(state.selection.from, state.selection.to, {
                class: options.className,
              }),
            ])
          },
          handleDOMEvents: {
            mousedown(view, event) {
              // A double click's second press keeps the first press's hold.
              if (view.hasFocus()) return false
              release()
              if (!isRange(view.state)) return false
              if (event.button !== 0 || event.shiftKey) return false
              pressed = view.state.selection
              window.addEventListener("mouseup", settle, {
                capture: true,
                once: true,
              })
              return false
            },
            blur(view) {
              release()
              if (isRange(view.state)) window.getSelection()?.removeAllRanges()
              return false
            },
            focus(view) {
              if (pressed || !isRange(view.state)) return false
              requestAnimationFrame(() => {
                if (!editor.isDestroyed && view.hasFocus()) view.focus()
              })
              return false
            },
          },
        },
      }),
    ]
  },
})

/** The editor preset; call it at module scope (useEditor compares by identity). */
export function createRichTextExtensions({
  placeholder,
}: RichTextExtensionOptions) {
  return [
    // v3 StarterKit already carries Link, Underline, list keys and undo history.
    StarterKit.configure({
      heading: { levels: [1, 2, 3] },
      link: { openOnClick: false, defaultProtocol: "https" },
    }),
    TaskList,
    RichTextTaskItem.configure({ nested: true }),
    TextAlign.configure({ types: ["heading", "paragraph"] }),
    RichTextHighlight.configure({ multicolor: true }),
    Placeholder.configure({
      placeholder: ({ node }) =>
        node.type.name === "heading"
          ? `Heading ${node.attrs.level}`
          : placeholder,
    }),
    CharacterCount,
    RichTextSelection,
  ]
}