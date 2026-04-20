import { describe, expect, it } from "vitest";
import { deltaLabel, fmtMaybe, fmtRate } from "@/lib/kpi-format";

describe("kpi-format", () => {
  it("fmtRate renders dash for null", () => {
    expect(fmtRate(null)).toBe("—");
  });

  it("fmtMaybe formats money", () => {
    expect(fmtMaybe(12.5, "money")).toMatch(/12/);
  });

  it("deltaLabel builds money string with percent tail", () => {
    const s = deltaLabel(10, 5, "money");
    expect(s).toMatch(/\+/);
    expect(s).toContain("5.00%");
  });
});
