"use client";

import { useMemo } from "react";

export type DashboardFilters = {
  from: string;
  to: string;
  country_code: string;
  device_type: "" | "desktop" | "mobile" | "tablet";
  traffic_source_id: "" | number;
};

type TrafficSourceOpt = { id: number; name: string };

type FiltersBarProps = {
  filters: DashboardFilters;
  onChange: (next: DashboardFilters) => void;
  trafficSources: TrafficSourceOpt[];
  showTrafficSource?: boolean;
};

function todayYmd(): string {
  const d = new Date();
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
}

function daysAgoYmd(days: number): string {
  const d = new Date();
  d.setDate(d.getDate() - days);
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd}`;
}

export function defaultFilters(): DashboardFilters {
  return {
    from: daysAgoYmd(6),
    to: todayYmd(),
    country_code: "",
    device_type: "",
    traffic_source_id: "",
  };
}

export function FiltersBar({
  filters,
  onChange,
  trafficSources,
  showTrafficSource = true,
}: FiltersBarProps) {
  const countryHint = useMemo(() => {
    const v = filters.country_code.trim().toUpperCase();
    if (v.length === 0) return "";
    if (v.length !== 2) return "Use ISO-2 (e.g. US)";
    return "";
  }, [filters.country_code]);

  return (
    <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex flex-wrap items-end gap-3">
        <label className="block text-sm">
          <span className="font-medium text-slate-700">From</span>
          <input
            type="date"
            className="mt-1 w-40 rounded-md border border-slate-200 px-2 py-2"
            value={filters.from}
            onChange={(e) => onChange({ ...filters, from: e.target.value })}
          />
        </label>
        <label className="block text-sm">
          <span className="font-medium text-slate-700">To</span>
          <input
            type="date"
            className="mt-1 w-40 rounded-md border border-slate-200 px-2 py-2"
            value={filters.to}
            onChange={(e) => onChange({ ...filters, to: e.target.value })}
          />
        </label>
        <label className="block text-sm">
          <span className="font-medium text-slate-700">Country</span>
          <input
            className="mt-1 w-32 rounded-md border border-slate-200 px-2 py-2 uppercase"
            value={filters.country_code}
            maxLength={2}
            placeholder="US"
            onChange={(e) =>
              onChange({ ...filters, country_code: e.target.value.toUpperCase() })
            }
          />
          {countryHint ? (
            <span className="mt-1 block text-xs text-amber-700">{countryHint}</span>
          ) : null}
        </label>
        <label className="block text-sm">
          <span className="font-medium text-slate-700">Device</span>
          <select
            className="mt-1 w-40 rounded-md border border-slate-200 px-2 py-2"
            value={filters.device_type}
            onChange={(e) =>
              onChange({
                ...filters,
                device_type: e.target.value as DashboardFilters["device_type"],
              })
            }
          >
            <option value="">Any</option>
            <option value="desktop">Desktop</option>
            <option value="mobile">Mobile</option>
            <option value="tablet">Tablet</option>
          </select>
        </label>

        {showTrafficSource ? (
          <label className="block text-sm">
            <span className="font-medium text-slate-700">Traffic source</span>
            <select
              className="mt-1 w-56 rounded-md border border-slate-200 px-2 py-2"
              value={filters.traffic_source_id}
              onChange={(e) =>
                onChange({
                  ...filters,
                  traffic_source_id: e.target.value ? Number(e.target.value) : "",
                })
              }
            >
              <option value="">Any</option>
              {trafficSources.map((ts) => (
                <option key={ts.id} value={ts.id}>
                  {ts.name}
                </option>
              ))}
            </select>
          </label>
        ) : null}
      </div>
    </section>
  );
}

