/**
 * Format constants of mollo-planer files (see spec/format-v1.md).
 */

/** Allowed task kinds, written as [art] in front of the task title. */
export const KINDS = Object.freeze(['feature', 'bug', 'planning', 'research', 'design', 'blocked']);

/** Maximum length of milestone, todo and task titles. */
export const TITLE_MAX = 160;

/** Section headings that are not milestones and are silently allowed. */
export const PLAIN_SECTIONS = Object.freeze(['Aufbau']);

/** Status of a milestone, written in a line "Status: …" directly below its heading. Default: geplant. */
export const STATUSES = Object.freeze(['geplant', 'veröffentlicht']);
export const DEFAULT_STATUS = 'geplant';
