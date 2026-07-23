import 'dotenv/config';

const env = (key, fallback = undefined) => process.env[key] ?? fallback;
const bool = (key, fallback = false) => {
  const value = process.env[key];
  return value === undefined ? fallback : ['1', 'true', 'yes'].includes(String(value).toLowerCase());
};

/*
 * "Harborline Claim Services" is a PROVISIONAL working name until the owner
 * and attorney confirm entity-name, trademark, domain, and licensing
 * clearance. Every brand string resolves through this config (overridable at
 * runtime by administrator Settings) so the product can be renamed without a
 * rebuild. See COMPLIANCE-PLACEHOLDERS.md.
 */
export const config = {
  env: env('NODE_ENV', 'production'),
  appUrl: env('APP_URL', 'http://localhost:3000'),
  port: Number(env('PORT', 3000)),
  appKey: env('APP_KEY', ''),

  brand: {
    name: env('BRAND_NAME', 'Harborline Claim Services'),
    legal_name: env('BRAND_LEGAL_NAME', 'Harborline Claim Services LLC'),
    parent_company: env('BRAND_PARENT_COMPANY', 'Northvale Unified Inc.'),
    tagline: env('BRAND_TAGLINE', 'Helping You Navigate the Path to Possible Surplus Funds'),
    case_prefix: env('BRAND_CASE_PREFIX', 'HCS'),
    phone: env('BRAND_PHONE', ''),
    email: env('BRAND_EMAIL', ''),
    address: env('BRAND_ADDRESS', ''),
    primary_state: env('BRAND_PRIMARY_STATE', 'MD'),
  },

  caseNumberFormat: '{PREFIX}-{YEAR}-{STATE}-{COUNTY}-{SEQ:6}',

  disclaimer:
    'Harborline Claim Services is not a government agency, court, trustee, county office, or law firm. '
    + 'The existence, amount, ownership, and availability of possible funds must be independently verified. '
    + 'Recovery is not guaranteed. Legal matters are referred to appropriately licensed attorneys.',

  confidentialityFooter: 'Confidential — prepared by {name}. Contains non-public case information. Do not redistribute.',

  db: {
    connection: env('DB_CONNECTION', 'sqlite'),
    host: env('DB_HOST', '127.0.0.1'),
    port: Number(env('DB_PORT', 5432)),
    database: env('DB_DATABASE', 'harborline'),
    username: env('DB_USERNAME', 'harborline'),
    password: env('DB_PASSWORD', ''),
    sqliteFile: env('DB_SQLITE_FILE', './storage/database.sqlite'),
  },

  mail: {
    host: env('MAIL_HOST', '127.0.0.1'),
    port: Number(env('MAIL_PORT', 587)),
    username: env('MAIL_USERNAME', ''),
    password: env('MAIL_PASSWORD', ''),
    from: env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
    driver: env('MAIL_MAILER', 'log'), // log | smtp
  },

  security: {
    mfaRequiredForStaff: bool('MFA_REQUIRED_FOR_STAFF', true),
    loginAlertsEnabled: bool('LOGIN_ALERTS_ENABLED', true),
    automationGlobalStop: bool('AUTOMATION_GLOBAL_STOP', false),
    sessionLifetimeMinutes: Number(env('SESSION_LIFETIME', 30)),
    maxUploadMb: Number(env('MAX_UPLOAD_MB', 20)),
    allowedUploadMimes: [
      'application/pdf', 'image/jpeg', 'image/png', 'image/heic',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
    sms: {
      enabled: bool('SMS_ENABLED', false),
      driver: env('SMS_DRIVER', 'log'),
      apiUrl: env('SMS_API_URL', ''),
      apiKey: env('SMS_API_KEY', ''),
      fromNumber: env('SMS_FROM_NUMBER', ''),
    },
    virusScan: {
      enabled: bool('VIRUS_SCAN_ENABLED', false),
      command: env('VIRUS_SCAN_COMMAND', 'clamdscan --no-summary'),
    },
  },
};

export default config;
