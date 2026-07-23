import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    fileParallelism: false, // tests share one sqlite database
    env: {
      NODE_ENV: 'test',
      DB_CONNECTION: 'sqlite',
      DB_SQLITE_FILE: './storage/test.sqlite',
      APP_KEY: 'test-key-test-key-test-key-test-key-1234',
      MAIL_MAILER: 'log',
      APP_URL: 'http://localhost:3000',
    },
    testTimeout: 20000,
    hookTimeout: 30000,
  },
});
