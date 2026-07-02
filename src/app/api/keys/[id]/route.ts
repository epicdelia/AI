import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  if (user.role === "MEMBER") {
    return NextResponse.json({ error: "Only admins can revoke API keys." }, { status: 403 });
  }
  const { id } = await params;
  const key = await db.apiKey.findUnique({ where: { id } });
  if (!key || key.orgId !== user.orgId) {
    return NextResponse.json({ error: "Key not found." }, { status: 404 });
  }
  await db.apiKey.update({ where: { id }, data: { revokedAt: new Date() } });
  return NextResponse.json({ ok: true });
}
