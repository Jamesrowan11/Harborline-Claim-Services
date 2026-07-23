import crypto from 'node:crypto';
import db from '../db.js';
import { checkOutreach } from '../services/outreachGate.js';
import { renderTemplate } from '../services/templates.js';
import { notifyUser, sendMail } from '../services/mailer.js';
import { enqueue } from '../services/queue.js';
import { changeStage } from '../services/stageChange.js';
import { decrypt } from '../crypto.js';

export const SENSITIVE_ACTIONS = ['send_email', 'send_sms', 'generate_letter', 'send_portal_notification'];

async function resolveUser(identifier) {
  if (!identifier) return null;
  return db('users').where('email', identifier).orWhere('name', identifier).first();
}

async function primaryClaimant(caseRow) {
  if (!caseRow) return null;
  const pivot = await db('case_claimant').where({ case_id: caseRow.id, role: 'primary' }).first();
  return pivot ? db('claimants').where({ id: pivot.claimant_id }).first() : null;
}

/** Executes one automation action; returns a log message. Outbound sends
 *  always pass through the outreach gate. */
export async function executeAction(automation, action, caseRow, lead, event) {
  const params = action.params ?? {};
  const due = (days) => new Date(Date.now() + Number(days ?? 3) * 86400_000);

  switch (action.type) {
    case 'create_task':
      await db('case_tasks').insert({
        case_id: caseRow?.id ?? null, lead_id: lead?.id ?? null,
        title: params.title ?? 'Automation task', description: params.description ?? null,
        priority: params.priority ?? 'normal',
        assigned_to: (await resolveUser(params.assignee))?.id ?? caseRow?.assigned_to ?? null,
        due_at: params.due_in_days !== undefined ? due(params.due_in_days) : null,
      });
      return 'Task created';

    case 'assign_employee': {
      const user = await resolveUser(params.assignee);
      if (!user) return 'Assignee not found; skipped';
      if (caseRow) await db('cases').where({ id: caseRow.id }).update({ assigned_to: user.id });
      if (lead) await db('leads').where({ id: lead.id }).update({ assigned_to: user.id });
      return `Assigned to ${user.name}`;
    }

    case 'change_stage': {
      if (!caseRow) return 'No case; skipped';
      const stage = await db('pipeline_stages').where({ key: params.stage ?? '' }).first();
      if (!stage) return 'Stage not found; skipped';
      await changeStage(caseRow.id, stage.id, null, 'Automation', (event.context.chain_depth ?? 0) + 1);
      return `Stage changed to ${stage.name}`;
    }

    case 'send_internal_notification': {
      const user = (await resolveUser(params.user)) ?? (caseRow?.assigned_to && await db('users').where({ id: caseRow.assigned_to }).first());
      if (!user) return 'No recipient; skipped';
      await notifyUser(user.id, params.message ?? 'Automation notification', '', caseRow?.case_number ?? lead?.lead_number ?? null);
      return `Notified ${user.name}`;
    }

    case 'send_email':
    case 'send_sms': {
      const channel = action.type === 'send_email' ? 'email' : 'sms';
      const template = await db('document_templates').where({ key: params.template ?? '' }).first();
      const claimant = await primaryClaimant(caseRow);
      const gate = await checkOutreach(channel, template, caseRow, lead, claimant);
      if (gate !== true) return `BLOCKED: ${gate}`;

      const rendered = await renderTemplate(template, caseRow, claimant);
      const recipient = channel === 'email'
        ? (claimant ? (await db('email_addresses').where({ emailable_type: 'claimant', emailable_id: claimant.id, opted_out: false }).first())?.email : lead?.email)
        : (claimant ? (await db('phone_numbers').where({ phoneable_type: 'claimant', phoneable_id: claimant.id, sms_capable: true }).first())?.number : lead?.phone);

      const [{ id: communicationId }] = await db('communications').insert({
        case_id: caseRow?.id ?? null, lead_id: lead?.id ?? null, claimant_id: claimant?.id ?? null,
        channel, direction: 'outbound', recipient,
        subject: rendered.subject, body_rendered: rendered.body,
        document_template_id: template.id, template_version: template.version,
        status: 'queued', delivery_method: channel,
      }, ['id']);
      await enqueue('send_communication', { communicationId });
      return `${channel} queued`;
    }

    case 'generate_letter': {
      const template = await db('document_templates').where({ key: params.template ?? '' }).first();
      if (!template || !caseRow) return 'Template or case missing; skipped';
      if (template.approval_status !== 'approved') return 'BLOCKED: letter template is not approved';
      const claimant = await primaryClaimant(caseRow);
      const rendered = await renderTemplate(template, caseRow, claimant);
      await db('communications').insert({
        case_id: caseRow.id, claimant_id: claimant?.id ?? null, channel: 'letter', direction: 'outbound',
        subject: rendered.subject, body_rendered: rendered.body,
        document_template_id: template.id, template_version: template.version, status: 'draft',
      });
      return 'Letter drafted for review';
    }

    case 'request_document':
      if (!caseRow) return 'No case; skipped';
      await db('document_requests').insert({
        case_id: caseRow.id,
        claimant_id: (await primaryClaimant(caseRow))?.id ?? null,
        name: params.name ?? 'Requested document', instructions: params.instructions ?? null,
        due_at: params.due_in_days !== undefined ? due(params.due_in_days) : null,
      });
      return 'Document requested';

    case 'add_note': {
      const target = caseRow ? ['case', caseRow.id] : lead ? ['lead', lead.id] : null;
      if (!target) return 'No target; skipped';
      await db('notes').insert({ notable_type: target[0], notable_id: target[1], body: params.body ?? 'Automation note', is_staff_only: true });
      return 'Note added';
    }

    case 'add_tag': {
      if (!caseRow) return 'No case; skipped';
      const custom = caseRow.custom_fields ? JSON.parse(caseRow.custom_fields) : {};
      custom.tags = [...new Set([...(custom.tags ?? []), String(params.tag ?? '')])];
      await db('cases').where({ id: caseRow.id }).update({ custom_fields: JSON.stringify(custom) });
      return 'Tag added';
    }

    case 'schedule_follow_up':
      return executeAction(automation, { type: 'create_task', params: { title: params.title ?? 'Follow up', due_in_days: params.due_in_days ?? 3 } }, caseRow, lead, event);

    case 'escalate_to_manager': {
      const manager = caseRow?.case_manager_id && await db('users').where({ id: caseRow.case_manager_id }).first();
      if (!manager) return 'No case manager; skipped';
      await notifyUser(manager.id, params.message ?? 'Escalation from automation', '', caseRow.case_number);
      return `Escalated to ${manager.name}`;
    }

    case 'require_compliance_review':
      if (!caseRow) return 'No case; skipped';
      await db('cases').where({ id: caseRow.id }).update({ compliance_hold: true });
      return 'Compliance review required';

    case 'require_attorney_review': {
      if (!caseRow) return 'No case; skipped';
      const stage = await db('pipeline_stages').where({ key: 'attorney_review_required' }).first();
      if (stage) await changeStage(caseRow.id, stage.id, null, 'Attorney review required by automation', 99);
      return 'Attorney review required';
    }

    case 'lock_workflow':
      if (!caseRow) return 'No case; skipped';
      await db('cases').where({ id: caseRow.id }).update({ automation_paused: true });
      return 'Workflow locked';

    case 'add_calendar_item':
      if (!caseRow) return 'No case; skipped';
      await db('deadlines').insert({
        case_id: caseRow.id, name: params.name ?? 'Calendar item',
        due_at: due(params.due_in_days ?? 7), source: 'automation',
      });
      return 'Calendar item added';

    case 'trigger_webhook':
    case 'call_external_api': {
      const endpoint = await db('webhook_endpoints').where({ name: params.endpoint ?? '', active: true }).first();
      if (!endpoint) return 'Webhook endpoint not found or inactive; skipped';
      const payload = { event: event.key, case_number: caseRow?.case_number ?? null, timestamp: new Date().toISOString(), data: params.data ?? {} };
      const signature = crypto.createHmac('sha256', endpoint.secret ? decrypt(endpoint.secret) : '').update(JSON.stringify(payload)).digest('hex');
      await fetch(endpoint.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Signature': signature },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(10_000),
      });
      return 'Webhook delivered';
    }

    case 'create_audit_record':
      await db('audit_events').insert({
        event: 'automation_note', auditable_type: 'case', auditable_id: caseRow?.id ?? null,
        case_id: caseRow?.id ?? null,
        new_values: JSON.stringify({ message: params.message ?? '', automation: automation.name }),
        created_at: new Date(),
      });
      return 'Audit record created';

    case 'close_duplicate': {
      if (!caseRow) return 'No case; skipped';
      const stage = await db('pipeline_stages').where({ key: 'closed_duplicate' }).first();
      if (stage) await changeStage(caseRow.id, stage.id, null, 'Closed as duplicate by automation', 99);
      return 'Closed as duplicate';
    }

    case 'reverify_source':
      if (!caseRow) return 'No case; skipped';
      await db('source_records').where({ case_id: caseRow.id, status: 'current' }).update({ status: 'stale' });
      return executeAction(automation, { type: 'create_task', params: { title: 'Re-verify source records', due_in_days: 3 } }, caseRow, lead, event);

    case 'stop_outreach':
      if (!caseRow) return 'No case; skipped';
      await db('cases').where({ id: caseRow.id }).update({ outreach_approved: false });
      return 'Outreach stopped';

    case 'send_portal_notification': {
      const claimant = await primaryClaimant(caseRow);
      const clientUser = claimant && await db('users').where({ claimant_id: claimant.id, user_type: 'client' }).first();
      if (!clientUser) return 'No portal user; skipped';
      await notifyUser(clientUser.id, params.message ?? 'There is an update on your case.', '', caseRow.case_number);
      return 'Portal notification sent';
    }

    default:
      throw new Error(`Unknown automation action: ${action.type}`);
  }
}
