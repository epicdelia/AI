import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  const { id } = await params;
  const conv = await db.conversation.findUnique({ where: { id } });
  if (!conv || conv.orgId !== user.orgId || conv.userId !== user.id) {
    return NextResponse.json({ error: "Conversation not found." }, { status: 404 });
  }
  await db.conversation.delete({ where: { id } });
  return NextResponse.json({ ok: true });
}
