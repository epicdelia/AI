"use client";

import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useRef, useState } from "react";
import Markdown from "./markdown";

interface UiMessage {
  role: "user" | "assistant";
  content: string;
}

export default function ChatPane({
  conversationId,
  initialMessages,
  models,
  defaultModel,
}: {
  conversationId: string | null;
  initialMessages: UiMessage[];
  models: string[];
  defaultModel: string;
}) {
  const router = useRouter();
  const [messages, setMessages] = useState<UiMessage[]>(initialMessages);
  const [input, setInput] = useState("");
  const [model, setModel] = useState(defaultModel);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const convIdRef = useRef<string | null>(conversationId);
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  async function send(e: FormEvent) {
    e.preventDefault();
    const text = input.trim();
    if (!text || busy) return;
    setError(null);
    setBusy(true);
    setInput("");
    setMessages((m) => [...m, { role: "user", content: text }, { role: "assistant", content: "" }]);

    try {
      const res = await fetch("/api/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message: text, conversationId: convIdRef.current, model }),
      });

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        setMessages((m) => m.slice(0, -2));
        setInput(text);
        setError(data.error ?? "Something went wrong.");
        return;
      }

      const isNew = !convIdRef.current;
      convIdRef.current = res.headers.get("X-Conversation-Id");

      const reader = res.body!.getReader();
      const decoder = new TextDecoder();
      for (;;) {
        const { done, value } = await reader.read();
        if (done) break;
        const chunk = decoder.decode(value, { stream: true });
        setMessages((m) => {
          const next = [...m];
          next[next.length - 1] = {
            role: "assistant",
            content: next[next.length - 1].content + chunk,
          };
          return next;
        });
      }

      if (isNew && convIdRef.current) {
        window.history.replaceState(null, "", `/dashboard/chat/${convIdRef.current}`);
      }
      router.refresh(); // refresh sidebar titles/order
    } catch {
      setError("Network error — please try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex h-full flex-col">
      <div className="flex-1 overflow-y-auto">
        <div className="mx-auto max-w-3xl space-y-6 px-4 py-8">
          {messages.length === 0 && (
            <div className="pt-24 text-center">
              <h2 className="text-2xl font-semibold text-white">How can I help?</h2>
              <p className="mt-2 text-sm text-zinc-500">
                Ask anything — drafts, analysis, code, summaries. Your team&apos;s conversations
                stay in your workspace.
              </p>
            </div>
          )}
          {messages.map((m, i) =>
            m.role === "user" ? (
              <div key={i} className="flex justify-end">
                <div className="max-w-[80%] whitespace-pre-wrap rounded-2xl bg-indigo-500/90 px-4 py-2.5 text-sm text-white">
                  {m.content}
                </div>
              </div>
            ) : (
              <div key={i} className="flex gap-3">
                <div className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-zinc-800 text-xs">
                  ✦
                </div>
                <div className="min-w-0 flex-1 pt-1 text-sm text-zinc-200">
                  {m.content ? (
                    <Markdown>{m.content}</Markdown>
                  ) : busy && i === messages.length - 1 ? (
                    "…"
                  ) : (
                    ""
                  )}
                </div>
              </div>
            )
          )}
          <div ref={bottomRef} />
        </div>
      </div>
      <div className="border-t border-zinc-800 p-4">
        <form onSubmit={send} className="mx-auto max-w-3xl">
          {error && <p className="mb-2 text-sm text-red-400">{error}</p>}
          <div className="flex items-end gap-2 rounded-2xl border border-zinc-700 bg-zinc-900 p-2 focus-within:border-indigo-500">
            <textarea
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter" && !e.shiftKey) {
                  e.preventDefault();
                  e.currentTarget.form?.requestSubmit();
                }
              }}
              rows={1}
              placeholder="Message your assistant…"
              className="max-h-40 min-h-[2.25rem] flex-1 resize-none bg-transparent px-2 py-1.5 text-sm text-white outline-none placeholder:text-zinc-600"
            />
            <select
              value={model}
              onChange={(e) => setModel(e.target.value)}
              className="rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-xs text-zinc-400 outline-none"
            >
              {models.map((m) => (
                <option key={m} value={m}>
                  {m}
                </option>
              ))}
            </select>
            <button
              type="submit"
              disabled={busy || !input.trim()}
              className="rounded-xl bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-400 disabled:opacity-40"
            >
              Send
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
