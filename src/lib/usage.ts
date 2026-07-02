import { db } from "./db";
import { getPlan } from "./plans";

export function monthStart(now = new Date()) {
  return new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), 1));
}

export async function getMonthlyTokenUsage(orgId: string) {
  const agg = await db.usageRecord.aggregate({
    where: { orgId, createdAt: { gte: monthStart() } },
    _sum: { promptTokens: true, completionTokens: true },
  });
  return (agg._sum.promptTokens ?? 0) + (agg._sum.completionTokens ?? 0);
}

export async function checkQuota(orgId: string, plan: string) {
  const used = await getMonthlyTokenUsage(orgId);
  const limit = getPlan(plan).monthlyTokenLimit;
  return { used, limit, exceeded: used >= limit };
}

export async function recordUsage(data: {
  orgId: string;
  userId?: string;
  apiKeyId?: string;
  model: string;
  promptTokens: number;
  completionTokens: number;
  source: "WEB" | "API";
}) {
  await db.usageRecord.create({ data });
}

// Rough fallback when the provider doesn't return usage (e.g. demo mode).
export function estimateTokens(text: string) {
  return Math.max(1, Math.ceil(text.length / 4));
}
