import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { createSession, hashPassword } from "@/lib/auth";

export async function POST(req: NextRequest) {
  const body = await req.json().catch(() => null);
  const { token, name, password } = body ?? {};
  if (!token || !name || !password) {
    return NextResponse.json({ error: "All fields are required." }, { status: 400 });
  }
  if (typeof password !== "string" || password.length < 8) {
    return NextResponse.json(
      { error: "Password must be at least 8 characters." },
      { status: 400 }
    );
  }

  const invite = await db.invite.findUnique({ where: { token } });
  if (!invite || invite.acceptedAt || invite.expiresAt < new Date()) {
    return NextResponse.json({ error: "This invite is invalid or has expired." }, { status: 410 });
  }
  const existing = await db.user.findUnique({ where: { email: invite.email } });
  if (existing) {
    return NextResponse.json({ error: "An account with that email already exists." }, { status: 409 });
  }

  const user = await db.user.create({
    data: {
      name,
      email: invite.email,
      passwordHash: await hashPassword(password),
      role: invite.role,
      orgId: invite.orgId,
    },
  });
  await db.invite.update({ where: { id: invite.id }, data: { acceptedAt: new Date() } });
  await createSession(user.id);
  return NextResponse.json({ ok: true });
}
