import { describe, expect, it } from "vitest";
import { toQuery } from "@/lib/query";

describe("toQuery", () => {
  it("omits empty strings and builds search string", () => {
    expect(toQuery({ a: 1, b: "", c: undefined })).toBe("?a=1");
  });

  it("returns empty when all blank", () => {
    expect(toQuery({ x: "" })).toBe("");
  });
});
