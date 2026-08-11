import { FrameDescription, FramePanel } from "@/components/reui/frame";

type MetricCardProps = {
  label: string;
  value: string;
  description: string;
};

export function MetricCard({ label, value, description }: MetricCardProps) {
  return (
    <FramePanel className="flex min-h-36 flex-col justify-between gap-5">
      <div className="flex flex-col gap-1">
        <FrameDescription>{label}</FrameDescription>
        <p className="text-3xl font-semibold tracking-tight tabular-nums">{value}</p>
      </div>
      <p className="text-sm text-muted-foreground">{description}</p>
    </FramePanel>
  );
}
