import { HugeiconsIcon } from "@hugeicons/react";
import { CheckmarkCircle02Icon, AlertCircleIcon } from "@hugeicons/core-free-icons";

// The Toaster's glyph carries no state colour, so typed toasts pass these.
export const TOAST_SUCCESS_ICON = (
  <HugeiconsIcon icon={CheckmarkCircle02Icon} strokeWidth={2} aria-hidden="true" className="text-success size-4" />
)

export const TOAST_ERROR_ICON = (
  <HugeiconsIcon icon={AlertCircleIcon} strokeWidth={2} aria-hidden="true" className="text-destructive size-4" />
)