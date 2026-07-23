import db from '../db.js';
import { config } from '../config.js';

let cache = null;
let cacheAt = 0;

async function all() {
  if (cache && Date.now() - cacheAt < 30_000) return cache;
  const rows = await db('settings').select('key', 'value');
  cache = Object.fromEntries(rows.map((r) => [r.key, r.value === null ? null : JSON.parse(r.value)]));
  cacheAt = Date.now();
  return cache;
}

export const Settings = {
  async get(key, fallback = null) {
    const map = await all();
    return key in map ? map[key] : fallback;
  },
  async set(key, value, grouping = 'general', userId = null) {
    const payload = { value: JSON.stringify(value ?? null), grouping, updated_by: userId, updated_at: db.fn.now() };
    const updated = await db('settings').where({ key }).update(payload);
    if (!updated) await db('settings').insert({ key, ...payload });
    cache = null;
  },
  /** Brand values: DB settings override config (which reads env). */
  async brand(key) {
    return (await this.get(`brand.${key}`)) ?? config.brand[key] ?? '';
  },
  async brandAll() {
    const out = {};
    for (const key of Object.keys(config.brand)) out[key] = await this.brand(key);
    return out;
  },
  clearCache() { cache = null; },
};
export default Settings;
