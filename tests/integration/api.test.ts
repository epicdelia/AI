import { afterAll, describe, expect, it } from "vitest";
import { ApiClient, testDb, uniqueEmail } from "../helpers/client";
import { BASE_URL } from "../helpers/constants";

afterAll(async () => {
  await testDb.$disconnect();
});

describe("auth", () => {
  it("signs up a user, creates an org, and sets a session cookie", async () => {
    const client = new ApiClient();
    const { email, orgId } = await client.signup({ orgName: "Signup Co" });

    const me = await testDb.user.findUniqueOrThrow({
      where: { email },
      include: { org: true },
    });
    expect(me.role).toBe("OWNER");
    expect(me.org.id).toBe(orgId);
    expect(me.org.name).toBe("Signup Co");
    expect(me.org.plan).toBe("FREE");

    // Session cookie works.
    const res = await client.get("/api/conversations");
    expect(res.status).toBe(200);
  });

  it("rejects duplicate emails, short passwords, and missing fields", async () => {
    const client = new ApiClient();
    const { email } = await client.signup();

    const dup = await new ApiClient().post("/api/auth/signup", {
      name: "X",
      email,
      password: "password123",
      orgName: "Dup",
    });
    expect(dup.status).toBe(409);

    const short = await new ApiClient().post("/api/auth/signup", {
      name: "X",
      email: uniqueEmail(),
      password: "short",
      orgName: "Short",
    });
    expect(short.status).toBe(400);

    const missing = await new ApiClient().post("/api/auth/signup", { name: "X" });
    expect(missing.status).toBe(400);
  });

  it("logs in with correct credentials only", async () => {
    const { email } = await new ApiClient().signup();

    const bad = await new ApiClient().post("/api/auth/login", {
      email,
      password: "wrong-password",
    });
    expect(bad.status).toBe(401);

    const fresh = new ApiClient();
    const good = await fresh.post("/api/auth/login", {
      email,
      password: "password123",
    });
    expect(good.status).toBe(200);
    expect((await fresh.get("/api/conversations")).status).toBe(200);
  });

  it("logout invalidates the session", async () => {
    const client = new ApiClient();
    await client.signup();
    await client.post("/api/auth/logout");
    const res = await client.get("/api/conversations");
    expect(res.status).toBe(401);
  });

  it("rejects unauthenticated API access", async () => {
    const anon = new ApiClient();
    expect((await anon.get("/api/conversations")).status).toBe(401);
    expect((await anon.post("/api/chat", { message: "hi" })).status).toBe(401);
    expect((await anon.post("/api/keys", { name: "k" })).status).toBe(401);
  });
});

