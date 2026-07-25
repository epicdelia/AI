import Link from "next/link";
import { getCurrentUser } from "@/lib/auth";
import { db } from "@/lib/db";
import { getPlan } from "@/lib/plans";

function Code({ children }: { children: string }) {
  return (
    <pre className="mt-2 overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-900 p-4 text-xs leading-relaxed text-zinc-300">
      <code>{children}</code>
    </pre>
  );
}

export default async function DevelopersPage() {
  const user = await getCurrentUser();
  if (!user) return null;
  const plan = getPlan(user.org.plan);
  const keyCount = await db.apiKey.count({
    where: { orgId: user.orgId, revokedAt: null },
  });

  return (
    <div className="h-full overflow-y-auto">
      <div className="mx-auto max-w-3xl space-y-8 px-6 py-10">
        <header>
          <h1 className="text-xl font-semibold text-white">Developers</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Integrate your workspace&apos;s assistant into your own product with the REST API.
          </p>
        </header>

        {!plan.apiAccess && (
          <p className="rounded-lg border border-amber-800 bg-amber-950/40 p-3 text-sm text-amber-300">
            API access is available on the Pro plan and above — upgrade in{" "}
            <Link href="/dashboard/settings" className="underline">
              Settings
            </Link>{" "}
            to use these endpoints.
          </p>
        )}

        <section>
          <h2 className="text-base font-semibold text-white">Authentication</h2>
          <p className="mt-2 text-sm leading-relaxed text-zinc-400">
            All requests are authenticated with a workspace API key in the{" "}
            <code className="rounded bg-zinc-800 px-1 py-0.5 text-xs">Authorization</code>{" "}
            header. Keys are created in{" "}
            <Link href="/dashboard/settings" className="text-indigo-400 hover:underline">
              Settings → API keys
            </Link>{" "}
            ({keyCount} active key{keyCount === 1 ? "" : "s"}). Usage is metered against your
            workspace&apos;s monthly token quota.
          </p>
          <Code>{`Authorization: Bearer ak_live_...`}</Code>
        </section>

        <section>
          <h2 className="text-base font-semibold text-white">Chat completions</h2>
          <p className="mt-2 text-sm leading-relaxed text-zinc-400">
            <code className="rounded bg-zinc-800 px-1 py-0.5 text-xs">
              POST /api/v1/chat/completions
            </code>{" "}
            — the request and response shapes follow the OpenAI Chat Completions format, so
            most OpenAI client libraries work by pointing their base URL at your FluentAI
            deployment.
          </p>
          <Code>{`curl https://your-domain.example/api/v1/chat/completions \\
  -H "Authorization: Bearer ak_live_..." \\
  -H "Content-Type: application/json" \\
  -d '{
    "model": "${plan.models[plan.models.length - 1]}",
    "messages": [
      {"role": "system", "content": "You are a support assistant for Acme."},
      {"role": "user", "content": "How do I reset my password?"}
    ],
    "stream": false
  }'`}</Code>
          <p className="mt-3 text-sm text-zinc-400">Response:</p>
          <Code>{`{
  "object": "chat.completion",
  "model": "${plan.models[plan.models.length - 1]}",
  "choices": [
    {
      "index": 0,
      "message": { "role": "assistant", "content": "..." },
      "finish_reason": "stop"
    }
  ],
  "usage": { "prompt_tokens": 28, "completion_tokens": 112, "total_tokens": 140 }
}`}</Code>
        </section>

        <section>
          <h2 className="text-base font-semibold text-white">Streaming</h2>
          <p className="mt-2 text-sm leading-relaxed text-zinc-400">
            Set <code className="rounded bg-zinc-800 px-1 py-0.5 text-xs">&quot;stream&quot;: true</code>{" "}
            to receive server-sent events, terminated by{" "}
            <code className="rounded bg-zinc-800 px-1 py-0.5 text-xs">data: [DONE]</code>.
          </p>
          <Code>{`data: {"object":"chat.completion.chunk","model":"gpt-4o-mini","choices":[{"index":0,"delta":{"content":"Hel"},"finish_reason":null}]}

data: {"object":"chat.completion.chunk","model":"gpt-4o-mini","choices":[{"index":0,"delta":{"content":"lo!"},"finish_reason":null}]}

data: [DONE]`}</Code>
        </section>

        <section>
          <h2 className="text-base font-semibold text-white">Models & errors</h2>
          <p className="mt-2 text-sm leading-relaxed text-zinc-400">
            Your {plan.name} plan includes: {plan.models.join(", ")}. Requests for other models
            fall back to the default. Errors use standard status codes:
          </p>
          <ul className="mt-3 space-y-1.5 text-sm text-zinc-400">
            <li>
              <code className="rounded bg-zinc-800 px-1 py-0.5 text-xs">401</code> — missing,
              invalid, or revoked API key
            </li>
            <li>
              <code className="rounded bg-zinc-800 px-1 py-0.5 text-xs">402</code> — your plan
              does not include API access
            </li>
            <li>
              <code className="rounded bg-zinc-800 px-1 py-0.5 text-xs">429</code> — monthly
              token quota exceeded
            </li>
            <li>
              <code className="rounded bg-zinc-800 px-1 py-0.5 text-xs">400</code> — malformed
              request (e.g. missing <code className="text-xs">messages</code>)
            </li>
          </ul>
        </section>
      </div>
    </div>
  );
}
