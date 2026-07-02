"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";

export default function SignupPage() {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    const form = new FormData(e.currentTarget);
    const res = await fetch("/api/auth/signup", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        name: form.get("name"),
        email: form.get("email"),
        password: form.get("password"),
        orgName: form.get("orgName"),
      }),
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
    <main className="flex min-h-screen items-center justify-center bg-zinc-950 px-4">
      <div className="w-full max-w-sm">
        <Link href="/" className="mb-8 block text-center text-xl font-semibold text-white">
          Fluent<span className="text-indigo-400">AI</span>
        </Link>
        <div className="rounded-2xl border border-zinc-800 bg-zinc-900 p-8">
          <h1 className="mb-6 text-lg font-semibold text-white">Create your workspace</h1>
          <form onSubmit={onSubmit} className="space-y-4">
            <div>
              <label className="mb-1 block text-sm text-zinc-400" htmlFor="orgName">
                Company name
              </label>
              <input id="orgName" name="orgName" required className={field} />
            </div>
            <div>
              <label className="mb-1 block text-sm text-zinc-400" htmlFor="name">
                Your name
              </label>
              <input id="name" name="name" required className={field} />
            </div>
            <div>
              <label className="mb-1 block text-sm text-zinc-400" htmlFor="email">
                Work email
              </label>
              <input id="email" name="email" type="email" required className={field} />
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
              {loading ? "Creating workspace…" : "Create workspace"}
            </button>
          </form>
        </div>
        <p className="mt-4 text-center text-sm text-zinc-500">
          Already have an account?{" "}
          <Link href="/login" className="text-indigo-400 hover:underline">
            Sign in
          </Link>
        </p>
      </div>
    </main>
  );
}
