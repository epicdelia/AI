import { describe, expect, it } from "vitest";
import { PLANS, getPlan, DEFAULT_MODEL } from "@/lib/plans";
import { generateApiKey, hashApiKey } from "@/lib/api-keys";
import { estimateTokens, monthStart } from "@/lib/usage";
import { slugify, hashPassword, verifyPassword } from "@/lib/auth";
import { streamChatCompletion, isDemoMode } from "@/lib/ai";

describe("plans", () => {
  it("falls back to FREE for unknown plan ids", () => {
    expect(getPlan("NONSENSE").id).toBe("FREE");
    expect(getPlan("").id).toBe("FREE");
  });

  it("returns the matching plan for known ids", () => {
    expect(getPlan("PRO").id).toBe("PRO");
    expect(getPlan("ENTERPRISE").id).toBe("ENTERPRISE");
  });

  it("tiers are strictly increasing in quota and seats", () => {
    expect(PLANS.FREE.monthlyTokenLimit).toBeLessThan(PLANS.PRO.monthlyTokenLimit);
    expect(PLANS.PRO.monthlyTokenLimit).toBeLessThan(PLANS.ENTERPRISE.monthlyTokenLimit);
    expect(PLANS.FREE.seatLimit).toBeLessThan(PLANS.PRO.seatLimit);
    expect(PLANS.PRO.seatLimit).toBeLessThan(PLANS.ENTERPRISE.seatLimit);
  });

  it("every plan includes the default model", () => {
    for (const plan of Object.values(PLANS)) {
      expect(plan.models).toContain(DEFAULT_MODEL);
    }
  });

  it("free tier has no API access; paid tiers do", () => {
    expect(PLANS.FREE.apiAccess).toBe(false);
    expect(PLANS.PRO.apiAccess).toBe(true);
    expect(PLANS.ENTERPRISE.apiAccess).toBe(true);
  });
});

describe("api keys", () => {
  it("generates keys with the ak_live_ prefix and a matching displayed prefix", () => {
    const { key, prefix, keyHash } = generateApiKey();
    expect(key).toMatch(/^ak_live_[0-9a-f]{48}$/);
    expect(key.startsWith(prefix)).toBe(true);
    expect(prefix).toHaveLength(12);
    expect(keyHash).toBe(hashApiKey(key));
  });

  it("hashing is deterministic and never stores the raw key", () => {
    const { key, keyHash } = generateApiKey();
    expect(hashApiKey(key)).toBe(keyHash);
    expect(keyHash).not.toContain(key.slice(8));
    expect(keyHash).toMatch(/^[0-9a-f]{64}$/); // sha256 hex
  });

  it("generates unique keys", () => {
    const keys = new Set(Array.from({ length: 50 }, () => generateApiKey().key));
    expect(keys.size).toBe(50);
  });
});

describe("usage", () => {
  it("estimateTokens is ~chars/4 with a floor of 1", () => {
    expect(estimateTokens("")).toBe(1);
    expect(estimateTokens("abcd")).toBe(1);
    expect(estimateTokens("a".repeat(400))).toBe(100);
  });

  it("monthStart returns the first of the month at UTC midnight", () => {
    const d = monthStart(new Date("2026-08-19T15:30:00Z"));
    expect(d.toISOString()).toBe("2026-08-01T00:00:00.000Z");
  });
});

describe("auth helpers", () => {
  it("slugify produces url-safe slugs", () => {
    expect(slugify("Acme Corp")).toBe("acme-corp");
    expect(slugify("  Héllo!! World  ")).toBe("h-llo-world");
    expect(slugify("!!!")).toBe("org");
    expect(slugify("x".repeat(100)).length).toBeLessThanOrEqual(40);
  });

  it("hashes and verifies passwords", async () => {
    const hash = await hashPassword("s3cret-password");
    expect(hash).not.toContain("s3cret");
    expect(await verifyPassword("s3cret-password", hash)).toBe(true);
    expect(await verifyPassword("wrong", hash)).toBe(false);
  });
});

describe("ai demo mode", () => {
  it("is active when OPENAI_API_KEY is unset", () => {
    expect(isDemoMode()).toBe(true);
  });

  it("streams a response and reports non-zero usage", async () => {
    const result = await streamChatCompletion("gpt-4o-mini", [
      { role: "user", content: "Hello there" },
    ]);
    let text = "";
    for await (const delta of result.stream) text += delta;
    expect(text).toContain("Demo mode");
    expect(text).toContain("Hello there");
    const usage = result.getUsage();
    expect(usage.promptTokens).toBeGreaterThan(0);
    expect(usage.completionTokens).toBeGreaterThan(0);
  });
});
