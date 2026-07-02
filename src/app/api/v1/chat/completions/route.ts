import { NextRequest } from "next/server";
import { verifyApiKey } from "@/lib/api-keys";
import { streamChatCompletion, ChatMessage } from "@/lib/ai";
import { checkQuota, recordUsage, estimateTokens } from "@/lib/usage";
import { getPlan, DEFAULT_MODEL } from "@/lib/plans";

export const maxDuration = 60;

// Public, API-key-authenticated endpoint (OpenAI-compatible request shape).
// POST /api/v1/chat/completions
// Authorization: Bearer ak_live_...
// { "model": "gpt-4o-mini", "messages": [{"role":"user","content":"Hi"}], "stream": false }
export async function POST(req: NextRequest) {
  const auth = req.headers.get("authorization") ?? "";
  const token = auth.startsWith("Bearer ") ? auth.slice(7) : "";
  const apiKey = await verifyApiKey(token);
  if (!apiKey) {
    return Response.json(
      { error: { message: "Invalid or revoked API key.", type: "authentication_error" } },
      { status: 401 }
    );
  }

  const plan = getPlan(apiKey.org.plan);
  if (!plan.apiAccess) {
    return Response.json(
      { error: { message: "API access requires the Pro plan or above.", type: "plan_error" } },
      { status: 402 }
    );
  }

  const quota = await checkQuota(apiKey.orgId, apiKey.org.plan);
  if (quota.exceeded) {
    return Response.json(
      {
        error: {
          message: `Monthly token quota exceeded (${quota.limit.toLocaleString()} tokens on the ${plan.name} plan).`,
          type: "quota_exceeded",
        },
      },
      { status: 429 }
    );
  }

  const body = await req.json().catch(() => null);
  const rawMessages = Array.isArray(body?.messages) ? body.messages : null;
  if (!rawMessages || rawMessages.length === 0) {
    return Response.json(
      { error: { message: "`messages` is required.", type: "invalid_request_error" } },
      { status: 400 }
    );
  }
  const messages: ChatMessage[] = rawMessages
    .filter((m: { role?: string }) => ["system", "user", "assistant"].includes(m?.role ?? ""))
    .map((m: { role: string; content?: unknown }) => ({
      role: m.role as ChatMessage["role"],
      content: String(m.content ?? ""),
    }));
  const model = plan.models.includes(body?.model) ? body.model : DEFAULT_MODEL;

  const completion = await streamChatCompletion(model, messages);

  async function finalize(text: string) {
    const usage = completion.getUsage();
    const promptTokens =
      usage.promptTokens || messages.reduce((n, m) => n + estimateTokens(m.content), 0);
    const completionTokens = usage.completionTokens || estimateTokens(text);
    await recordUsage({
      orgId: apiKey!.orgId,
      apiKeyId: apiKey!.id,
      model,
      promptTokens,
      completionTokens,
      source: "API",
    });
    return { promptTokens, completionTokens };
  }

  if (body?.stream) {
    const encoder = new TextEncoder();
    const stream = new ReadableStream<Uint8Array>({
      async start(controller) {
        let text = "";
        try {
          for await (const delta of completion.stream) {
            text += delta;
            const event = {
              object: "chat.completion.chunk",
              model,
              choices: [{ index: 0, delta: { content: delta }, finish_reason: null }],
            };
            controller.enqueue(encoder.encode(`data: ${JSON.stringify(event)}\n\n`));
          }
        } catch (err) {
          console.error("v1 stream error", err);
        }
        await finalize(text);
        controller.enqueue(encoder.encode("data: [DONE]\n\n"));
        controller.close();
      },
    });
    return new Response(stream, {
      headers: { "Content-Type": "text/event-stream", "Cache-Control": "no-store" },
    });
  }

  let text = "";
  for await (const delta of completion.stream) text += delta;
  const usage = await finalize(text);
  return Response.json({
    object: "chat.completion",
    model,
    choices: [
      {
        index: 0,
        message: { role: "assistant", content: text },
        finish_reason: "stop",
      },
    ],
    usage: {
      prompt_tokens: usage.promptTokens,
      completion_tokens: usage.completionTokens,
      total_tokens: usage.promptTokens + usage.completionTokens,
    },
  });
}
