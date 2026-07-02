"use client";

import { FormEvent, useState } from "react";

interface KeyRow {
  id: string;
  name: string;
  prefix: string;
  createdAt: string;
  lastUsedAt: string | null;
}

export default function ApiKeysSection({
  initialKeys,
  isAdmin,
  apiAccess,
}: {
  initialKeys: KeyRow[];
  isAdmin: boolean;
  apiAccess: boolean;
}) {
  const [keys, setKeys] = useState(initialKeys);
  const [newKey, setNewKey] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function create(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    const form = new FormData(e.currentTarget);
    const res = await fetch("/api/keys", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name: form.get("name") }),
    });
    const data = await res.json().catch(() => ({}));
    if (res.ok) {
      setNewKey(data.key);
      setKeys((k) => [
        {
          id: data.id,
          name: data.name,
          prefix: data.prefix,
          createdAt: new Date().toISOString(),
          lastUsedAt: null,
        },
        ...k,
      ]);
      (e.target as HTMLFormElement).reset();
    } else {
      setError(data.error ?? "Something went wrong.");
    }
    setBusy(false);
  }

  async function revoke(id: string) {
    const res = await fetch(`/api/keys/${id}`, { method: "DELETE" });
    if (res.ok) setKeys((k) => k.filter((x) => x.id !== id));
  }

  return (
    <section>
      <h2 className="text-base font-semibold text-white">API keys</h2>
      <p className="mt-1 text-sm text-zinc-500">
        Use keys with{" "}
        <code className="rounded bg-zinc-800 px-1 py-0.5 text-xs">
          POST /api/v1/chat/completions
        </code>{" "}
        to integrate the assistant into your own products.
      </p>

      {!apiAccess && (
        <p className="mt-3 rounded-lg border border-amber-800 bg-amber-950/40 p-3 text-sm text-amber-300">
          API access is available on the Pro plan and above. Upgrade below to create keys.
        </p>
      )}

      {keys.length > 0 && (
        <ul className="mt-4 divide-y divide-zinc-800 rounded-xl border border-zinc-800">
          {keys.map((k) => (
            <li key={k.id} className="flex items-center gap-3 px-4 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-zinc-200">{k.name}</p>
                <p className="text-xs text-zinc-500">
                  <code>{k.prefix}…</code> · created{" "}
                  {new Date(k.createdAt).toLocaleDateString()}
                  {k.lastUsedAt &&
                    ` · last used ${new Date(k.lastUsedAt).toLocaleDateString()}`}
                </p>
              </div>
              {isAdmin && (
                <button
                  onClick={() => revoke(k.id)}
                  className="text-xs text-zinc-500 hover:text-red-400"
                >
                  Revoke
                </button>
              )}
            </li>
          ))}
        </ul>
      )}

      {isAdmin && apiAccess && (
        <form onSubmit={create} className="mt-4 flex gap-2">
          <input
            name="name"
            placeholder="Key name (e.g. production)"
            className="flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white outline-none focus:border-indigo-500"
          />
          <button
            type="submit"
            disabled={busy}
            className="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-400 disabled:opacity-50"
          >
            Create key
          </button>
        </form>
      )}
      {error && <p className="mt-2 text-sm text-red-400">{error}</p>}
      {newKey && (
        <div className="mt-3 rounded-lg border border-emerald-800 bg-emerald-950/40 p-3 text-sm">
          <p className="text-emerald-300">
            Key created — copy it now, it won&apos;t be shown again:
          </p>
          <code className="mt-1 block break-all text-xs text-emerald-200">{newKey}</code>
        </div>
      )}
    </section>
  );
}
