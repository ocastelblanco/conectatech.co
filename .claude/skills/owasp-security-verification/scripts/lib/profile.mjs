/**
 * Project profile: what was detected, what the user confirmed, and the exceptions
 * they accepted. Lives at <project>/.owasp/profile.json and is meant to be committed.
 */

import { existsSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join, basename as pbasename } from 'node:path';
import { OUTPUT_DIR, matchAny } from './files.mjs';

export const profilePath = (root) => join(root, OUTPUT_DIR, 'profile.json');

export function loadProfile(root) {
  const p = profilePath(root);
  if (!existsSync(p)) return null;
  return JSON.parse(readFileSync(p, 'utf8'));
}

/** Builds a profile from a detection result (detect-stack.mjs). */
export function profileFromDetection(det, overrides = {}) {
  return {
    schema: 1,
    project: pbasename(det.root),
    confirmed: false,
    level: det.level.suggested,
    signals: Object.fromEntries(Object.entries(det.level.signals).map(([k, v]) => [k, v.length > 0])),
    signal_evidence: det.level.signals,
    facets: Object.keys(det.facets),
    facets_disabled: [],
    exclude_paths: [],
    accepted: [],
    live: { urls: [], aws: null },
    ...overrides,
  };
}

export function saveProfile(root, profile, outDir) {
  const dir = outDir || join(root, OUTPUT_DIR);
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, 'profile.json'), JSON.stringify(profile, null, 2) + '\n');
}

/**
 * An accepted exception silences one check on matching paths until it expires:
 * { check: "CTR-02", paths: ["docker-compose.dev.yml"], reason: "...", expires: "2027-01-31" }
 */
export function acceptedFor(profile, checkId, file, today = new Date().toISOString().slice(0, 10)) {
  return (profile?.accepted || []).find((a) =>
    a.check === checkId && (!a.expires || a.expires >= today) && (!a.paths?.length || (file && matchAny(file, a.paths))));
}
