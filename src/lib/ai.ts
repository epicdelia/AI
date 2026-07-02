import OpenAI from "openai";
import { estimateTokens } from "./usage";

export interface ChatMessage {
  role: "system" | "user" | "assistant";
  content: string;
}

export interface CompletionResult {
  // Streams text deltas; usage is populated after the stream is consumed.
  stream: AsyncIterable<string>;
  getUsage: () => { promptTokens: number; completionTokens: number };
}

export function isDemoMode() {
  return !process.env.OPENAI_API_KEY;
}

let client: OpenAI | null = null;
function getClient() {
  if (!client) client = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });
  return client;
}

export async function streamChatCompletion(
  model: string,
  messages: ChatMessage[]
): Promise<CompletionResult> {
  if (isDemoMode()) return demoCompletion(messages);

  const openaiStream = await getClient().chat.completions.create({
    model,
    messages,
    stream: true,
    stream_options: { include_usage: true },
  });

  const usage = { promptTokens: 0, completionTokens: 0 };
  async function* iterate() {
    for await (const chunk of openaiStream) {
      const delta = chunk.choices[0]?.delta?.content;
      if (delta) yield delta;
      if (chunk.usage) {
        usage.promptTokens = chunk.usage.prompt_tokens;
        usage.completionTokens = chunk.usage.completion_tokens;
      }
    }
  }
  return { stream: iterate(), getUsage: () => usage };
}

function demoCompletion(messages: ChatMessage[]): CompletionResult {
  const lastUser = [...messages].reverse().find((m) => m.role === "user");
  const reply =
    `**Demo mode** — no \`OPENAI_API_KEY\` is configured, so this is a simulated response.\n\n` +
    `You said: "${(lastUser?.content ?? "").slice(0, 200)}"\n\n` +
    `Once you add an OpenAI API key to \`.env\`, responses here will come from the real model. ` +
    `Everything else — conversations, usage metering, quotas, API keys, and team management — works exactly the same in demo mode.`;
  const usage = {
    promptTokens: messages.reduce((n, m) => n + estimateTokens(m.content), 0),
    completionTokens: estimateTokens(reply),
  };
  async function* iterate() {
    const words = reply.split(/(?<=\s)/);
    for (const word of words) {
      yield word;
      await new Promise((r) => setTimeout(r, 15));
    }
  }
  return { stream: iterate(), getUsage: () => usage };
}
