import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export async function POST(req: NextRequest) {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  if (user.role === "MEMBER") {
    return NextResponse.json(
      { error: "Only admins can rename the workspace." },
      { status: 403 }
    );
  }
  const body = await req.json().catch(() => null);
  const name = String(body?.name ?? "").trim();
  if (!name || name.length > 80) {
    return NextResponse.json(
      { error: "Workspace name must be 1–80 characters." },
      { status: 400 }
    );
  }
  await db.organization.update({ where: { id: user.orgId }, data: { name } });
  return NextResponse.json({ ok: true });
}
