import knexFactory from 'knex';
import { config } from './config.js';

export function knexConfig(overrides = {}) {
  const driver = overrides.connection ?? config.db.connection;

  const base = {
    migrations: { directory: './src/migrations' },
    seeds: { directory: './src/seeds' },
    useNullAsDefault: true,
  };

  if (driver === 'sqlite' || driver === 'sqlite3') {
    return {
      ...base,
      client: 'better-sqlite3',
      connection: { filename: overrides.filename ?? config.db.sqliteFile },
      pool: { afterCreate: (conn, done) => { conn.pragma('foreign_keys = ON'); done(); } },
    };
  }

  return {
    ...base,
    client: driver === 'mysql' ? 'mysql2' : 'pg',
    connection: {
      host: config.db.host,
      port: config.db.port,
      database: config.db.database,
      user: config.db.username,
      password: config.db.password,
    },
    pool: { min: 0, max: 10 },
  };
}

export const db = knexFactory(knexConfig());
export default db;
