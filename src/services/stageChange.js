import db from '../db.js';
import { bus } from '../events.js';

export async function changeStage(caseId, toStageId, userId = null, reason = null, chainDepth = 0) {
  const caseRow = await db('cases').where({ id: caseId }).first();
  const to = await db('pipeline_stages').where({ id: toStageId }).first();
  if (!caseRow || !to || caseRow.pipeline_stage_id === to.id) return;

  const from = await db('pipeline_stages').where({ id: caseRow.pipeline_stage_id }).first();

  await db('cases').where({ id: caseId }).update({
    pipeline_stage_id: to.id,
    closed_at: to.is_closed ? new Date() : null,
    last_activity_at: new Date(),
    updated_at: new Date(),
  });

  await db('stage_transitions').insert({
    case_id: caseId, from_stage_id: from?.id ?? null, to_stage_id: to.id,
    user_id: userId, reason, created_at: new Date(),
  });

  await bus.emitDomain('case.stage_changed', {
    caseId,
    context: { from_stage: from?.key ?? null, to_stage: to.key, chain_depth: chainDepth },
  });
}
