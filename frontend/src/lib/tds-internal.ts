export class TdsApiError extends Error {
  status: number;
  body: unknown;

  constructor(message: string, status: number, body: unknown) {
    super(message);
    this.name = "TdsApiError";
    this.status = status;
    this.body = body;
  }
}

export async function tdsFetch(path: string, init?: RequestInit): Promise<Response> {
  const normalized = path.replace(/^\//, "");
  return fetch(`/api/internal/${normalized}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...init?.headers,
    },
    cache: "no-store",
  });
}

export async function tdsJson<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await tdsFetch(path, init);
  const text = await res.text();
  let parsed: unknown = null;
  if (text) {
    try {
      parsed = JSON.parse(text) as unknown;
    } catch {
      parsed = text;
    }
  }

  if (!res.ok) {
    let msg =
      typeof parsed === "object" && parsed !== null && "message" in parsed
        ? String((parsed as { message?: unknown }).message)
        : res.statusText;
    if (
      typeof parsed === "object" &&
      parsed !== null &&
      "hint" in parsed &&
      typeof (parsed as { hint?: unknown }).hint === "string"
    ) {
      const hint = (parsed as { hint: string }).hint;
      msg = msg ? `${msg} — ${hint}` : hint;
    }
    throw new TdsApiError(msg || "Request failed", res.status, parsed);
  }

  return parsed as T;
}
