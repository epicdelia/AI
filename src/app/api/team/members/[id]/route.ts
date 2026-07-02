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
    return NextResponse.json({ error: "Only admins can remove teammates." }, { status: 403 });
  }
  const { id } = await params;
  const target = await db.user.findUnique({ where: { id } });
  if (!target || target.orgId !== user.orgId) {
    return NextResponse.json({ error: "Member not found." }, { status: 404 });
  }
  if (target.role === "OWNER") {
    return NextResponse.json({ error: "The workspace owner cannot be removed." }, { status: 400 });
  }
  if (target.id === user.id) {
    return NextResponse.json({ error: "You cannot remove yourself." }, { status: 400 });
  }
  await db.user.delete({ where: { id } });
  return NextResponse.json({ ok: true });
}
