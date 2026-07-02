import { notFound } from "next/navigation";
import { getCurrentUser } from "@/lib/auth";
import { db } from "@/lib/db";
import { getPlan } from "@/lib/plans";
import ChatPane from "@/components/chat-pane";

export default async function ConversationPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const user = await getCurrentUser();
  if (!user) return null;
  const { id } = await params;
  const conversation = await db.conversation.findUnique({
    where: { id },
    include: { messages: { orderBy: { createdAt: "asc" } } },
  });
  if (!conversation || conversation.orgId !== user.orgId || conversation.userId !== user.id) {
    notFound();
  }
  const plan = getPlan(user.org.plan);

  return (
    <ChatPane
      key={conversation.id}
      conversationId={conversation.id}
      initialMessages={conversation.messages
        .filter((m) => m.role !== "system")
        .map((m) => ({ role: m.role as "user" | "assistant", content: m.content }))}
      models={plan.models}
      defaultModel={
        plan.models.includes(conversation.model) ? conversation.model : plan.models[0]
      }
    />
  );
}
