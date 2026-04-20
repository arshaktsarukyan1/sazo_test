import type { ReactNode } from "react";

export type KpiCardProps = {
  label: string;
  value: ReactNode;
  /** Short supporting text, e.g. period or unit */
  description?: string;
  /** e.g. "+12% vs last week" — string kept for simple reuse */
  delta?: string;
  /** Positive / negative / neutral styling for delta */
  deltaTone?: "positive" | "negative" | "neutral";
  icon?: ReactNode;
};

const deltaToneClass: Record<NonNullable<KpiCardProps["deltaTone"]>, string> = {
  positive: "text-emerald-800",
  negative: "text-red-800",
  neutral: "text-slate-700",
};

export function KpiCard({
  label,
  value,
  description,
  delta,
  deltaTone = "neutral",
  icon,
}: KpiCardProps) {
  return (
    <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="text-sm font-medium text-slate-700">
            {label}
          </h2>
          <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
            {value}
          </p>
          {description ? (
            <p className="mt-1 text-xs text-slate-600">{description}</p>
          ) : null}
          {delta ? (
            <p className={`mt-2 text-xs font-medium ${deltaToneClass[deltaTone]}`}>
              {delta}
            </p>
          ) : null}
        </div>
        {icon ? (
          <div
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-700"
            aria-hidden
          >
            {icon}
          </div>
        ) : null}
      </div>
    </section>
  );
}
