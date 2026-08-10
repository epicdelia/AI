import path from "path";

export const TEST_PORT = 3199;
export const BASE_URL = `http://localhost:${TEST_PORT}`;
export const TEST_DB_PATH = path.resolve(__dirname, "../tmp/test.db");
export const TEST_DB_URL = `file:${TEST_DB_PATH}`;