describe("chat", () => {
  it("streams a reply, persists the conversation, and meters usage", async () => {
    const client = new ApiClient();
    const { orgId } = await client.signup();

    const res = await client.post("/api/chat", { message: "Hello integration test" });
    expect(res.status).toBe(200);
    const convId = res.headers.get("x-conversation-id");
    expect(convId).toBeTruthy();
    const text = await res.text();
    expect(text).toContain("Demo mode");

    // Both messages stored.
    const messages = await testDb.message.findMany({
      where: { conversationId: convId! },
      orderBy: { createdAt: "asc" },
    });
    expect(messages.map((m) => m.role)).toEqual(["user", "assistant"]);
    expect(messages[0].content).toBe("Hello integration test");
    expect(messages[1].content).toContain("Demo mode");

    // Usage was recorded for the org.
    const usage = await testDb.usageRecord.findMany({ where: { orgId } });
    expect(usage).toHaveLength(1);
    expect(usage[0].source).toBe("WEB");
    expect(usage[0].promptTokens + usage[0].completionTokens).toBeGreaterThan(0);

    // Conversation shows up in the list with a title from the first message.
    const list = await (await client.get("/api/conversations")).json();
    expect(list.conversations).toHaveLength(1);
    expect(list.conversations[0].title).toBe("Hello integration test");
  });

  it("continues an existing conversation with history", async () => {
    const client = new ApiClient();
    await client.signup();

    const first = await client.post("/api/chat", { message: "first" });
    const convId = first.headers.get("x-conversation-id");
    await first.text();

    const second = await client.post("/api/chat", {
      message: "second",
      conversationId: convId,
    });
    expect(second.headers.get("x-conversation-id")).toBe(convId);
    await second.text();

    const count = await testDb.message.count({ where: { conversationId: convId! } });
    expect(count).toBe(4);
  });

  it("falls back to the default model when the plan does not allow the requested one", async () => {
    const client = new ApiClient();
    await client.signup(); // FREE: only gpt-4o-mini
    const res = await client.post("/api/chat", { message: "hi", model: "gpt-4o" });
    await res.text();
    const convId = res.headers.get("x-conversation-id");
    const conv = await testDb.conversation.findUniqueOrThrow({ where: { id: convId! } });
    expect(conv.model).toBe("gpt-4o-mini");
  });

  it("does not leak conversations across users or workspaces", async () => {
    const alice = new ApiClient();
    await alice.signup();
    const res = await alice.post("/api/chat", { message: "private" });
    const convId = res.headers.get("x-conversation-id");
    await res.text();

    const mallory = new ApiClient();
    await mallory.signup();
    const foreign = await mallory.post("/api/chat", {
      message: "steal",
      conversationId: convId,
    });
    expect(foreign.status).toBe(404);
    expect((await mallory.delete(`/api/conversations/${convId}`)).status).toBe(404);

    const list = await (await mallory.get("/api/conversations")).json();
    expect(list.conversations).toHaveLength(0);
  });

  it("deletes a conversation and its messages", async () => {
    const client = new ApiClient();
    await client.signup();
    const res = await client.post("/api/chat", { message: "to delete" });
    const convId = res.headers.get("x-conversation-id");
    await res.text();

    expect((await client.delete(`/api/conversations/${convId}`)).status).toBe(200);
    expect(await testDb.conversation.findUnique({ where: { id: convId! } })).toBeNull();
    expect(await testDb.message.count({ where: { conversationId: convId! } })).toBe(0);
  });

  it("rejects empty messages", async () => {
    const client = new ApiClient();
    await client.signup();
    expect((await client.post("/api/chat", { message: "   " })).status).toBe(400);
  });
});

describe("plans and api keys", () => {
  it("blocks key creation on the Free plan with 402", async () => {
    const client = new ApiClient();
    await client.signup();
    const res = await client.post("/api/keys", { name: "nope" });
    expect(res.status).toBe(402);
  });

  it("owner can upgrade; key is returned once and stored hashed", async () => {
    const client = new ApiClient();
    const { orgId } = await client.signup();

    expect((await client.post("/api/org/plan", { plan: "PRO" })).status).toBe(200);
    expect((await client.post("/api/org/plan", { plan: "BOGUS" })).status).toBe(400);

    const res = await client.post("/api/keys", { name: "prod" });
    expect(res.status).toBe(200);
    const { key, prefix } = await res.json();
    expect(key).toMatch(/^ak_live_/);

    const stored = await testDb.apiKey.findFirstOrThrow({ where: { orgId } });
    expect(stored.prefix).toBe(prefix);
    expect(stored.keyHash).not.toBe(key);
    expect(stored.keyHash).toMatch(/^[0-9a-f]{64}$/);

    // Listing never returns the raw key.
    const list = await (await client.get("/api/keys")).json();
    expect(JSON.stringify(list)).not.toContain(key.slice(12));
  });

  it("revoked keys stop working", async () => {
    const client = new ApiClient();
    await client.signup();
    await client.post("/api/org/plan", { plan: "PRO" });
    const { id, key } = await (await client.post("/api/keys", { name: "temp" })).json();

    const before = await fetch(`${BASE_URL}/api/v1/chat/completions`, {
      method: "POST",
      headers: { Authorization: `Bearer ${key}`, "Content-Type": "application/json" },
      body: JSON.stringify({ messages: [{ role: "user", content: "hi" }] }),
    });
    expect(before.status).toBe(200);

    expect((await client.delete(`/api/keys/${id}`)).status).toBe(200);

    const after = await fetch(`${BASE_URL}/api/v1/chat/completions`, {
      method: "POST",
      headers: { Authorization: `Bearer ${key}`, "Content-Type": "application/json" },
      body: JSON.stringify({ messages: [{ role: "user", content: "hi" }] }),
    });
    expect(after.status).toBe(401);
  });
});

