import { defineConfig } from "vitest/config";
import path from "path";

export default defineConfig({
  resolve: {
    alias: { "@": path.resolve(__dirname, "src") },
  },
  test: {
    include: ["tests/**/*.test.ts"],
    globalSetup: ["tests/helpers/global-setup.ts"],
    testTimeout: 30_000,
    hookTimeout: 120_000,
    // Integration tests share one server + database; keep files sequential.
    fileParallelism: false,
    env: {
      DATABASE_URL: "file:./tests/tmp/test.db",
      OPENAI_API_KEY: "", // force demo mode — deterministic, no network
    },
  },
});
