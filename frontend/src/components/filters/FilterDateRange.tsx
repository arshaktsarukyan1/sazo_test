import type { InputHTMLAttributes } from "react";

type DateInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, "type" | "id">;

export type FilterDateRangeProps = {
  startId: string;
  endId: string;
  startLabel?: string;
  endLabel?: string;
  startInputProps?: DateInputProps;
  endInputProps?: DateInputProps;
};

export function FilterDateRange({
  startId,
  endId,
  startLabel = "From",
  endLabel = "To",
  startInputProps,
  endInputProps,
}: FilterDateRangeProps) {
  const inputClass =
    "h-10 w-full min-w-[10rem] rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none ring-slate-900 ring-offset-2 focus-visible:ring-2";

  return (
    <fieldset className="flex flex-wrap items-end gap-4 border-0 p-0">
      <legend className="sr-only">Date range</legend>
      <div className="flex min-w-[10rem] flex-col gap-1">
        <label htmlFor={startId} className="text-xs font-medium text-slate-800">
          {startLabel}
        </label>
        <input id={startId} type="date" className={inputClass} {...startInputProps} />
      </div>
      <div className="flex min-w-[10rem] flex-col gap-1">
        <label htmlFor={endId} className="text-xs font-medium text-slate-800">
          {endLabel}
        </label>
        <input id={endId} type="date" className={inputClass} {...endInputProps} />
      </div>
    </fieldset>
  );
}
