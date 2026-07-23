import nodemailer from 'nodemailer';
import fs from 'node:fs';
import { config } from '../config.js';
import db from '../db.js';

let transport = null;
function getTransport() {
  if (!transport) {
    transport = config.mail.driver === 'smtp'
      ? nodemailer.createTransport({
          host: config.mail.host,
          port: config.mail.port,
          secure: config.mail.port === 465,
          auth: config.mail.username ? { user: config.mail.username, pass: config.mail.password } : undefined,
        })
      : { sendMail: async (message) => {
          fs.appendFileSync('./storage/logs/mail.log', JSON.stringify({ at: new Date().toISOString(), ...message }) + '\n');
          return { messageId: 'log' };
        } };
  }
  return transport;
}

export async function sendMail({ to, subject, text }) {
  const brand = config.brand.name;
  await getTransport().sendMail({ from: `"${brand}" <${config.mail.from}>`, to, subject, text });
}

/** Internal staff notification: DB row + best-effort email. */
export async function notifyUser(userId, subject, body = '', reference = null) {
  if (!userId) return;
  await db('notifications').insert({ user_id: userId, subject, body, reference });
  const user = await db('users').where({ id: userId }).first();
  if (user?.email) {
    try { await sendMail({ to: user.email, subject: reference ? `[${reference}] ${subject}` : subject, text: body || subject }); }
    catch { /* notification email failures never break the flow */ }
  }
}

export async function sendSms(to, body) {
  if (!config.security.sms.enabled) return false;
  // 'log' driver default; 'twilio-compatible' posts to any Twilio-style API.
  // [ATTORNEY REVIEW REQUIRED] TCPA review before enabling in production.
  if (config.security.sms.driver === 'twilio-compatible' && config.security.sms.apiUrl) {
    const response = await fetch(config.security.sms.apiUrl, {
      method: 'POST',
      headers: { Authorization: `Bearer ${config.security.sms.apiKey}`, 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ To: to, From: config.security.sms.fromNumber, Body: body }),
    });
    return response.ok;
  }
  fs.appendFileSync('./storage/logs/sms.log', JSON.stringify({ at: new Date().toISOString(), to, body }) + '\n');
  return true;
}
import fsSync from 'node:fs';
const fsDir = './storage/logs';
if (!fsSync.existsSync(fsDir)) fsSync.mkdirSync(fsDir, { recursive: true });
