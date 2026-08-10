import { spawn, execSync, ChildProcess } from "child_process";
import { existsSync, mkdirSync, rmSync } from "fs";
import path from "path";
import { BASE_URL, TEST_DB_URL, TEST_PORT } from "./constants";

const root = path.resolve(__dirname, "../..");
let server: ChildProcess | undefined;

async function waitForServer(url: string, timeoutMs = 30_000) {
  const deadline = Date.now() + timeoutMs;
  for (;;) {
    try {
      const res = await fetch(url);
      if (res.status < 500) return;
    } catch {
      // not up yet
    }
    if (Date.now() > deadline) throw new Error(`Server at ${url} did not start`);
    await new Promise((r) => setTimeout(r, 250));
  }
}

async function assertPortFree() {
  try {
    await fetch(BASE_URL);
  } catch {
    return; // connection refused — port is free
  }
  throw new Error(
    `Port ${TEST_PORT} is already in use (a stale test server?). ` +
      `Kill it before running the suite.`
  );
}

export default async function setup() {
  await assertPortFree();

  // Fresh test database.
  const tmpDir = path.join(root, "tests/tmp");
  rmSync(tmpDir, { recursive: true, force: true });
  mkdirSync(tmpDir, { recursive: true });
  execSync("npx prisma db push --skip-generate", {
    cwd: root,
    env: { ...process.env, DATABASE_URL: TEST_DB_URL },
    stdio: "pipe",
  });

  // The integration suite runs against the production build.
  if (!existsSync(path.join(root, ".next/BUILD_ID"))) {
    execSync("npx next build", { cwd: root, stdio: "inherit" });
  }

  server = spawn("npx", ["next", "start", "-p", String(TEST_PORT)], {
    cwd: root,
    env: { ...process.env, DATABASE_URL: TEST_DB_URL, OPENAI_API_KEY: "" },
    stdio: "ignore",
    detached: true, // own process group, so teardown can kill next-server too
  });
  await waitForServer(BASE_URL);

  return async () => {
    if (server?.pid) {
      try {
        process.kill(-server.pid, "SIGTERM");
      } catch {
        server.kill();
      }
    }
    rmSync(tmpDir, { recursive: true, force: true });
  };
}
