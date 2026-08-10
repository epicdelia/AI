import { PrismaClient } from "@prisma/client";
import { BASE_URL, TEST_DB_URL } from "./constants";

// Direct database access for test setup/assertions.
export const testDb = new PrismaClient({
  datasources: { db: { url: TEST_DB_URL } },
});

let counter = 0;
export function uniqueEmail(prefix = "user") {
  return `${prefix}-${Date.now()}-${counter++}@test.example`;
}

/** Minimal cookie-jar API client for the integration suite. */
export class ApiClient {
  private cookie: string | null = null;

  async request(path: string, init: RequestInit = {}) {
    const headers = new Headers(init.headers);
    if (init.body && !headers.has("Content-Type")) {
      headers.set("Content-Type", "application/json");
    }
    if (this.cookie) headers.set("Cookie", this.cookie);
    const res = await fetch(`${BASE_URL}${path}`, {
      ...init,
      headers,
      redirect: "manual",
    });
    const setCookie = res.headers.getSetCookie();
    for (const c of setCookie) {
      const [pair] = c.split(";");
      if (pair.startsWith("session=")) {
        this.cookie = pair.split("=")[1] ? pair : null;
      }
    }
    return res;
  }

  get(path: string) {
    return this.request(path);
  }

  post(path: string, body?: unknown) {
    return this.request(path, {
      method: "POST",
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  }

  delete(path: string) {
    return this.request(path, { method: "DELETE" });
  }

  /** Sign up a fresh user+org and return its details. */
  async signup(opts: { orgName?: string; role?: never } = {}) {
    const email = uniqueEmail();
    const res = await this.post("/api/auth/signup", {
      name: "Test User",
      email,
      password: "password123",
      orgName: opts.orgName ?? "Test Org",
    });
    if (res.status !== 200) {
      throw new Error(`signup failed: ${res.status} ${await res.text()}`);
    }
    const user = await testDb.user.findUniqueOrThrow({ where: { email } });
    return { email, userId: user.id, orgId: user.orgId };
  }
}
