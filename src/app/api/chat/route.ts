import { NextRequest } from "next/server";
import { db } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { streamChatCompletion, ChatMessage } from "@/lib/ai";
import { checkQuota, recordUsage, estimateTokens } from "@/lib/usage";
import { getPlan, DEFAULT_MODEL } from "@/lib/plans";

export const maxDuration = 60;

const SYSTEM_PROMPT =
  "You are a helpful, concise AI assistant for a business team. Format answers in Markdown.";

export async function POST(req: NextRequest) {
  const user = await getCurrentUser();
  if (!user) return Response.json({ error: "Unauthorized" }, { status: 401 });

  const body = await req.json().catch(() => null);
  const text = String(body?.message ?? "").trim();
  if (!text) return Response.json({ error: "Message is required." }, { status: 400 });

  const plan = getPlan(user.org.plan);
  const model = plan.models.includes(body?.model) ? body.model : DEFAULT_MODEL;

  const quota = await checkQuota(user.orgId, user.org.plan);
  if (quota.exceeded) {
    return Response.json(
      {
        error: `Your workspace has used its monthly token quota (${quota.limit.toLocaleString()} tokens on the ${plan.name} plan). Upgrade in Settings to continue.`,
      },
      { status: 402 }
    );
  }

  // Find or create the conversation.
  let conversationId: string | null = body?.conversationId ?? null;
  if (conversationId) {
    const conv = await db.conversation.findUnique({ where: { id: conversationId } });
    if (!conv || conv.orgId !== user.orgId) {
      return Response.json({ error: "Conversation not found." }, { status: 404 });
    }
  } else {
    const conv = await db.conversation.create({
      data: {
        title: text.slice(0, 60),
        model,
        orgId: user.orgId,
        userId: user.id,
      },
    });
    conversationId = conv.id;
  }

  await db.message.create({
    data: { conversationId, role: "user", content: text },
  });

  const history = await db.message.findMany({
    where: { conversationId },
    orderBy: { createdAt: "asc" },
    take: 40,
  });
  const messages: ChatMessage[] = [
    { role: "system", content: SYSTEM_PROMPT },
    ...history.map((m) => ({
      role: m.role as ChatMessage["role"],
      content: m.content,
    })),
  ];

  const completion = await streamChatCompletion(model, messages);
  const encoder = new TextEncoder();
  const convId = conversationId;

  const stream = new ReadableStream<Uint8Array>({
    async start(controller) {
      let assistantText = "";
      try {
        for await (const delta of completion.stream) {
          assistantText += delta;
          controller.enqueue(encoder.encode(delta));
        }
      } catch (err) {
        controller.enqueue(
          encoder.encode("\n\n_[The response was interrupted by a provider error.]_")
        );
        console.error("chat stream error", err);
      }

      const usage = completion.getUsage();
      const promptTokens =
        usage.promptTokens ||
        messages.reduce((n, m) => n + estimateTokens(m.content), 0);
      const completionTokens = usage.completionTokens || estimateTokens(assistantText);

      await db.message.create({
        data: {
          conversationId: convId,
          role: "assistant",
          content: assistantText,
          promptTokens,
          completionTokens,
        },
      });
      await db.conversation.update({
        where: { id: convId },
        data: { updatedAt: new Date() },
      });
      await recordUsage({
        orgId: user.orgId,
        userId: user.id,
        model,
        promptTokens,
        completionTokens,
        source: "WEB",
      });
      controller.close();
    },
  });

  return new Response(stream, {
    headers: {
      "Content-Type": "text/plain; charset=utf-8",
      "X-Conversation-Id": conversationId,
      "Cache-Control": "no-store",
    },
  });
}
