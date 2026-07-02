import { getCurrentUser } from "@/lib/auth";
import { db } from "@/lib/db";
import { getPlan } from "@/lib/plans";
import { monthStart } from "@/lib/usage";
import UsageChart from "./usage-chart";

export default async function UsagePage() {
  const user = await getCurrentUser();
  if (!user) return null;
  const plan = getPlan(user.org.plan);

  const since = new Date(Date.now() - 29 * 24 * 60 * 60 * 1000);
  since.setUTCHours(0, 0, 0, 0);

  const [monthAgg, monthRequests, seats, records] = await Promise.all([
    db.usageRecord.aggregate({
      where: { orgId: user.orgId, createdAt: { gte: monthStart() } },
      _sum: { promptTokens: true, completionTokens: true },
    }),
    db.usageRecord.count({
      where: { orgId: user.orgId, createdAt: { gte: monthStart() } },
    }),
    db.user.count({ where: { orgId: user.orgId } }),
    db.usageRecord.findMany({
      where: { orgId: user.orgId, createdAt: { gte: since } },
      select: { model: true, source: true, promptTokens: true, completionTokens: true, createdAt: true },
    }),
  ]);

  const monthTokens =
    (monthAgg._sum.promptTokens ?? 0) + (monthAgg._sum.completionTokens ?? 0);
  const quotaPct = Math.min(100, (monthTokens / plan.monthlyTokenLimit) * 100);

  // Daily buckets, last 30 days (UTC).
  const days: { date: string; label: string; tokens: number }[] = [];
  for (let i = 29; i >= 0; i--) {
    const d = new Date(Date.now() - i * 24 * 60 * 60 * 1000);
    const key = d.toISOString().slice(0, 10);
    days.push({
      date: key,
      label: d.toLocaleDateString("en-US", { month: "short", day: "numeric", timeZone: "UTC" }),
      tokens: 0,
    });
  }
  const byDay = new Map(days.map((d) => [d.date, d]));
  const byModel = new Map<string, { tokens: number; requests: number }>();
  let webTokens = 0;
  let apiTokens = 0;
  for (const r of records) {
    const tokens = r.promptTokens + r.completionTokens;
    const day = byDay.get(r.createdAt.toISOString().slice(0, 10));
    if (day) day.tokens += tokens;
    const m = byModel.get(r.model) ?? { tokens: 0, requests: 0 };
    m.tokens += tokens;
    m.requests += 1;
    byModel.set(r.model, m);
    if (r.source === "API") apiTokens += tokens;
    else webTokens += tokens;
  }

  const tiles = [
    {
      label: "Tokens this month",
      value: monthTokens.toLocaleString(),
      sub: `of ${plan.monthlyTokenLimit.toLocaleString()} (${plan.name} plan)`,
      meterPct: quotaPct,
    },
    {
      label: "Requests this month",
      value: monthRequests.toLocaleString(),
      sub: "web chat + API combined",
    },
    {
      label: "Seats in use",
      value: `${seats} / ${plan.seatLimit}`,
      sub: "manage in Settings → Team",
    },
  ];

  return (
    <div className="h-full overflow-y-auto">
      <div className="mx-auto max-w-4xl space-y-8 px-6 py-10">
        <header>
          <h1 className="text-xl font-semibold text-white">Usage</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Token consumption across your workspace, updated in real time.
          </p>
        </header>

        <div className="grid gap-3 sm:grid-cols-3">
          {tiles.map((t) => (
            <div key={t.label} className="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
              <p className="text-xs text-zinc-500">{t.label}</p>
              <p className="mt-1 text-2xl font-semibold text-white">{t.value}</p>
              <p className="mt-1 text-xs text-zinc-500">{t.sub}</p>
              {t.meterPct !== undefined && (
                <div
                  className="mt-3 h-1.5 overflow-hidden rounded-full bg-zinc-800"
                  role="meter"
                  aria-valuenow={Math.round(t.meterPct)}
                  aria-valuemin={0}
                  aria-valuemax={100}
                  aria-label="Monthly quota used"
                >
                  <div
                    className={`h-full rounded-full ${
                      t.meterPct >= 90 ? "bg-red-500" : "bg-indigo-500"
                    }`}
                    style={{ width: `${t.meterPct}%` }}
                  />
                </div>
              )}
            </div>
          ))}
        </div>

        <UsageChart days={days} />

        <section className="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
          <h2 className="text-sm font-semibold text-white">Breakdown (last 30 days)</h2>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-zinc-800 text-left text-xs text-zinc-500">
                  <th className="py-2 pr-4 font-normal">Model</th>
                  <th className="py-2 pr-4 text-right font-normal">Requests</th>
                  <th className="py-2 text-right font-normal">Tokens</th>
                </tr>
              </thead>
              <tbody>
                {byModel.size === 0 && (
                  <tr>
                    <td colSpan={3} className="py-3 text-zinc-600">
                      No usage yet — start a chat to see data here.
                    </td>
                  </tr>
                )}
                {[...byModel.entries()]
                  .sort((a, b) => b[1].tokens - a[1].tokens)
                  .map(([model, m]) => (
                    <tr key={model} className="border-b border-zinc-800/60 text-zinc-300">
                      <td className="py-2 pr-4">{model}</td>
                      <td className="py-2 pr-4 text-right tabular-nums">
                        {m.requests.toLocaleString()}
                      </td>
                      <td className="py-2 text-right tabular-nums">
                        {m.tokens.toLocaleString()}
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>
          <p className="mt-3 text-xs text-zinc-500">
            Web chat: {webTokens.toLocaleString()} tokens · API:{" "}
            {apiTokens.toLocaleString()} tokens
          </p>
        </section>
      </div>
    </div>
  );
}
