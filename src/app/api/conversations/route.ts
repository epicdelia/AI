import { NextResponse } from "next/server";
import { db } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export async function GET() {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  const conversations = await db.conversation.findMany({
    where: { orgId: user.orgId, userId: user.id },
    orderBy: { updatedAt: "desc" },
    select: { id: true, title: true, model: true, updatedAt: true },
  });
  return NextResponse.json({ conversations });
}