describe("public v1 API", () => {
  async function proClientWithKey() {
    const client = new ApiClient();
    const ids = await client.signup();
    await client.post("/api/org/plan", { plan: "PRO" });
    const { key } = await (await client.post("/api/keys", { name: "k" })).json();
    return { client, key, ...ids };
  }

  it("rejects missing and invalid keys", async () => {
    const noAuth = await fetch(`${BASE_URL}/api/v1/chat/completions`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ messages: [{ role: "user", content: "hi" }] }),
    });
    expect(noAuth.status).toBe(401);

    const badKey = await fetch(`${BASE_URL}/api/v1/chat/completions`, {
      method: "POST",
      headers: {
        Authorization: "Bearer ak_live_00000000000000000000000000000000000000000000000",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ messages: [{ role: "user", content: "hi" }] }),
    });
    expect(badKey.status).toBe(401);
  });

  it("returns an OpenAI-shaped completion and meters usage against the org", async () => {
    const { key, orgId } = await proClientWithKey();
    const res = await fetch(`${BASE_URL}/api/v1/chat/completions`, {
      method: "POST",
      headers: { Authorization: `Bearer ${key}`, "Content-Type": "application/json" },
      body: JSON.stringify({
        model: "gpt-4o",
        messages: [{ role: "user", content: "ping" }],
      }),
    });
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body.object).toBe("chat.completion");
    expect(body.choices[0].message.role).toBe("assistant");
    expect(body.choices[0].message.content.length).toBeGreaterThan(0);
    expect(body.usage.total_tokens).toBe(
      body.usage.prompt_tokens + body.usage.completion_tokens
    );

    const usage = await testDb.usageRecord.findMany({ where: { orgId, source: "API" } });
    expect(usage).toHaveLength(1);
    expect(usage[0].apiKeyId).not.toBeNull();
  });

  it("streams SSE chunks terminated by [DONE]", async () => {
    const { key } = await proClientWithKey();
    const res = await fetch(`${BASE_URL}/api/v1/chat/completions`, {
      method: "POST",
      headers: { Authorization: `Bearer ${key}`, "Content-Type": "application/json" },
      body: JSON.stringify({
        stream: true,
        messages: [{ role: "user", content: "hi" }],
      }),
    });
    expect(res.status).toBe(200);
    expect(res.headers.get("content-type")).toContain("text/event-stream");
    const text = await res.text();
    expect(text).toContain('"object":"chat.completion.chunk"');
    expect(text.trim().endsWith("data: [DONE]")).toBe(true);
  });

  it("rejects requests without messages", async () => {
    const { key } = await proClientWithKey();
    const res = await fetch(`${BASE_URL}/api/v1/chat/completions`, {
      method: "POST",
      headers: { Authorization: `Bearer ${key}`, "Content-Type": "application/json" },
      body: JSON.stringify({ messages: [] }),
    });
    expect(res.status).toBe(400);
  });
});

describe("quota enforcement", () => {
  it("blocks web chat with 402 once the monthly quota is exhausted", async () => {
    const client = new ApiClient();
    const { orgId, userId } = await client.signup(); // FREE: 100k tokens
    await testDb.usageRecord.create({
      data: {
        orgId,
        userId,
        model: "gpt-4o-mini",
        promptTokens: 100_000,
        completionTokens: 0,
        source: "WEB",
      },
    });
    const res = await client.post("/api/chat", { message: "over quota" });
    expect(res.status).toBe(402);
    const body = await res.json();
    expect(body.error).toContain("quota");
  });

  it("blocks the public API with 429 once the quota is exhausted", async () => {
    const client = new ApiClient();
    const { orgId } = await client.signup();
    await client.post("/api/org/plan", { plan: "PRO" }); // 5M tokens
    const { key } = await (await client.post("/api/keys", { name: "k" })).json();
    await testDb.usageRecord.create({
      data: {
        orgId,
        model: "gpt-4o-mini",
        promptTokens: 5_000_000,
        completionTokens: 0,
        source: "API",
      },
    });
    const res = await fetch(`${BASE_URL}/api/v1/chat/completions`, {
      method: "POST",
      headers: { Authorization: `Bearer ${key}`, "Content-Type": "application/json" },
      body: JSON.stringify({ messages: [{ role: "user", content: "hi" }] }),
    });
    expect(res.status).toBe(429);
  });
});

