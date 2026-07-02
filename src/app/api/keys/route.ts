import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { generateApiKey } from "@/lib/api-keys";
import { getPlan } from "@/lib/plans";

export async function GET() {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  const keys = await db.apiKey.findMany({
    where: { orgId: user.orgId, revokedAt: null },
    orderBy: { createdAt: "desc" },
    select: { id: true, name: true, prefix: true, lastUsedAt: true, createdAt: true },
  });
  return NextResponse.json({ keys });
}

export async function POST(req: NextRequest) {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  if (user.role === "MEMBER") {
    return NextResponse.json({ error: "Only admins can create API keys." }, { status: 403 });
  }
  const plan = getPlan(user.org.plan);
  if (!plan.apiAccess) {
    return NextResponse.json(
      { error: `API access requires the Pro plan or above. You're on ${plan.name}.` },
      { status: 402 }
    );
  }
  const body = await req.json().catch(() => null);
  const name = String(body?.name ?? "").trim() || "Default key";
  const { key, prefix, keyHash } = generateApiKey();
  const created = await db.apiKey.create({
    data: { name, prefix, keyHash, orgId: user.orgId },
  });
  // The full key is returned exactly once; only its hash is stored.
  return NextResponse.json({ id: created.id, name, prefix, key });
}
