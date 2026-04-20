import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { KpiCards, type KpiDelta, type KpiTotals } from "@/components/dashboard/KpiCards";

const baseTotals: KpiTotals = {
  visits: 100,
  clicks: 10,
  conversions: 2,
  revenue: 50,
  cost: 20,
  profit: 30,
  ctr: 10,
  cr: 20,
  roi: 150,
  cpa: 10,
  epc: 5,
};

const zeroDelta = (Object.keys(baseTotals) as (keyof KpiTotals)[]).reduce((acc, k) => {
  acc[k] = { abs: 0, pct: 0 };
  return acc;
}, {} as KpiDelta);

describe("KpiCards", () => {
  it("renders primary KPI labels", () => {
    render(<KpiCards current={baseTotals} delta={zeroDelta} />);
    expect(screen.getByText("Visits")).toBeTruthy();
    expect(screen.getByText("Revenue")).toBeTruthy();
    expect(screen.getByText("ROI")).toBeTruthy();
  });
});
