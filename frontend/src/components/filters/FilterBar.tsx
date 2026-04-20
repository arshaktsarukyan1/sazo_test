import type { ReactNode } from "react";

export type FilterBarProps = {
  title?: string;
  children: ReactNode;
  /** Right-aligned actions (e.g. Apply / Reset) */
  actions?: ReactNode;
};

export function FilterBar({ title = "Filters", children, actions }: FilterBarProps) {
  return (
    <section
      className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
      aria-label={title}
    >
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div className="flex flex-1 flex-wrap items-end gap-4">{children}</div>
        {actions ? (
          <div className="flex flex-wrap items-center gap-2 border-t border-slate-200 pt-3 lg:border-0 lg:pt-0">
            {actions}
          </div>
        ) : null}
      </div>
    </section>
  );
}
