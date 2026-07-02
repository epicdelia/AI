"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";

export default function ConversationList({
  conversations,
}: {
  conversations: { id: string; title: string }[];
}) {
  const router = useRouter();
  const params = useParams<{ id?: string }>();

  async function remove(id: string) {
    await fetch(`/api/conversations/${id}`, { method: "DELETE" });
    if (params.id === id) router.push("/dashboard/chat");
    router.refresh();
  }

  return (
    <aside className="flex w-64 shrink-0 flex-col border-r border-zinc-800">
      <div className="p-3">
        <Link
          href="/dashboard/chat"
          className="block w-full rounded-lg bg-indigo-500 px-3 py-2 text-center text-sm font-medium text-white hover:bg-indigo-400"
        >
          + New chat
        </Link>
      </div>
      <div className="flex-1 space-y-0.5 overflow-y-auto px-2 pb-3">
        {conversations.length === 0 && (
          <p className="px-2 pt-2 text-xs text-zinc-600">No conversations yet.</p>
        )}
        {conversations.map((c) => (
          <div
            key={c.id}
            className={`group flex items-center rounded-lg text-sm ${
              params.id === c.id ? "bg-zinc-800 text-white" : "text-zinc-400 hover:bg-zinc-900"
            }`}
          >
            <Link
              href={`/dashboard/chat/${c.id}`}
              className="min-w-0 flex-1 truncate px-3 py-2"
            >
              {c.title}
            </Link>
            <button
              onClick={() => remove(c.id)}
              title="Delete conversation"
              className="hidden pr-2 text-zinc-600 hover:text-red-400 group-hover:block"
            >
              ✕
            </button>
          </div>
        ))}
      </div>
    </aside>
  );
}
