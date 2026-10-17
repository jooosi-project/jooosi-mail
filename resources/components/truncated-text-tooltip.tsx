"use client";

import * as React from "react";

import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

type TruncatedTextTooltipProps = {
  value: string;
  className?: string;
  tooltipContent?: React.ReactNode;
};

export function TruncatedTextTooltip({
  value,
  className,
  tooltipContent,
}: TruncatedTextTooltipProps) {
  const textRef = React.useRef<HTMLSpanElement>(null);
  const [isTruncated, setIsTruncated] = React.useState(false);

  React.useLayoutEffect(() => {
    const text = textRef.current;

    if (!text) {
      return;
    }

    const updateTruncation = () => {
      const nextIsTruncated = text.scrollWidth > text.clientWidth;

      setIsTruncated((currentIsTruncated) =>
        currentIsTruncated === nextIsTruncated ? currentIsTruncated : nextIsTruncated,
      );
    };

    updateTruncation();

    if (typeof ResizeObserver === "undefined") {
      return;
    }

    const observer = new ResizeObserver(updateTruncation);

    observer.observe(text);

    return () => observer.disconnect();
  }, [value]);

  return (
    <Tooltip disabled={!isTruncated}>
      <TooltipTrigger
        render={
          <span
            ref={textRef}
            className={cn("block min-w-0 truncate", className)}
            data-truncated={isTruncated || undefined}
          />
        }
      >
        {value}
      </TooltipTrigger>
      <TooltipContent className="max-w-sm">
        {tooltipContent ?? <span className="break-all">{value}</span>}
      </TooltipContent>
    </Tooltip>
  );
}
