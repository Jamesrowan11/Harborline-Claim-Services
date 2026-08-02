import { buildApp } from "./app.js";
import { config } from "./config.js";
import { log } from "./log.js";

const app = buildApp();
app.listen(config.port, () => {
  log.info("server listening", { port: config.port, env: config.nodeEnv });
});
