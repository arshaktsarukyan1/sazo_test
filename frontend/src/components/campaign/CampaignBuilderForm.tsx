"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";
import { AdminShell } from "@/components/admin";
import { TargetingRulesSection, WeightSplitEditor, type SplitRow } from "@/components/campaign";
import { fetchAllPages } from "@/lib/paginate";
import { clickUrl, redirectUrl, trackerScriptTag, type DomainRow } from "@/lib/tracking-urls";
import { activeSum, slugify } from "@/lib/campaign-form";
import { api } from "@/lib/apiClient";
import { ApiError } from "@/lib/apiError";
import { reportApiError } from "@/lib/reportApiError";

type TrafficSource = { id: number; name: string; slug: string };
type Lander = { id: number; name: string; url: string };
type Offer = { id: number; name: string; url: string };

type CampaignPayload = {
  id: number;
  name: string;
  slug: string;
  status: string;
  destination_url: string;
  timezone: string | null;
  domain_id: number | null;
  traffic_source_id: number;
  daily_budget: string | number | null;
  monthly_budget: string | number | null;
  landers?: Array<{
    id: number;
    name: string;
    pivot: { weight_percent: number; is_active: boolean };
  }>;
  offers?: Array<{
    id: number;
    name: string;
    pivot: { weight_percent: number; is_active: boolean };
  }>;
  domain?: DomainRow | null;
};

type CampaignBuilderFormProps = {
  campaignId?: number;
};

/** Pivot booleans from JSON may be 0/1 or strings; avoid Boolean("0") === true. */
function pivotBool(value: unknown): boolean {
  if (value === true || value === 1) {
    return true;
  }
  if (value === false || value === 0) {
    return false;
  }
  if (typeof value === "string") {
    const t = value.trim().toLowerCase();
    return t === "1" || t === "true" || t === "yes";
  }
  return false;
}

