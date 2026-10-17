import type { JSONContent } from "@tiptap/react"

export interface Collaborator {
  id: string
  name: string
  initials: string
  avatar: string
}

export const DOCUMENT_META = {
  title: "Loomwell 4.2",
  kind: "Launch brief",
  editedAt: "2026-09-26",
} as const

export const COLLABORATORS: Collaborator[] = [
  {
    id: "usr_maya",
    name: "Maya Chen",
    avatar:
      "https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=96&h=96&dpr=2&q=80",
    initials: "MC",
  },
  {
    id: "usr_daniel",
    name: "Daniel Okafor",
    avatar:
      "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=96&h=96&dpr=2&q=80",
    initials: "DO",
  },
  {
    id: "usr_lena",
    name: "Lena Hoffmann",
    avatar:
      "https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=96&h=96&dpr=2&q=80",
    initials: "LH",
  },
]

type Mark = NonNullable<JSONContent["marks"]>[number]

const bold: Mark = { type: "bold" }
const code: Mark = { type: "code" }
const italic: Mark = { type: "italic" }
const highlight: Mark = { type: "highlight", attrs: { color: "yellow" } }

function link(href: string): Mark {
  return { type: "link", attrs: { href } }
}

function text(value: string, ...marks: Mark[]): JSONContent {
  return marks.length
    ? { type: "text", text: value, marks }
    : { type: "text", text: value }
}

function paragraph(...content: JSONContent[]): JSONContent {
  return { type: "paragraph", content }
}

function heading(level: 1 | 2 | 3, value: string): JSONContent {
  return { type: "heading", attrs: { level }, content: [text(value)] }
}

function item(...content: JSONContent[]): JSONContent {
  return { type: "listItem", content: [paragraph(...content)] }
}

function task(checked: boolean, ...content: JSONContent[]): JSONContent {
  return {
    type: "taskItem",
    attrs: { checked },
    content: [paragraph(...content)],
  }
}

/** A launch brief mid draft: two checks done, two open, one line flagged. */
export const DOCUMENT: JSONContent = {
  type: "doc",
  content: [
    heading(1, "Loomwell 4.2 Launch Brief"),
    paragraph(
      text("Loomwell 4.2 brings "),
      text("scheduled replies", bold),
      text(" and a rebuilt "),
      text("inbox search", bold),
      text(
        " to every workspace on October 14. This brief is the source of truth for positioning, rollout and support readiness. Live numbers sit on the "
      ),
      text("rollout dashboard", link("https://loomwell.example/rollout")),
      text(".")
    ),
    heading(2, "Goals"),
    {
      type: "bulletList",
      content: [
        item(
          text("Cut median first reply time for teams over 50 seats by 20%.")
        ),
        item(
          text("Move inbox search from the Scale plan to every plan, "),
          text("including Free", italic),
          text(".")
        ),
        item(
          text(
            "Keep launch week tickets under 1,200, down from 1,640 at the 4.0 launch."
          )
        ),
        item(
          text("Publish both help center articles before general availability.")
        ),
      ],
    },
    heading(2, "Rollout"),
    {
      type: "orderedList",
      attrs: { start: 1 },
      content: [
        item(text("Dogfood with the support team from October 1.")),
        item(
          text(
            "Open to 10% of workspaces on October 7 while search latency is watched."
          )
        ),
        item(
          text(
            "General availability on October 14, with the changelog and the customer email."
          )
        ),
      ],
    },
    {
      type: "blockquote",
      content: [
        paragraph(
          text(
            "Half my team works overnight. Scheduling replies means nobody sends a 3 a.m. apology again."
          )
        ),
        paragraph(text("Priya Raman, support lead at Fieldnote", italic)),
      ],
    },
    paragraph(
      text("Pricing does not change in 4.2. "),
      text(
        "Leave the Q1 pricing review out of every piece of launch copy.",
        highlight
      )
    ),
    heading(2, "Launch Checklist"),
    {
      type: "taskList",
      content: [
        task(true, text("Final copy for the in-app announcement")),
        task(
          true,
          text("Search latency dashboard shared with the on-call rotation")
        ),
        task(
          false,
          text("Help center article for scheduled replies, owned by "),
          text("Daniel", bold)
        ),
        task(
          false,
          text("Support macros updated for the new search operators")
        ),
      ],
    },
    heading(3, "Search Operators"),
    paragraph(
      text("Search now reads "),
      text("from:", code),
      text(", "),
      text("status:", code),
      text(" and quoted phrases. Paste this into the macro as the example:")
    ),
    {
      type: "codeBlock",
      content: [text('from:maya status:open "refund request"')],
    },
    paragraph(text("Questions go to Maya in #launch-loomwell.")),
  ],
}