export function toQuery(params: Record<string, string | number | null | undefined>): string {
  const usp = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v === null || v === undefined) continue;
    const s = String(v);
    if (s === "") continue;
    usp.set(k, s);
  }
  const q = usp.toString();
  return q ? `?${q}` : "";
}