export function CampaignBuilderForm({ campaignId }: CampaignBuilderFormProps) {
  const router = useRouter();
  const isNew = !campaignId;

  const [loading, setLoading] = useState(!isNew);
  /** Domains, landers, offers, traffic sources — must be ready before traffic source is usable. */
  const [refsLoading, setRefsLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [slugTouched, setSlugTouched] = useState(false);

  const [id, setId] = useState<number | null>(campaignId ?? null);

  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [status, setStatus] = useState("active");
  const [destinationUrl, setDestinationUrl] = useState("https://example.com");
  const [timezone, setTimezone] = useState("UTC");
  const [domainId, setDomainId] = useState<number | "">("");
  const [trafficSourceId, setTrafficSourceId] = useState<number | "">("");
  const [dailyBudget, setDailyBudget] = useState("");
  const [monthlyBudget, setMonthlyBudget] = useState("");

  const [landerRows, setLanderRows] = useState<SplitRow[]>([]);
  const [offerRows, setOfferRows] = useState<SplitRow[]>([]);

  const [domains, setDomains] = useState<DomainRow[]>([]);
  const [landers, setLanders] = useState<Lander[]>([]);
  const [offers, setOffers] = useState<Offer[]>([]);
  const [trafficSources, setTrafficSources] = useState<TrafficSource[]>([]);
  /** Names from last successful campaign load — keeps selects valid when a pivot id is missing from catalog lists. */
  const [loadedSplitMeta, setLoadedSplitMeta] = useState<{
    landers: Array<{ id: number; name: string }>;
    offers: Array<{ id: number; name: string }>;
  }>({ landers: [], offers: [] });
  /** Campaign's domain from API when it is not returned by the domains index (e.g. legacy row). */
  const [loadedCampaignDomain, setLoadedCampaignDomain] = useState<DomainRow | null>(null);

  const domainChoices = useMemo(() => {
    if (!loadedCampaignDomain) {
      return domains;
    }
    if (domains.some((d) => d.id === loadedCampaignDomain.id)) {
      return domains;
    }
    return [...domains, loadedCampaignDomain].sort((a, b) => a.id - b.id);
  }, [domains, loadedCampaignDomain]);

  const selectedDomain = useMemo(
    () => domainChoices.find((d) => d.id === domainId) ?? null,
    [domainChoices, domainId],
  );

  const landerOptions = useMemo(() => {
    const byId = new Map<number, { id: number; label: string }>();
    for (const l of landers) {
      byId.set(l.id, { id: l.id, label: l.name });
    }
    for (const meta of loadedSplitMeta.landers) {
      if (!byId.has(meta.id)) {
        byId.set(meta.id, { id: meta.id, label: `${meta.name} (not in catalog)` });
      }
    }
    for (const r of landerRows) {
      if (!byId.has(r.id)) {
        byId.set(r.id, { id: r.id, label: `Lander #${r.id} (not in catalog)` });
      }
    }
    return [...byId.values()].sort((a, b) => a.id - b.id);
  }, [landers, landerRows, loadedSplitMeta.landers]);

  const offerOptions = useMemo(() => {
    const byId = new Map<number, { id: number; label: string }>();
    for (const o of offers) {
      byId.set(o.id, { id: o.id, label: o.name });
    }
    for (const meta of loadedSplitMeta.offers) {
      if (!byId.has(meta.id)) {
        byId.set(meta.id, { id: meta.id, label: `${meta.name} (not in catalog)` });
      }
    }
    for (const r of offerRows) {
      if (!byId.has(r.id)) {
        byId.set(r.id, { id: r.id, label: `Offer #${r.id} (not in catalog)` });
      }
    }
    return [...byId.values()].sort((a, b) => a.id - b.id);
  }, [offers, offerRows, loadedSplitMeta.offers]);

  const landerSplitValid = landerRows.length === 0 || activeSum(landerRows) === 100;
  const offerSplitValid = offerRows.length === 0 || activeSum(offerRows) === 100;

  const targetingOffers = useMemo(() => {
    const byId = new Map<number, { id: number; name: string }>();
    for (const o of offers) {
      byId.set(o.id, { id: o.id, name: o.name });
    }
    for (const meta of loadedSplitMeta.offers) {
      if (!byId.has(meta.id)) {
        byId.set(meta.id, { id: meta.id, name: meta.name });
      }
    }
    return [...byId.values()].sort((a, b) => a.id - b.id);
  }, [offers, loadedSplitMeta.offers]);

  const loadCampaign = useCallback(async () => {
    if (!campaignId) {
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const { data } = await api.get<{ data: CampaignPayload }>(`v1/campaigns/${campaignId}`);
      setId(data.id);
      setName(data.name);
      setSlug(data.slug);
      setStatus(data.status);
      setDestinationUrl(data.destination_url);
      setTimezone(data.timezone ?? "UTC");
      setDomainId(data.domain_id ?? "");
      setTrafficSourceId(data.traffic_source_id);
      setDailyBudget(data.daily_budget != null ? String(data.daily_budget) : "");
      setMonthlyBudget(data.monthly_budget != null ? String(data.monthly_budget) : "");
      setLanderRows(
        (data.landers ?? []).map((row) => ({
          id: row.id,
          weight_percent: Number(row.pivot.weight_percent),
          is_active: pivotBool(row.pivot.is_active),
        })),
      );
      setOfferRows(
        (data.offers ?? []).map((row) => ({
          id: row.id,
          weight_percent: Number(row.pivot.weight_percent),
          is_active: pivotBool(row.pivot.is_active),
        })),
      );
      setLoadedSplitMeta({
        landers: (data.landers ?? []).map((row) => ({ id: row.id, name: row.name })),
        offers: (data.offers ?? []).map((row) => ({ id: row.id, name: row.name })),
      });
      setLoadedCampaignDomain(
        data.domain ??
          (data.domain_id != null
            ? { id: data.domain_id, name: `Domain #${data.domain_id}` }
            : null),
      );
      setSlugTouched(true);
    } catch (e) {
      reportApiError(e);
      setError(ApiError.isApiError(e) ? e.message : "Failed to load campaign");
    } finally {
      setLoading(false);
    }
  }, [campaignId]);

  useEffect(() => {
    let cancelled = false;
    setRefsLoading(true);
    (async () => {
      try {
        const [d, l, o, ts] = await Promise.all([
          fetchAllPages<DomainRow>((page) => api.get(`v1/domains?page=${page}`)),
          fetchAllPages<Lander>((page) => api.get(`v1/landers?page=${page}`)),
          fetchAllPages<Offer>((page) => api.get(`v1/offers?page=${page}`)),
          api.get<{ data: TrafficSource[] }>("v1/traffic-sources").then((r) => r.data),
        ]);
        if (cancelled) {
          return;
        }
        setDomains(d);
        setLanders(l);
        setOffers(o);
        setTrafficSources(ts);
      } catch (e) {
        if (!cancelled) {
          reportApiError(e);
          setError(ApiError.isApiError(e) ? e.message : "Failed to load reference data");
        }
      } finally {
        if (!cancelled) {
          setRefsLoading(false);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!campaignId && trafficSources.length > 0 && trafficSourceId === "") {
      setTrafficSourceId(trafficSources[0].id);
    }
  }, [campaignId, trafficSources, trafficSourceId]);

  useEffect(() => {
    if (campaignId) {
      void loadCampaign();
    }
  }, [campaignId, loadCampaign]);

  const persist = async () => {
    setSaving(true);
    setError(null);
    if (!trafficSourceId) {
      setError("Select a traffic source.");
      setSaving(false);
      return;
    }
    if (!landerSplitValid || !offerSplitValid) {
      setError("Each non-empty lander or offer split must total 100% for active rows.");
      setSaving(false);
      return;
    }

    const body: Record<string, unknown> = {
      name,
      slug,
      status,
      destination_url: destinationUrl,
      timezone: timezone || "UTC",
      domain_id: domainId === "" ? null : domainId,
      traffic_source_id: trafficSourceId,
      daily_budget: dailyBudget.trim() === "" ? null : Number(dailyBudget),
      monthly_budget: monthlyBudget.trim() === "" ? null : Number(monthlyBudget),
    };

    body.landers = landerRows.map((r) => ({
      id: r.id,
      weight_percent: r.weight_percent,
      is_active: r.is_active,
    }));
    body.offers = offerRows.map((r) => ({
      id: r.id,
      weight_percent: r.weight_percent,
      is_active: r.is_active,
    }));

    try {
      if (isNew || !id) {
        const created = await api.post<{ data: CampaignPayload }>("v1/campaigns", body);
        router.push(`/campaigns/${created.data.id}/edit`);
        router.refresh();
        return;
      }
      await api.patch(`v1/campaigns/${id}`, body);
      await loadCampaign();
    } catch (e) {
      reportApiError(e);
      setError(ApiError.isApiError(e) ? e.message : "Save failed");
    } finally {
      setSaving(false);
    }
  };

  const copy = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      setError("Could not copy to clipboard.");
    }
  };

  const testOpen = (url: string) => {
    window.open(url, "_blank", "noopener,noreferrer");
  };

  const title = isNew ? "New campaign" : `Edit campaign · ${name || slug || id}`;

  const pageBusy = refsLoading || (!!campaignId && loading);

  const redirect = id ? redirectUrl(slug, selectedDomain) : "";
  const click = id ? clickUrl(slug, selectedDomain) : "";
  const scriptTag = id ? trackerScriptTag(id, selectedDomain) : "";

  return (
    <AdminShell
      title={title}
      topbarRight={
        <div className="flex flex-wrap items-center gap-2">
          <Link
            href="/traffic-sources"
            className="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
          >
            Traffic sources
          </Link>
          <Link
            href="/campaigns"
            className="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
          >
            All campaigns
          </Link>
        </div>
      }
    >
      {pageBusy ? (
        <p className="text-sm text-slate-600">
          {refsLoading ? "Loading domains, landers, offers, and traffic sources…" : "Loading campaign…"}
        </p>
      ) : (
        <div className="flex flex-col gap-6">
          {error ? (
            <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
              {error}
            </p>
          ) : null}

          <section className="rounded-lg border border-slate-300 bg-white p-4 shadow-sm">
            <h2 className="text-base font-semibold text-slate-900">Campaign details</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <label className="form-field">
                <span className="form-label">Name</span>
                <input
                  className="form-control"
                  value={name}
                  onChange={(e) => {
                    const v = e.target.value;
                    setName(v);
                    if (!slugTouched) {
                      setSlug(slugify(v));
                    }
                  }}
                />
              </label>
              <label className="form-field">
                <span className="form-label">Slug</span>
                <input
                  className="form-control"
                  value={slug}
                  onChange={(e) => {
                    setSlugTouched(true);
                    setSlug(e.target.value);
                  }}
                />
              </label>
              <label className="form-field">
                <span className="form-label">Status</span>
                <select
                  className="form-control"
                  value={status}
                  onChange={(e) => setStatus(e.target.value)}
                >
                  <option value="draft">draft</option>
                  <option value="active">active</option>
                  <option value="paused">paused</option>
                  <option value="archived">archived</option>
                </select>
              </label>
              <label className="form-field">
                <span className="form-label">Traffic source</span>
                <select
                  className="form-control disabled:bg-slate-100 disabled:text-slate-600"
                  disabled={refsLoading}
                  value={trafficSourceId}
                  onChange={(e) =>
                    setTrafficSourceId(e.target.value ? Number(e.target.value) : "")
                  }
                >
                  <option value="">
                    {refsLoading ? "Loading…" : "Select…"}
                  </option>
                  {trafficSources.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </select>
                {!refsLoading && trafficSources.length === 0 ? (
                  <span className="mt-1 block text-xs text-amber-800">
                    No traffic sources available. Open{" "}
                    <Link href="/traffic-sources" className="font-medium underline">
                      Traffic sources
                    </Link>{" "}
                    or seed the database before saving a campaign.
                  </span>
                ) : null}
              </label>
              <label className="form-field sm:col-span-2">
                <span className="form-label">Destination URL (fallback)</span>
                <input
                  className="form-control"
                  value={destinationUrl}
                  onChange={(e) => setDestinationUrl(e.target.value)}
                />
              </label>
              <label className="form-field">
                <span className="form-label">Domain</span>
                <select
                  className="form-control"
                  value={domainId}
                  onChange={(e) =>
                    setDomainId(e.target.value === "" ? "" : Number(e.target.value))
                  }
                >
                  <option value="">Default (public origin)</option>
                  {domainChoices.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name}
                    </option>
                  ))}
                </select>
                <span className="mt-1 block text-xs text-slate-500">
                  Used to preview tracking links with your tracking hostname.
                </span>
              </label>
              <label className="form-field">
                <span className="form-label">Timezone</span>
                <input
                  className="form-control"
                  value={timezone}
                  onChange={(e) => setTimezone(e.target.value)}
                />
              </label>
              <label className="form-field">
                <span className="form-label">Daily budget</span>
                <input
                  className="form-control"
                  value={dailyBudget}
                  onChange={(e) => setDailyBudget(e.target.value)}
                  inputMode="decimal"
                />
              </label>
              <label className="form-field">
                <span className="form-label">Monthly budget</span>
                <input
                  className="form-control"
                  value={monthlyBudget}
                  onChange={(e) => setMonthlyBudget(e.target.value)}
                  inputMode="decimal"
                />
              </label>
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
              <button
                type="button"
                className="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                disabled={
                  saving ||
                  refsLoading ||
                  !landerSplitValid ||
                  !offerSplitValid ||
                  !trafficSourceId
                }
                onClick={() => void persist()}
              >
                {saving ? "Saving…" : "Save campaign"}
              </button>
            </div>
          </section>

          <WeightSplitEditor
            title="Landers"
            description="Weighted rotation for the first hop (/campaign/{slug}). Active weights must total 100% when any row exists."
            options={landerOptions}
            rows={landerRows}
            onChange={setLanderRows}
          />

          <WeightSplitEditor
            title="Offers"
            description="Default weighted offer split after targeting rules are evaluated on /click."
            options={offerOptions}
            rows={offerRows}
            onChange={setOfferRows}
          />

          <TargetingRulesSection campaignId={id} offers={targetingOffers} />

          {id ? (
            <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              <h2 className="text-base font-semibold text-slate-900">Tracking URL and script</h2>
              <p className="mt-1 text-sm text-slate-600">
                Redirect entry (landers). Click URL is used from the lander toward offers.
              </p>

              <div className="mt-4 space-y-4">
                <div>
                  <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Redirect URL
                  </div>
                  <pre className="mt-1 whitespace-pre-wrap break-all rounded-md bg-slate-50 p-3 text-sm text-slate-900">
                    {redirect}
                  </pre>
                  <div className="mt-2 flex flex-wrap gap-2">
                    <button
                      type="button"
                      className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium hover:bg-slate-50"
                      onClick={() => void copy(redirect)}
                    >
                      Copy
                    </button>
                    <button
                      type="button"
                      className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium hover:bg-slate-50"
                      onClick={() => testOpen(redirect)}
                    >
                      Open test
                    </button>
                  </div>
                </div>

                <div>
                  <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Click URL (example)
                  </div>
                  <pre className="mt-1 whitespace-pre-wrap break-all rounded-md bg-slate-50 p-3 text-sm text-slate-900">
                    {click}
                  </pre>
                  <div className="mt-2 flex flex-wrap gap-2">
                    <button
                      type="button"
                      className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium hover:bg-slate-50"
                      onClick={() => void copy(click)}
                    >
                      Copy
                    </button>
                  </div>
                </div>

                <div>
                  <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Tracker script snippet
                  </div>
                  <pre className="mt-1 whitespace-pre-wrap break-all rounded-md bg-slate-50 p-3 text-sm text-slate-900">
                    {scriptTag}
                  </pre>
                  <div className="mt-2 flex flex-wrap gap-2">
                    <button
                      type="button"
                      className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium hover:bg-slate-50"
                      onClick={() => void copy(scriptTag)}
                    >
                      Copy snippet
                    </button>
                  </div>
                </div>
              </div>
            </section>
          ) : null}
        </div>
      )}
    </AdminShell>
  );
}
