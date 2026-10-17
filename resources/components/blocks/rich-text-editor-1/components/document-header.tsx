import { Badge } from "@/components/reui/badge"
import {
  FrameDescription,
  FrameHeader,
  FrameTitle,
} from "@/components/reui/frame"
import type { Editor } from "@tiptap/react"

import {
  Avatar,
  AvatarFallback,
  AvatarGroup,
  AvatarImage,
} from "@/components/ui/avatar"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"

import { COLLABORATORS, DOCUMENT_META } from "./data"
import { DocumentActions } from "./document-actions"

export type SaveState = "saved" | "saving"

// Local midnight, so the stored day never shifts with the viewer's time zone.
const EDITED_ON = new Intl.DateTimeFormat("en-US", {
  month: "short",
  day: "numeric",
}).format(new Date(`${DOCUMENT_META.editedAt}T00:00:00`))

const SHARED_WITH = `Shared with ${new Intl.ListFormat("en-US").format(
  COLLABORATORS.map((person) => person.name)
)}`

/** The house dot separator, shared with the footer. */
export function Dot() {
  return (
    <span
      aria-hidden="true"
      className="bg-muted-foreground/40 size-1 shrink-0 rounded-full"
    />
  )
}

interface DocumentHeaderProps {
  editor: Editor | null
  editable: boolean
  /** The text changed in this session, so the stored stamp is stale. */
  edited: boolean
  saveState: SaveState
  onEditableChange: (editable: boolean) => void
}

export function DocumentHeader({
  editor,
  editable,
  edited,
  saveState,
  onEditableChange,
}: DocumentHeaderProps) {
  return (
    <FrameHeader className="flex-row items-center gap-3">
      <div className="flex min-w-0 flex-1 flex-col gap-px">
        <div className="flex min-w-0 items-center gap-2">
          <FrameTitle className="truncate">{DOCUMENT_META.title}</FrameTitle>
          {editable ? null : (
            <Badge variant="outline" className="shrink-0 font-normal">
              View only
            </Badge>
          )}
        </div>
        <FrameDescription className="text-xs">
          {/* Each dot travels inside its segment, so a wrap never strands one. */}
          <span className="flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
            <span>{DOCUMENT_META.kind}</span>
            <span className="inline-flex items-center gap-1.5 max-sm:hidden">
              <Dot />
              Edited {edited ? "just now" : EDITED_ON}
            </span>
            <span className="inline-flex items-center gap-1.5">
              <Dot />
              <span aria-live="polite">
                {saveState === "saving" ? "Saving…" : "Saved"}
              </span>
            </span>
          </span>
        </FrameDescription>
      </div>

      {/* One reveal for the stack, so a pointer can learn who the faces are. */}
      <Tooltip>
        <TooltipTrigger
          render={
            <AvatarGroup
              role="group"
              aria-label={SHARED_WITH}
              className="max-sm:hidden"
            />
          }
        >
          {COLLABORATORS.map((person) => (
            <Avatar key={person.id} size="sm">
              <AvatarImage src={person.avatar} alt={person.name} />
              <AvatarFallback>{person.initials}</AvatarFallback>
            </Avatar>
          ))}
        </TooltipTrigger>
        <TooltipContent>{SHARED_WITH}</TooltipContent>
      </Tooltip>

      <DocumentActions
        editor={editor}
        editable={editable}
        onEditableChange={onEditableChange}
      />
    </FrameHeader>
  )
}