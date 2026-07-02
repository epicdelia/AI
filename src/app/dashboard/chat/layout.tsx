import { getCurrentUser } from "@/lib/auth";
import { db } from "@/lib/db";
import ConversationList from "@/components/conversation-list";

export default async function ChatLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const user = await getCurrentUser();
  if (!user) return null;
  const conversations = await db.conversation.findMany({
    where: { orgId: user.orgId, userId: user.id },
    orderBy: { updatedAt: "desc" },
    take: 100,
    select: { id: true, title: true },
  });

  return (
    <div className="flex h-full">
      <ConversationList conversations={conversations} />
      <div className="flex-1 overflow-hidden">{children}</div>
    </div>
  );
}
