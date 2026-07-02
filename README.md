# FluentAI — B2B AI Workspace (ChatGPT wrapper)

A multi-tenant B2B SaaS application that wraps the OpenAI Chat Completions API with
everything a business needs on top: team workspaces, streaming chat, usage metering
and quotas, plan tiers, role-based team management, and an API-key-protected public
endpoint for product integrations.

Built with **Next.js (App Router) · TypeScript · Tailwind CSS · Prisma + SQLite · OpenAI SDK**.

## Features

- **Workspaces (multi-tenant)** — signup creates an organization; all data is scoped to it.
- **Streaming team chat** — ChatGPT-style UI with conversation history, model picker,
  and token-by-token streaming.
- **Usage metering & quotas** — every request records prompt/completion tokens; monthly
  quotas are enforced per plan, with a usage dashboard (KPI tiles, 30-day daily chart,
  per-model breakdown).
- **Plan tiers** — Free / Pro / Enterprise with different token quotas, seat limits,
  model access, and API availability. Self-serve switching (Stripe-ready seam in
  `src/app/api/org/plan/route.ts`).
- **Team management** — role-based access (owner/admin/member), invite links, seat limits,
  member removal.
- **API keys + public API** — hashed workspace keys and an OpenAI-compatible endpoint:
  `POST /api/v1/chat/completions` (streaming and non-streaming), usage metered per key.
- **Demo mode** — with no `OPENAI_API_KEY` set, chat streams a simulated response so the
  whole product (metering, quotas, keys, teams) is testable without spending tokens.

## Getting started

```bash
npm install
npx prisma db push        # creates prisma/dev.db (SQLite)
cp .env.example .env      # add your OPENAI_API_KEY (optional — demo mode without it)
npm run dev
```

Open http://localhost:3000, create a workspace, and start chatting.

## Public API

Create a key in **Settings → API keys** (Pro plan or above), then:

```bash
curl http://localhost:3000/api/v1/chat/completions \
  -H "Authorization: Bearer ak_live_..." \
  -H "Content-Type: application/json" \
  -d '{"model": "gpt-4o-mini", "messages": [{"role": "user", "content": "Hello"}], "stream": false}'
```

## Architecture

```
src/
  lib/
    db.ts            Prisma client singleton
    auth.ts          cookie sessions, bcrypt passwords, current-user lookup
    plans.ts         plan tiers, quotas, seat limits, model allowlists
    usage.ts         token metering + monthly quota checks
    api-keys.ts      key generation (sha256-hashed at rest) + verification
    ai.ts            OpenAI streaming wrapper + demo mode
  app/
    page.tsx         marketing landing + pricing
    login/ signup/ invite/[token]/
    dashboard/
      chat/          streaming chat UI + conversation history
      usage/         KPI tiles, daily token chart, model breakdown
      settings/      team, API keys, billing plan
    api/
      auth/ chat/ conversations/ keys/ team/ org/
      v1/chat/completions/   public API-key endpoint
  middleware.ts      session-cookie gate for /dashboard
prisma/schema.prisma Organization, User, Session, Conversation, Message,
                     ApiKey, UsageRecord, Invite
```

### Production notes

- Swap SQLite for Postgres by changing `datasource` in `prisma/schema.prisma` and
  `DATABASE_URL`.
- Plan switching is where a Stripe Checkout/portal integration plugs in.
- Invite links are returned to the admin for copy/paste; wire up an email provider to
  send them automatically.
