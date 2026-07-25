"use client";

import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";

export default function WorkspaceSection({
  orgName,
  isAdmin,
}: {
  orgName: string;
  isAdmin: boolean;
}) {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [busy, setBusy] = useState(false);

  async function rename(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    setSaved(false);
    setBusy(true);
    const form = new FormData(e.currentTarget);
    const res = await fetch("/api/org/name", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name: form.get("name") }),
    });
    if (res.ok) {
      setSaved(true);
      router.refresh();
    } else {
      const data = await res.json().catch(() => ({}));
      setError(data.error ?? "Something went wrong.");
    }
    setBusy(false);
  }

  return (
    <section>
      <h2 className="text-base font-semibold text-white">Workspace</h2>
      <form onSubmit={rename} className="mt-4 flex gap-2">
        <input
          name="name"
          defaultValue={orgName}
          maxLength={80}
          required
          disabled={!isAdmin}
          className="flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white outline-none focus:border-indigo-500 disabled:opacity-60"
        />
        {isAdmin && (
          <button
            type="submit"
            disabled={busy}
            className="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-400 disabled:opacity-50"
          >
            {busy ? "Saving…" : "Save"}
          </button>
        )}
      </form>
      {error && <p className="mt-2 text-sm text-red-400">{error}</p>}
      {saved && <p className="mt-2 text-sm text-emerald-400">Workspace name updated.</p>}
      {!isAdmin && (
        <p className="mt-2 text-xs text-zinc-500">Only admins can rename the workspace.</p>
      )}
    </section>
  );
}
