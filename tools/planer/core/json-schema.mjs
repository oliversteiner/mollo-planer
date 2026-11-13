/**
 * Kleiner JSON-Schema-Prüfer ohne Abhängigkeiten. Er kennt nur die Schlüsselwörter,
 * die spec/planer-2.schema.json (Draft 2020-12) verwendet. Ein unbekanntes
 * Schlüsselwort bricht mit einem Fehler ab, damit eine Schemaänderung nicht
 * stillschweigend falsch geprüft wird.
 */

/** Schlüsselwörter, die nur beschreiben und nichts prüfen. */
const ANNOTATIONS = new Set(['$schema', '$id', '$defs', 'title', 'description']);

/** Schlüsselwörter, die dieser Prüfer auswertet. */
const CHECKED = new Set([
  'type', 'const', 'enum', 'pattern', 'format', 'minLength', 'maxLength', 'minimum',
  'minItems', 'maxItems', 'minProperties', 'required', 'additionalProperties', 'properties', 'items', '$ref', 'anyOf',
]);

/**
 * Prüft einen Wert gegen ein Schema. Gibt eine Liste { path, message } zurück,
 * leer bei Gültigkeit. Der Pfad ist z. B. `milestones[0].todos[1].tasks[0].id`.
 *
 * @param {object} schema  Das Schema (Wurzel, für $ref).
 * @param {unknown} value  Der zu prüfende Wert.
 */
export function validateAgainst(schema, value) {
  const issues = [];
  check(schema, schema, value, '', issues);
  return issues;
}

function check(root, schema, value, path, issues) {
  for (const key of Object.keys(schema)) {
    if (!ANNOTATIONS.has(key) && !CHECKED.has(key)) {
      throw new Error(`Nicht unterstütztes Schlüsselwort „${key}“ im Schema.`);
    }
  }

  if (schema.$ref !== undefined) {
    check(root, resolve(root, schema.$ref), value, path, issues);
  }

  if (schema.type !== undefined) {
    const types = Array.isArray(schema.type) ? schema.type : [schema.type];
    if (!types.some((type) => matchesType(type, value))) {
      issues.push({ path, message: `ist ${describe(value)}, erwartet ${types.join(' oder ')}` });
      return;
    }
  }

  if (schema.const !== undefined && value !== schema.const) {
    issues.push({ path, message: `muss „${schema.const}“ sein` });
  }

  if (schema.enum !== undefined && !schema.enum.includes(value)) {
    issues.push({ path, message: `nicht erlaubt. Erlaubt: ${schema.enum.join(', ')}` });
  }

  if (schema.anyOf !== undefined) {
    const passes = schema.anyOf.some((sub) => {
      const subIssues = [];
      check(root, sub, value, path, subIssues);
      return subIssues.length === 0;
    });
    if (!passes) {
      issues.push({ path, message: 'passt auf keine der erlaubten Formen' });
    }
  }

  if (typeof value === 'string') {
    checkString(schema, value, path, issues);
  }

  if (typeof value === 'number' && schema.minimum !== undefined && value < schema.minimum) {
    issues.push({ path, message: `muss mindestens ${schema.minimum} sein` });
  }

  if (Array.isArray(value)) {
    checkArray(root, schema, value, path, issues);
  }

  if (isObject(value)) {
    checkObject(root, schema, value, path, issues);
  }
}

function checkString(schema, value, path, issues) {
  const length = [...value].length; // Zeichen, nicht UTF-16-Einheiten, wie mb_strlen in Laravel
  if (schema.minLength !== undefined && length < schema.minLength) {
    issues.push({ path, message: `zu kurz (mindestens ${schema.minLength} Zeichen)` });
  }
  if (schema.maxLength !== undefined && length > schema.maxLength) {
    issues.push({ path, message: `zu lang (höchstens ${schema.maxLength} Zeichen)` });
  }
  if (schema.pattern !== undefined && !new RegExp(schema.pattern).test(value)) {
    issues.push({ path, message: `hat ein ungültiges Format (erwartet ${schema.pattern})` });
  }
  if (schema.format === 'date' && !isDate(value)) {
    issues.push({ path, message: 'ist kein gültiges Datum (JJJJ-MM-TT)' });
  }
  if (schema.format === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
    issues.push({ path, message: 'ist keine gültige E-Mail-Adresse' });
  }
  if (schema.format !== undefined && !['date', 'email'].includes(schema.format)) {
    throw new Error(`Nicht unterstütztes Format „${schema.format}“ im Schema.`);
  }
}

function checkArray(root, schema, value, path, issues) {
  if (schema.minItems !== undefined && value.length < schema.minItems) {
    issues.push({ path, message: `braucht mindestens ${schema.minItems} Eintrag/Einträge` });
  }
  if (schema.maxItems !== undefined && value.length > schema.maxItems) {
    issues.push({ path, message: `hat mehr als ${schema.maxItems} Einträge` });
  }
  if (schema.items !== undefined) {
    value.forEach((item, index) => check(root, schema.items, item, `${path}[${index}]`, issues));
  }
}

function checkObject(root, schema, value, path, issues) {
  if (schema.minProperties !== undefined && Object.keys(value).length < schema.minProperties) {
    issues.push({ path, message: `braucht mindestens ${schema.minProperties} Eintrag/Einträge` });
  }
  for (const key of schema.required ?? []) {
    if (!Object.hasOwn(value, key)) {
      issues.push({ path: join(path, key), message: 'fehlt (Pflichtfeld)' });
    }
  }
  if (schema.additionalProperties === false) {
    const known = Object.keys(schema.properties ?? {});
    for (const key of Object.keys(value)) {
      if (!known.includes(key)) {
        issues.push({ path: join(path, key), message: 'ist kein erlaubtes Feld' });
      }
    }
  }
  for (const [key, sub] of Object.entries(schema.properties ?? {})) {
    if (Object.hasOwn(value, key)) {
      check(root, sub, value[key], join(path, key), issues);
    }
  }
}

function resolve(root, ref) {
  if (!ref.startsWith('#/')) {
    throw new Error(`Nur lokale Verweise werden unterstützt: ${ref}`);
  }
  return ref.slice(2).split('/').reduce((node, part) => node[part], root);
}

function matchesType(type, value) {
  switch (type) {
    case 'null': return value === null;
    case 'string': return typeof value === 'string';
    case 'integer': return Number.isInteger(value);
    case 'number': return typeof value === 'number';
    case 'boolean': return typeof value === 'boolean';
    case 'array': return Array.isArray(value);
    case 'object': return isObject(value);
    default: throw new Error(`Unbekannter Typ „${type}“ im Schema.`);
  }
}

function describe(value) {
  if (value === null) return 'null';
  if (Array.isArray(value)) return 'eine Liste';
  if (typeof value === 'number') return Number.isInteger(value) ? 'eine ganze Zahl' : 'eine Zahl';
  return `ein ${typeof value === 'string' ? 'Text' : typeof value === 'object' ? 'Objekt' : typeof value}`;
}

function isObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function isDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const date = new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

function join(path, key) {
  return path === '' ? key : `${path}.${key}`;
}