describe("team management", () => {
  it("full invite lifecycle: invite, accept, role enforcement, removal", async () => {
    const owner = new ApiClient();
    const { orgId } = await owner.signup({ orgName: "Team Co" });

    // Owner invites a member.
    const inviteEmail = uniqueEmail("invitee");
    const inviteRes = await owner.post("/api/team/invite", { email: inviteEmail });
    expect(inviteRes.status).toBe(200);
    const { inviteUrl } = await inviteRes.json();
    const token = inviteUrl.split("/").pop();

    // Invitee accepts and lands in the same org as MEMBER.
    const member = new ApiClient();
    const accept = await member.post("/api/team/accept", {
      token,
      name: "New Member",
      password: "password123",
    });
    expect(accept.status).toBe(200);
    const memberUser = await testDb.user.findUniqueOrThrow({ where: { email: inviteEmail } });
    expect(memberUser.orgId).toBe(orgId);
    expect(memberUser.role).toBe("MEMBER");

    // Token is single-use.
    const reuse = await new ApiClient().post("/api/team/accept", {
      token,
      name: "Copycat",
      password: "password123",
    });
    expect(reuse.status).toBe(410);

    // Members cannot invite, create keys, rename the org, or remove people.
    expect((await member.post("/api/team/invite", { email: uniqueEmail() })).status).toBe(403);
    expect((await member.post("/api/keys", { name: "k" })).status).toBe(403);
    expect((await member.post("/api/org/name", { name: "Hacked" })).status).toBe(403);

    // Members cannot change the plan (owner only).
    expect((await member.post("/api/org/plan", { plan: "PRO" })).status).toBe(403);

    // Owner cannot be removed; owner can remove the member.
    const ownerUser = await testDb.user.findFirstOrThrow({
      where: { orgId, role: "OWNER" },
    });
    expect((await owner.delete(`/api/team/members/${ownerUser.id}`)).status).toBe(400);
    expect((await owner.delete(`/api/team/members/${memberUser.id}`)).status).toBe(200);
    expect(await testDb.user.findUnique({ where: { id: memberUser.id } })).toBeNull();
  });

  it("enforces seat limits on invites", async () => {
    const owner = new ApiClient();
    const { orgId } = await owner.signup(); // FREE: 3 seats
    // Fill the remaining 2 seats directly.
    for (let i = 0; i < 2; i++) {
      await testDb.user.create({
        data: {
          name: `Filler ${i}`,
          email: uniqueEmail("filler"),
          passwordHash: "x",
          role: "MEMBER",
          orgId,
        },
      });
    }
    const res = await owner.post("/api/team/invite", { email: uniqueEmail() });
    expect(res.status).toBe(402);
  });

  it("admins cannot remove users from other orgs", async () => {
    const a = new ApiClient();
    await a.signup();
    const b = new ApiClient();
    const { userId: bUserId } = await b.signup();
    expect((await a.delete(`/api/team/members/${bUserId}`)).status).toBe(404);
  });
});

describe("middleware", () => {
  it("redirects unauthenticated users away from the dashboard", async () => {
    const res = await fetch(`${BASE_URL}/dashboard/chat`, { redirect: "manual" });
    expect(res.status).toBe(307);
    expect(res.headers.get("location")).toContain("/login");
  });

  it("serves the dashboard to authenticated users", async () => {
    const client = new ApiClient();
    await client.signup();
    for (const page of ["/dashboard/chat", "/dashboard/usage", "/dashboard/developers", "/dashboard/settings"]) {
      const res = await client.get(page);
      expect(res.status, page).toBe(200);
    }
  });
});
