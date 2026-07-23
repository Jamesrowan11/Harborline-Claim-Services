import { describe, it, expect, beforeEach } from 'vitest';
import db from '../src/db.js';
import { createApp } from '../src/app.js';
import { reportRows } from '../src/services/reports.js';
import { freshDb, createCase, createStaff, loginAgent } from './helpers.js';

const app = createApp();

describe('print & export center', () => {
  beforeEach(freshDb);

  it('lists all reports', async () => {
    const staff = await createStaff();
    const agent = await loginAgent(app, staff);
    const page = await agent.get('/portal/print');
    expect(page.status).toBe(200);
    expect(page.text).toContain('Master Case Spreadsheet');
    expect(page.text).toContain('Revenue Spreadsheet');
    expect(page.text).toContain('Audit Report');
  });

  it('exports CSV with headers and rows, logging the export', async () => {
    const staff = await createStaff();
    const agent = await loginAgent(app, staff);
    await createCase({ estimated_surplus: 1234.56, case_number: 'HCS-2026-MD-AA-000501' });

    const response = await agent.get('/portal/print/master_cases/export?format=csv');
    expect(response.status).toBe(200);
    expect(response.text).toContain('Case Number');
    expect(response.text).toContain('HCS-2026-MD-AA-000501');

    expect(await db('export_logs').where({ report_key: 'master_cases', format: 'csv' }).first()).toBeTruthy();
    expect(await db('audit_events').where({ event: 'export_created' }).first()).toBeTruthy();
  });

  it('produces XLSX and PDF downloads', async () => {
    const staff = await createStaff();
    const agent = await loginAgent(app, staff);
    const caseRow = await createCase({ case_number: 'HCS-2026-MD-AA-000502' });
    await db('payments').insert({
      case_id: caseRow.id, payment_type: 'funds_received', amount: 1000.5,
      occurred_on: new Date().toISOString().slice(0, 10),
    });

    const xlsx = await agent.get('/portal/print/payments/export?format=xlsx').buffer().parse((res, cb) => {
      const chunks = []; res.on('data', (c) => chunks.push(c)); res.on('end', () => cb(null, Buffer.concat(chunks)));
    });
    expect(xlsx.status).toBe(200);
    expect(xlsx.headers['content-type']).toContain('spreadsheetml');
    expect(xlsx.body.length).toBeGreaterThan(1000);

    const pdf = await agent.get('/portal/print/payments/export?format=pdf').buffer().parse((res, cb) => {
      const chunks = []; res.on('data', (c) => chunks.push(c)); res.on('end', () => cb(null, Buffer.concat(chunks)));
    });
    expect(pdf.status).toBe(200);
    expect(pdf.headers['content-type']).toContain('pdf');
    expect(pdf.body.subarray(0, 5).toString()).toBe('%PDF-');
  });

  it('computes correct financial rows in the registry', async () => {
    const caseRow = await createCase({ case_number: 'HCS-2026-MD-AA-000503' });
    const today = new Date().toISOString().slice(0, 10);
    await db('payments').insert({ case_id: caseRow.id, payment_type: 'company_fee', amount: 250.25, occurred_on: today });
    await db('payments').insert({ case_id: caseRow.id, payment_type: 'funds_received', amount: 1000, occurred_on: today });

    const rows = await reportRows('revenue');
    expect(rows.length).toBe(1);
    expect(Number(rows[0][2])).toBe(250.25);
  });

  it('blocks users without export permission', async () => {
    const attorney = await createStaff('Attorney');
    const agent = await loginAgent(app, attorney);
    expect((await agent.get('/portal/print')).status).toBe(403);
  });
});
