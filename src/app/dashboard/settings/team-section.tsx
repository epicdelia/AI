"use client";

import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";

interface Member {
  id: string;
  name: string;
  email: string;
  role: string;
}

export default function TeamSection({
  members,
  currentUserId,
  isAdmin,
  seatLimit,
}: {
  members: Member[];
  currentUserId: string;
  isAdmin: boolean;
  seatLimit: number;
}) {
  const router = useRouter();
  const [inviteUrl, setInviteUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function invite(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    setInviteUrl(null);
    setBusy(true);
    const form = new FormData(e.currentTarget);
    const res = await fetch("/api/team/invite", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: form.get("email"), role: form.get("role") }),
    });
    const data = await res.json().catch(() => ({}));
    if (res.ok) {
      setInviteUrl(`${window.location.origin}${data.inviteUrl}`);
      (e.target as HTMLFormElement).reset();
    } else {
      setError(data.error ?? "Something went wrong.");
    }
    setBusy(false);
  }

  async function remove(id: string) {
    const res = await fetch(`/api/team/members/${id}`, { method: "DELETE" });
    if (res.ok) router.refresh();
  }

  return (
    <section>
      <h2 className="text-base font-semibold text-white">Team</h2>
      <p className="mt-1 text-sm text-zinc-500">
        {members.length} of {seatLimit} seats used.
      </p>
      <ul className="mt-4 divide-y divide-zinc-800 rounded-xl border border-zinc-800">
        {members.map((m) => (
          <li key={m.id} className="flex items-center gap-3 px-4 py-3">
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm text-zinc-200">
                {m.name}
                {m.id === currentUserId && <span className="text-zinc-500"> (you)</span>}
              </p>
              <p className="truncate text-xs text-zinc-500">{m.email}</p>
            </div>
            <span className="rounded-full border border-zinc-700 px-2 py-0.5 text-xs text-zinc-400">
              {m.role.toLowerCase()}
            </span>
            {isAdmin && m.role !== "OWNER" && m.id !== currentUserId && (
              <button
                onClick={() => remove(m.id)}
                className="text-xs text-zinc-500 hover:text-red-400"
              >
                Remove
              </button>
            )}
          </li>
        ))}
      </ul>

      {isAdmin && (
        <form onSubmit={invite} className="mt-4 flex gap-2">
          <input
            name="email"
            type="email"
            required
            placeholder="teammate@company.com"
            className="flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white outline-none focus:border-indigo-500"
          />
          <select
            name="role"
            className="rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-2 text-sm text-zinc-300 outline-none"
          >
            <option value="MEMBER">Member</option>
            <option value="ADMIN">Admin</option>
          </select>
          <button
            type="submit"
            disabled={busy}
            className="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-400 disabled:opacity-50"
          >
            Invite
          </button>
        </form>
      )}
      {error && <p className="mt-2 text-sm text-red-400">{error}</p>}
      {inviteUrl && (
        <div className="mt-3 rounded-lg border border-emerald-800 bg-emerald-950/40 p-3 text-sm">
          <p className="text-emerald-300">Invite created — share this link:</p>
          <code className="mt-1 block break-all text-xs text-emerald-200">{inviteUrl}</code>
        </div>
      )}
    </section>
  );
}
