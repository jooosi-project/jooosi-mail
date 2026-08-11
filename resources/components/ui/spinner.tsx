import { cn } from "@/lib/utils";
import Loading03Icon from "~icons/hugeicons/loading-03";

function Spinner({ className, ...props }: React.ComponentProps<"svg">) {
  return (
    <Loading03Icon
      data-slot="spinner"
      role="status"
      aria-label="Loading"
      className={cn("size-4 animate-spin", className)}
      {...props}
    />
  );
}

export { Spinner };
