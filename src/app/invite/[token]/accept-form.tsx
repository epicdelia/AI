"use client";

import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";

export default function AcceptInviteForm({
  token,
  orgName,
  email,
}: {
  token: string;
  orgName: string;
  email: string;
}) {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    const form = new FormData(e.currentTarget);
    const res = await fetch("/api/team/accept", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token, name: form.get("name"), password: form.get("password") }),
    });
    if (res.ok) {
      router.push("/dashboard");
      router.refresh();
    } else {
      const data = await res.json().catch(() => ({}));
      setError(data.error ?? "Something went wrong.");
      setLoading(false);
    }
  }

  const field =
    "w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white outline-none focus:border-indigo-500";

  return (
    <div className="rounded-2xl border border-zinc-800 bg-zinc-900 p-8">
      <h1 className="mb-1 text-lg font-semibold text-white">Join {orgName}</h1>
      <p className="mb-6 text-sm text-zinc-400">
        You&apos;ve been invited as <span className="text-zinc-200">{email}</span>. Set up your
        account to get started.
      </p>
      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <label className="mb-1 block text-sm text-zinc-400" htmlFor="name">
            Your name
          </label>
          <input id="name" name="name" required className={field} />
        </div>
        <div>
          <label className="mb-1 block text-sm text-zinc-400" htmlFor="password">
            Password
          </label>
          <input
            id="password"
            name="password"
            type="password"
            minLength={8}
            required
            className={field}
          />
        </div>
        {error && <p className="text-sm text-red-400">{error}</p>}
        <button
          type="submit"
          disabled={loading}
          className="w-full rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-400 disabled:opacity-50"
        >
          {loading ? "Joining…" : "Join workspace"}
        </button>
      </form>
    </div>
  );
}
