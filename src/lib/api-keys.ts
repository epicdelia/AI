import { createHash, randomBytes } from "crypto";
import { db } from "./db";

export function generateApiKey() {
  const secret = randomBytes(24).toString("hex");
  const key = `ak_live_${secret}`;
  return { key, prefix: key.slice(0, 12), keyHash: hashApiKey(key) };
}

export function hashApiKey(key: string) {
  return createHash("sha256").update(key).digest("hex");
}

export async function verifyApiKey(key: string) {
  if (!key.startsWith("ak_live_")) return null;
  const apiKey = await db.apiKey.findUnique({
    where: { keyHash: hashApiKey(key) },
    include: { org: true },
  });
  if (!apiKey || apiKey.revokedAt) return null;
  // fire-and-forget last-used stamp
  db.apiKey
    .update({ where: { id: apiKey.id }, data: { lastUsedAt: new Date() } })
    .catch(() => {});
  return apiKey;
}
