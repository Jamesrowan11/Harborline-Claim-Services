import type { NextFunction, Request, RequestHandler, Response } from "express";

/** Route async errors into the Express error handler (Express 4). */
export function a(
  fn: (req: Request, res: Response, next: NextFunction) => Promise<unknown>
): RequestHandler {
  return (req, res, next) => {
    fn(req, res, next).catch(next);
  };
}

interface PgErrorish {
  code?: string;
}

/**
 * True when an error is a database refusal of a business rule (check
 * violation, unique violation, or a trigger's raise). Those surface to the
 * client as 400s; everything else is a 500.
 */
export function isDbRuleViolation(err: unknown): err is Error {
  const code = (err as PgErrorish)?.code;
  return code === "23514" || code === "23505" || code === "P0001" || code === "42501";
}
