import { NextRequest, NextResponse } from "next/server";
import { randomBytes } from "crypto";
import { db } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { getPlan } from "@/lib/plans";

export async function POST(req: NextRequest) {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  if (user.role === "MEMBER") {
    return NextResponse.json({ error: "Only admins can invite teammates." }, { status: 403 });
  }

  const body = await req.json().catch(() => null);
  const email = String(body?.email ?? "").toLowerCase().trim();
  const role = body?.role === "ADMIN" ? "ADMIN" : "MEMBER";
  if (!email) return NextResponse.json({ error: "Email is required." }, { status: 400 });

  const seats = await db.user.count({ where: { orgId: user.orgId } });
  const plan = getPlan(user.org.plan);
  if (seats >= plan.seatLimit) {
    return NextResponse.json(
      { error: `Your ${plan.name} plan is limited to ${plan.seatLimit} seats. Upgrade to add more.` },
      { status: 402 }
    );
  }

  const existingUser = await db.user.findUnique({ where: { email } });
  if (existingUser) {
    return NextResponse.json({ error: "That user already has an account." }, { status: 409 });
  }

  const invite = await db.invite.create({
    data: {
      email,
      role,
      orgId: user.orgId,
      token: randomBytes(24).toString("hex"),
      expiresAt: new Date(Date.now() + 1000 * 60 * 60 * 24 * 7),
    },
  });

  // In production this link would be emailed; here we return it for copy/paste.
  return NextResponse.json({ ok: true, inviteUrl: `/invite/${invite.token}` });
}
