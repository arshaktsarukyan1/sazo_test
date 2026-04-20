export function fmtMoney(v: number): string {
  return v.toLocaleString(undefined, { style: "currency", currency: "USD" });
}

export function fmtNum(v: number): string {
  return v.toLocaleString();
}

export function fmtRate(v: number | null): string {
  return v == null ? "—" : `${v.toFixed(2)}%`;
}

export function fmtMaybe(v: number | null, kind: "num" | "money" | "pct"): string {
  if (v == null) return "—";
  if (kind === "money") return fmtMoney(v);
  if (kind === "pct") return `${v.toFixed(2)}%`;
  return fmtNum(v);
}

export function deltaLabel(
  abs: number | null,
  pct: number | null,
  kind: "num" | "money" | "pct",
): string {
  if (abs == null) return "—";
  const sign = abs > 0 ? "+" : "";
  const absText =
    kind === "money"
      ? `${sign}${fmtMoney(abs)}`
      : kind === "pct"
        ? `${sign}${abs.toFixed(2)}%`
        : `${sign}${fmtNum(abs)}`;
  const pctText = pct == null ? "" : ` (${pct > 0 ? "+" : ""}${pct.toFixed(2)}%)`;
  return `${absText}${pctText}`;
}
