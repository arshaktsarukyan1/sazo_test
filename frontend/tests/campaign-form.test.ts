import { describe, expect, it } from "vitest";
import { activeSum, slugify } from "@/lib/campaign-form";

describe("campaign-form", () => {
  it("slugify normalizes names", () => {
    expect(slugify("  My Campaign!! ")).toBe("my-campaign");
  });

  it("activeSum counts only active rows", () => {
    expect(
      activeSum([
        { id: 1, weight_percent: 60, is_active: true },
        { id: 2, weight_percent: 40, is_active: true },
        { id: 3, weight_percent: 50, is_active: false },
      ]),
    ).toBe(100);
  });
});
