"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { PLANS, PlanId } from "@/lib/plans";

export default function PlanSection({
  currentPlan,
  isOwner,
}: {
  currentPlan: PlanId;
  isOwner: boolean;
}) {
  const router = useRouter();
  const [busy, setBusy] = useState<PlanId | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function switchPlan(plan: PlanId) {
    setError(null);
    setBusy(plan);
    const res = await fetch("/api/org/plan", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ plan }),
    });
    if (res.ok) {
      router.refresh();
    } else {
      const data = await res.json().catch(() => ({}));
      setError(data.error ?? "Something went wrong.");
    }
    setBusy(null);
  }

  return (
    <section>
      <h2 className="text-base font-semibold text-white">Billing plan</h2>
      <p className="mt-1 text-sm text-zinc-500">
        Plan changes apply immediately. (In production this connects to Stripe.)
      </p>
      <div className="mt-4 grid gap-3 sm:grid-cols-3">
        {Object.values(PLANS).map((p) => {
          const active = p.id === currentPlan;
          return (
            <div
              key={p.id}
              className={`rounded-xl border p-4 ${
                active ? "border-indigo-500 bg-indigo-500/5" : "border-zinc-800"
              }`}
            >
              <p className="text-sm font-semibold text-white">{p.name}</p>
              <p className="mt-1 text-lg text-white">
                ${p.priceMonthly}
                <span className="text-xs text-zinc-500">/seat/mo</span>
              </p>
              <ul className="mt-2 space-y-1 text-xs text-zinc-400">
                <li>{p.monthlyTokenLimit.toLocaleString()} tokens/mo</li>
                <li>{p.seatLimit} seats</li>
                <li>{p.models.join(", ")}</li>
                <li>{p.apiAccess ? "API access" : "No API access"}</li>
              </ul>
              {isOwner && !active && (
                <button
                  onClick={() => switchPlan(p.id)}
                  disabled={busy !== null}
                  className="mt-3 w-full rounded-lg border border-indigo-500 px-3 py-1.5 text-xs font-medium text-indigo-300 hover:bg-indigo-500/10 disabled:opacity-50"
                >
                  {busy === p.id ? "Switching…" : `Switch to ${p.name}`}
                </button>
              )}
              {active && (
                <p className="mt-3 text-center text-xs font-medium text-indigo-400">
                  Current plan
                </p>
              )}
            </div>
          );
        })}
      </div>
      {error && <p className="mt-2 text-sm text-red-400">{error}</p>}
    </section>
  );
}
