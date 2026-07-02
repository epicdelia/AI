import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { createSession, hashPassword, slugify } from "@/lib/auth";

export async function POST(req: NextRequest) {
  const body = await req.json().catch(() => null);
  const { name, email, password, orgName } = body ?? {};
  if (!name || !email || !password || !orgName) {
    return NextResponse.json({ error: "All fields are required." }, { status: 400 });
  }
  if (typeof password !== "string" || password.length < 8) {
    return NextResponse.json(
      { error: "Password must be at least 8 characters." },
      { status: 400 }
    );
  }
  const normalizedEmail = String(email).toLowerCase().trim();
  const existing = await db.user.findUnique({ where: { email: normalizedEmail } });
  if (existing) {
    return NextResponse.json(
      { error: "An account with that email already exists." },
      { status: 409 }
    );
  }

  const base = slugify(orgName);
  let slug = base;
  for (let i = 2; await db.organization.findUnique({ where: { slug } }); i++) {
    slug = `${base}-${i}`;
  }

  const user = await db.user.create({
    data: {
      name,
      email: normalizedEmail,
      passwordHash: await hashPassword(password),
      role: "OWNER",
      org: { create: { name: orgName, slug } },
    },
  });

  await createSession(user.id);
  return NextResponse.json({ ok: true });
}
