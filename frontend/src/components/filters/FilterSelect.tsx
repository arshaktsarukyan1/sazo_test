import type { SelectHTMLAttributes } from "react";

export type FilterSelectOption = { value: string; label: string };

export type FilterSelectProps = {
  id: string;
  label: string;
  options: FilterSelectOption[];
} & Omit<SelectHTMLAttributes<HTMLSelectElement>, "id">;

export function FilterSelect({ id, label, options, className = "", ...rest }: FilterSelectProps) {
  return (
    <div className="flex min-w-[10rem] flex-col gap-1">
      <label htmlFor={id} className="text-xs font-medium text-slate-800">
        {label}
      </label>
      <select
        id={id}
        className={
          "h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none ring-slate-900 ring-offset-2 focus-visible:ring-2 " +
          className
        }
        {...rest}
      >
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </div>
  );
}
