export type DomainRow = { id: number; name: string };

export function publicOrigin(): string {
  const raw =
    process.env.NEXT_PUBLIC_TDS_PUBLIC_ORIGIN?.replace(/\/$/, "") ??
    (typeof window !== "undefined" ? window.location.origin : "http://localhost");
  return raw;
}

export function redirectUrl(slug: string, domain: DomainRow | null): string {
  const base = domain?.name
    ? `https://${domain.name.replace(/\/$/, "")}`
    : publicOrigin();
  return `${base}/campaign/${encodeURIComponent(slug)}`;
}

export function clickUrl(slug: string, domain: DomainRow | null): string {
  const base = domain?.name
    ? `https://${domain.name.replace(/\/$/, "")}`
    : publicOrigin();
  return `${base}/click?campaign=${encodeURIComponent(slug)}`;
}

export function trackerScriptTag(campaignId: number, domain: DomainRow | null): string {
  const base = domain?.name
    ? `https://${domain.name.replace(/\/$/, "")}`
    : publicOrigin();
  const src = `${base}/tracker/${campaignId}.js`;
  return `<script src="${src}" async></script>`;
}
