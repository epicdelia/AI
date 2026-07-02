import { getCurrentUser } from "@/lib/auth";
import { getPlan, DEFAULT_MODEL } from "@/lib/plans";
import ChatPane from "@/components/chat-pane";

export default async function NewChatPage() {
  const user = await getCurrentUser();
  if (!user) return null;
  const plan = getPlan(user.org.plan);
  return (
    <ChatPane
      conversationId={null}
      initialMessages={[]}
      models={plan.models}
      defaultModel={DEFAULT_MODEL}
    />
  );
}
