import { describe, expect, it } from "vitest";
import { defaultFilters, type DashboardFilters } from "@/components/dashboard/FiltersBar";
import { toQuery } from "@/lib/query";

describe("dashboard filter URL coupling", () => {
  it("defaultFilters yields stable keys for query serialization", () => {
    const f: DashboardFilters = defaultFilters();
    const q = toQuery({
      from: f.from,
      to: f.to,
      country_code: f.country_code || undefined,
      device_type: f.device_type || undefined,
      traffic_source_id: f.traffic_source_id === "" ? undefined : f.traffic_source_id,
    });
    expect(q.startsWith("?")).toBe(true);
    expect(q).toContain("from=");
    expect(q).toContain("to=");
  });

  it("includes optional filters when set", () => {
    const q = toQuery({
      from: "2026-01-01",
      to: "2026-01-07",
      country_code: "US",
      device_type: "mobile",
      traffic_source_id: 3,
    });
    expect(q).toContain("country_code=US");
    expect(q).toContain("device_type=mobile");
    expect(q).toContain("traffic_source_id=3");
  });
});
