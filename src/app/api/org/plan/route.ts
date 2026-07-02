import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { PLANS, PlanId } from "@/lib/plans";

// Plan changes are self-serve here; in production this is where a Stripe
// Checkout session / customer portal redirect would be created instead.
export async function POST(req: NextRequest) {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  if (user.role !== "OWNER") {
    return NextResponse.json({ error: "Only the workspace owner can change the plan." }, { status: 403 });
  }
  const body = await req.json().catch(() => null);
  const plan = body?.plan as PlanId;
  if (!PLANS[plan]) {
    return NextResponse.json({ error: "Unknown plan." }, { status: 400 });
  }
  await db.organization.update({ where: { id: user.orgId }, data: { plan } });
  return NextResponse.json({ ok: true });
}
