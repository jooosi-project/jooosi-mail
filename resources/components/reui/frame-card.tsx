import * as React from "react";

import {
  Frame,
  FrameDescription,
  FrameFooter,
  FrameHeader,
  FramePanel,
  FrameTitle,
} from "@/components/reui/frame";
import { cn } from "@/lib/utils";

type FrameCardProps = React.ComponentProps<"div"> & {
  size?: "default" | "sm";
};

function Card({ className, size = "default", ...props }: FrameCardProps) {
  return (
    <Frame
      className={cn("group/card", className)}
      data-size={size}
      spacing={size === "sm" ? "sm" : "default"}
      stacked
      {...props}
    />
  );
}

function CardHeader({ className, ...props }: React.ComponentProps<"header">) {
  return (
    <FrameHeader
      className={cn(
        "group/card-header @container/card-header grid auto-rows-min items-start gap-1 has-data-[slot=card-action]:grid-cols-[1fr_auto] has-data-[slot=frame-panel-description]:grid-rows-[auto_auto]",
        className,
      )}
      {...props}
    />
  );
}

function CardTitle(props: React.ComponentProps<"div">) {
  return <FrameTitle {...props} />;
}

function CardDescription(props: React.ComponentProps<"div">) {
  return <FrameDescription {...props} />;
}

function CardAction({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-action"
      className={cn("col-start-2 row-span-2 row-start-1 self-start justify-self-end", className)}
      {...props}
    />
  );
}

function CardContent(props: React.ComponentProps<typeof FramePanel>) {
  return <FramePanel {...props} />;
}

function CardFooter(props: React.ComponentProps<typeof FrameFooter>) {
  return <FrameFooter {...props} />;
}

export { Card, CardAction, CardContent, CardDescription, CardFooter, CardHeader, CardTitle };
