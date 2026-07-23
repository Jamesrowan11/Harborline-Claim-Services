import { EventEmitter } from 'node:events';

/**
 * Domain event bus. Every emitted event carries:
 *   { key, caseId?, leadId?, context? }
 * The automation engine subscribes to '*' and matches key against
 * automations.trigger_event.
 */
class DomainBus extends EventEmitter {
  async emitDomain(key, payload = {}) {
    const event = { key, ...payload, context: payload.context ?? {} };
    const handlers = this.listeners('domain');
    for (const handler of handlers) {
      await handler(event); // sequential, awaited — deterministic for tests
    }
    return event;
  }
}

export const bus = new DomainBus();

export const TRIGGERS = [
  'lead.created', 'case.created', 'case.field_changed', 'case.stage_changed',
  'document.uploaded', 'document.approved', 'document.rejected', 'agreement.signed',
  'claim.filed', 'payment.entered', 'deadline.approaching', 'task.overdue',
  'case.inactive', 'source.stale', 'client.message_received', 'email.bounced',
  'sms.opt_out', 'mail.returned', 'attorney.review_requested', 'verification.expired',
  'schedule.tick',
];
