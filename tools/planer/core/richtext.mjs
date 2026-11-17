/**
 * Beschreibungen als Markdown (planer-abgleich §9). Die erlaubte Teilmenge wird
 * in einen Rich-Text-Baum gelesen, der den TipTap-Knoten der App entspricht, und
 * wieder in die kanonische Form geschrieben. Konstrukte außerhalb der Teilmenge
 * sind Fehler (§9.6), Schreibweisen, die nur vereinheitlicht werden (§9.5), sind
 * Hinweise. Keine Abhängigkeiten.
 */

const LIST = /^( {0,3})([-*+]|\d+\.)( +|$)/;
const QUOTE = /^ {0,3}>/;
const HEADING = /^ {0,3}(#{1,6})( +|$)/;
const FENCE = /^ {0,3}```/;
const THEMATIC = /^ {0,3}([-*_] *){3,}$/;
const TABLE = /^ *\|/;
const HTML_LINE = /^ {0,3}<\/?[A-Za-z!]/;
const BLOCK_START = [HEADING, FENCE, QUOTE, LIST, TABLE, HTML_LINE];

/** Liest Markdown in den Baum. Liefert { doc, errors, hints }; Fehler machen den Text ungültig. */
export function parseDescription(text) {
  const state = { errors: [], hints: [] };
  const lines = String(text ?? '').replace(/\r\n?/g, '\n').split('\n');
  const content = parseBlocks(lines, 0, state);
  return { doc: { type: 'doc', content }, errors: unique(state.errors), hints: unique(state.hints) };
}

/** Verstöße gegen die Whitelist (nur Fehler, §9.6). Leere Liste, wenn alles erlaubt ist. */
export function forbiddenMarkdown(text) {
  return parseDescription(text).errors;
}

/** Kanonische Form einer Beschreibung. Ungültiger Text bleibt unverändert (der Validator meldet ihn). */
export function canonicalDescription(text) {
  if (typeof text !== 'string' || text === '') {
    return text;
  }
  const { doc, errors } = parseDescription(text);
  return errors.length > 0 ? text : canonicalMarkdown(doc);
}

/** Schreibt den Baum in die kanonische Form (zwei Leerzeichen bei Listen, `*` für kursiv). */
export function canonicalMarkdown(doc) {
  return renderBlocks(doc.content).replace(/\n+$/, '');
}

function unique(list) {
  return [...new Set(list)];
}

function indentOf(line) {
  return line.length - line.trimStart().length;
}

function isBlockStart(line) {
  return BLOCK_START.some((pattern) => pattern.test(line)) || THEMATIC.test(line);
}

function parseBlocks(lines, depth, st) {
  const blocks = [];
  let i = 0;

  while (i < lines.length) {
    const line = lines[i];

    if (line.trim() === '') {
      i += 1;
    } else if (FENCE.test(line)) {
      st.errors.push('Code-Blöcke sind in Beschreibungen nicht erlaubt');
      i += 1;
      while (i < lines.length && !FENCE.test(lines[i])) i += 1;
      i += 1;
    } else if (THEMATIC.test(line)) {
      st.errors.push('Trennlinien sind in Beschreibungen nicht erlaubt');
      i += 1;
    } else if (HEADING.test(line)) {
      const [, hashes, rest = ''] = line.match(/^ {0,3}(#{1,6}) ?(.*)$/);
      if (hashes.length === 2 || hashes.length === 3) {
        blocks.push({ type: 'heading', attrs: { level: hashes.length }, content: inlineLine(rest.trim(), st) });
      } else {
        st.errors.push('Nur Überschriften der Ebene 2 und 3 sind erlaubt');
      }
      i += 1;
    } else if (TABLE.test(line)) {
      st.errors.push('Tabellen sind in Beschreibungen nicht erlaubt');
      i += 1;
      while (i < lines.length && TABLE.test(lines[i])) i += 1;
    } else if (HTML_LINE.test(line)) {
      st.errors.push('HTML ist in Beschreibungen nicht erlaubt');
      i += 1;
    } else if (QUOTE.test(line)) {
      const inner = [];
      while (i < lines.length && QUOTE.test(lines[i])) {
        inner.push(lines[i].replace(/^ {0,3}> ?/, ''));
        i += 1;
      }
      blocks.push({ type: 'blockquote', content: parseBlocks(inner, depth, st) });
    } else if (LIST.test(line)) {
      const list = parseList(lines, i, depth, st);
      blocks.push(list.node);
      i = list.next;
    } else {
      const paragraph = [];
      while (i < lines.length && lines[i].trim() !== '' && !isBlockStart(lines[i])) {
        paragraph.push(lines[i]);
        i += 1;
      }
      blocks.push({ type: 'paragraph', content: paragraphInline(paragraph, st) });
    }
  }

  return blocks;
}

function parseList(lines, start, depth, st) {
  const ordered = /\d/.test(lines[start].match(LIST)[2]);
  const node = { type: ordered ? 'orderedList' : 'bulletList', content: [] };

  let i = start;
  while (i < lines.length) {
    const match = lines[i].match(LIST);
    if (!match || /\d/.test(match[2]) !== ordered) break;

    if (ordered) {
      const number = Number(match[2].slice(0, -1));
      if (number !== node.content.length + 1) {
        st.hints.push('Nummerierung wird fortlaufend geschrieben (1., 2., …)');
      }
    } else if (match[2] !== '-') {
      st.hints.push('Listenzeichen ist „-“ (wird vereinheitlicht)');
    }

    const width = match[0].length;
    const item = [lines[i].slice(width)];
    i += 1;
    while (i < lines.length) {
      const next = lines[i];
      if (next.trim() === '') {
        const after = nextNonBlank(lines, i);
        if (after !== -1 && indentOf(lines[after]) >= width) {
          item.push('');
          i += 1;
          continue;
        }
        break;
      }
      if (indentOf(next) >= width) {
        item.push(next.slice(width));
        i += 1;
      } else if (!LIST.test(next) && !isBlockStart(next) && item[item.length - 1].trim() !== '') {
        item.push(next.trim());
        i += 1;
      } else {
        break;
      }
    }
    while (item.length > 0 && item[item.length - 1].trim() === '') item.pop();
    node.content.push({ type: 'listItem', content: parseBlocks(item, depth + 1, st) });
  }

  return { node, next: i };
}

function nextNonBlank(lines, from) {
  for (let j = from; j < lines.length; j += 1) {
    if (lines[j].trim() !== '') return j;
  }
  return -1;
}

/** Absatz: Zeilen mit `\` oder zwei Leerzeichen am Ende werden zu harten Umbrüchen, sonst ein Leerzeichen. */
function paragraphInline(lines, st) {
  const content = [];
  let previousHard = false;
  lines.forEach((raw, index) => {
    let line = raw.trim();
    let hard = false;
    if (line.endsWith('\\')) {
      hard = true;
      line = line.slice(0, -1).trimEnd();
    } else if (/ {2}$/.test(raw)) {
      hard = true;
      st.hints.push('Zeilenumbruch mit zwei Leerzeichen (wird zu „\\“)');
    }
    if (index > 0) {
      content.push(previousHard ? { type: 'hardBreak' } : { type: 'text', text: ' ' });
    }
    content.push(...inlineLine(line, st));
    previousHard = hard;
  });
  return normalizeText(content);
}

function inlineLine(text, st) {
  return normalizeText(inline(normalizeUnderscores(text, st), st, new Set()));
}

/** `__fett__` und `_kursiv_` an Wortgrenzen werden zu `**` und `*` (Hinweis, §9.5). */
function normalizeUnderscores(text, st) {
  let result = text.replace(/(^|[\s(])__(?=\S)(.+?)(?<=\S)__(?=$|[\s).,;:!?])/g, (_, before, inner) => {
    st.hints.push('Fett mit Unterstrich (wird zu **)');
    return `${before}**${inner}**`;
  });
  result = result.replace(/(^|[\s(])_(?=\S)([^_]+?)(?<=\S)_(?=$|[\s).,;:!?])/g, (_, before, inner) => {
    st.hints.push('Kursiv mit Unterstrich (wird zu *)');
    return `${before}*${inner}*`;
  });
  return result;
}

const ESCAPABLE = /[\\`*_[\]<>#()+\-.!&~|]/;
const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', '#39': "'" };

/** Inline-Text in Textknoten mit Marks (bold, italic, code, link). `base` sind die Marks der Umgebung. */
function inline(s, st, base) {
  const out = [];
  let buf = '';
  const active = new Set(base);
  const marks = () => [...['bold', 'italic'].filter((mark) => active.has(mark)).map((type) => ({ type }))];
  const flush = () => {
    if (buf !== '') {
      out.push({ type: 'text', text: buf, marks: marks() });
      buf = '';
    }
  };

  let i = 0;
  while (i < s.length) {
    const c = s[i];

    if (c === '\\' && i + 1 < s.length && ESCAPABLE.test(s[i + 1])) {
      buf += s[i + 1];
      i += 2;
    } else if (c === '`') {
      let run = 0;
      while (s[i + run] === '`') run += 1;
      const end = findClosingRun(s, i + run, run);
      if (end === -1) {
        buf += '`'.repeat(run);
        i += run;
      } else {
        flush();
        let code = s.slice(i + run, end).replace(/\n/g, ' ');
        if (code.length > 2 && code.startsWith(' ') && code.endsWith(' ') && code.trim() !== '') code = code.slice(1, -1);
        out.push({ type: 'text', text: code, marks: [...marks(), { type: 'code' }] });
        i = end + run;
      }
    } else if (c === '*') {
      let run = 0;
      while (s[i + run] === '*') run += 1;
      const size = Math.min(run, 3);
      flush();
      if (size === 2 || size === 3) toggle(active, 'bold');
      if (size === 1 || size === 3) toggle(active, 'italic');
      i += size;
    } else if (c === '!' && s[i + 1] === '[') {
      st.errors.push('Bilder sind in Beschreibungen nicht erlaubt');
      i += 1;
    } else if (c === '[') {
      const link = readLink(s, i);
      if (link === null) {
        buf += c;
        i += 1;
      } else {
        flush();
        if (!/^(https?:\/\/|mailto:)/i.test(link.url)) {
          st.errors.push(`Link-Schema nicht erlaubt: ${link.url}`);
        }
        if (link.title) {
          st.hints.push('Linktitel wird entfernt');
        }
        const inner = inline(link.label, st, new Set(active));
        for (const node of inner) {
          out.push({ ...node, marks: [...(node.marks ?? []), { type: 'link', attrs: { href: link.url } }] });
        }
        i = link.end;
      }
    } else if (c === '<' && /^<(?:https?:\/\/|mailto:)[^>\s]+>/i.test(s.slice(i))) {
      const url = s.slice(i + 1, s.indexOf('>', i));
      flush();
      st.hints.push('Autolink <…> wird zu [Text](URL)');
      out.push({ type: 'text', text: url, marks: [...marks(), { type: 'link', attrs: { href: url } }] });
      i = s.indexOf('>', i) + 1;
    } else if (c === '<' && /^<\/?[A-Za-z][^>]*>/.test(s.slice(i))) {
      st.errors.push('HTML ist in Beschreibungen nicht erlaubt');
      i += s.slice(i).match(/^<\/?[A-Za-z][^>]*>/)[0].length;
    } else if (c === '&' && /^&(amp|lt|gt|quot|#39);/.test(s.slice(i))) {
      const entity = s.slice(i).match(/^&(amp|lt|gt|quot|#39);/)[1];
      st.hints.push('HTML-Entität wird zum Zeichen');
      buf += ENTITIES[entity];
      i += entity.length + 2;
    } else if (c === '&' && /^&#(\d{1,7}|[xX][0-9a-fA-F]{1,6});/.test(s.slice(i))) {
      const [whole, code] = s.slice(i).match(/^&#(\d{1,7}|[xX][0-9a-fA-F]{1,6});/);
      const point = /^[xX]/.test(code) ? parseInt(code.slice(1), 16) : Number(code);
      st.hints.push('HTML-Entität wird zum Zeichen');
      buf += point > 0 && point <= 0x10ffff ? String.fromCodePoint(point) : '\uFFFD';
      i += whole.length;
    } else if (c === '~' && s[i + 1] === '~') {
      st.errors.push('Durchgestrichen ist in Beschreibungen nicht erlaubt');
      i += 2;
    } else {
      buf += c;
      i += 1;
    }
  }
  flush();

  if (active.size !== base.size || [...active].some((mark) => !base.has(mark))) {
    st.errors.push('Fett oder Kursiv ist nicht geschlossen');
  }
  return out;
}

function toggle(set, mark) {
  if (set.has(mark)) set.delete(mark);
  else set.add(mark);
}

/** Ende eines Codes mit `size` Backticks ab `from` (nur eine Folge genau dieser Länge), sonst -1. */
function findClosingRun(s, from, size) {
  let j = from;
  while (j < s.length) {
    if (s[j] !== '`') {
      j += 1;
      continue;
    }
    let run = 0;
    while (s[j + run] === '`') run += 1;
    if (run === size) return j;
    j += run;
  }
  return -1;
}

/** Liest [Text](URL), auch mit Titel, Klammern in der Adresse und <URL>. Null, wenn es kein Link ist. */
function readLink(s, start) {
  let close = -1;
  for (let j = start + 1; j < s.length; j += 1) {
    if (s[j] === '\\') j += 1;
    else if (s[j] === ']') {
      close = j;
      break;
    }
  }
  if (close === -1 || s[close + 1] !== '(') return null;

  const target = readDestination(s, close + 2);
  return target === null ? null : { label: s.slice(start + 1, close), ...target };
}

function unescapeBackslashes(text) {
  return text.replace(/\\([\\`*_[\]<>#()+\-.!&~|])/g, '$1');
}

function readDestination(s, from) {
  let j = from;
  while (s[j] === ' ') j += 1;

  let url;
  if (s[j] === '<') {
    const end = s.indexOf('>', j + 1);
    if (end === -1 || s.slice(j + 1, end).includes('\n')) return null;
    url = unescapeBackslashes(s.slice(j + 1, end));
    j = end + 1;
  } else {
    const begin = j;
    let depth = 0;
    while (j < s.length) {
      const ch = s[j];
      if (ch === '\\' && j + 1 < s.length) {
        j += 2;
        continue;
      }
      if (/\s/.test(ch)) break;
      if (ch === '(') depth += 1;
      else if (ch === ')') {
        if (depth === 0) break;
        depth -= 1;
      }
      j += 1;
    }
    url = unescapeBackslashes(s.slice(begin, j));
  }

  while (s[j] === ' ') j += 1;
  const titled = s.slice(j).match(/^"[^"]*"/);
  if (titled) {
    j += titled[0].length;
    while (s[j] === ' ') j += 1;
  }
  return s[j] === ')' ? { url, title: titled !== null, end: j + 1 } : null;
}

/** Gleiche Marks nebeneinander zu einem Textknoten zusammenfassen, Marks in fester Reihenfolge. */
function normalizeText(nodes) {
  const out = [];
  for (const node of nodes) {
    if (node.type === 'text') {
      node.marks = [...(node.marks ?? [])].sort((a, b) => MARK_ORDER.indexOf(a.type) - MARK_ORDER.indexOf(b.type));
    }
    const last = out[out.length - 1];
    if (node.type === 'text' && last && last.type === 'text' && JSON.stringify(last.marks) === JSON.stringify(node.marks)) {
      last.text += node.text;
    } else {
      out.push(node);
    }
  }
  return out;
}

const MARK_ORDER = ['bold', 'italic', 'code', 'link'];

/* ---- Schreiben ---- */

const LIST_TYPES = ['bulletList', 'orderedList'];

function renderBlocks(blocks, inItem = false) {
  let out = '';
  let previous = null;
  for (const block of blocks) {
    const text = renderBlock(block);
    if (text === '') continue;
    if (previous !== null) out += inItem && joinsTight(previous, block) ? '\n' : '\n\n';
    out += text;
    previous = block;
  }
  return out;
}

/** In einem Listenpunkt braucht eine Liste direkt nach Absatz oder Überschrift keine Leerzeile. */
function joinsTight(previous, next) {
  return LIST_TYPES.includes(next.type) && ['paragraph', 'heading'].includes(previous.type);
}

function renderBlock(block) {
  switch (block.type) {
    case 'heading': {
      const text = renderInline(block.content ?? [], true);
      return text === '' ? '' : `${'#'.repeat(Math.max(2, Math.min(3, block.attrs?.level ?? 2)))} ${text}`;
    }
    case 'paragraph':
      return renderInline(block.content ?? []);
    case 'blockquote': {
      const inner = renderBlocks(block.content ?? []);
      return inner === '' ? '' : inner.split('\n').map((line) => (line === '' ? '>' : `> ${line}`)).join('\n');
    }
    case 'bulletList':
      return renderItems(block, () => '- ');
    case 'orderedList':
      return renderItems(block, (index) => `${index + 1}. `);
    default:
      return '';
  }
}

/** Fortsetzungen eines Punkts sind so weit eingerückt wie sein Listenzeichen (`1. ` 3, `10. ` 4 Zeichen). */
function renderItems(list, marker) {
  const lines = [];
  let written = 0;
  for (const item of list.content ?? []) {
    const text = renderBlocks(item.content ?? [], true);
    if (text === '') continue;
    const first = marker(written);
    written += 1;
    const rest = ' '.repeat(first.length);
    text.split('\n').forEach((line, n) => {
      lines.push(n === 0 ? `${first}${line}` : line === '' ? '' : `${rest}${line}`);
    });
  }
  return lines.join('\n');
}

/**
 * Fett und Kursiv werden nur dort geöffnet und geschlossen, wo sie wechseln (`**a*b***`); ein Link
 * umfasst die Texte mit derselben Adresse. Überlappen Fett und Kursiv ohne Verschachtelung und
 * ohne Zwischenraum, ist das mit CommonMark nicht lesbar (`****`).
 */
function renderInline(nodes, heading = false) {
  const prepared = prepareInline(nodes);
  const state = { open: [], href: null };
  let out = '';
  let atLineStart = true;

  prepared.forEach((node, index) => {
    if (node.type === 'hardBreak') {
      out += closeAll(state) + (heading ? ' ' : '\\\n');
      atLineStart = true;
      return;
    }

    const marks = Object.fromEntries((node.marks ?? []).map((mark) => [mark.type, mark]));
    const href = marks.link ? marks.link.attrs.href : null;
    if (href !== state.href) {
      out += closeAll(state);
      if (href !== null) out += '[';
      state.href = href;
    }
    out += changeMarks(state, ['bold', 'italic'].filter((mark) => marks[mark]));

    const next = (prepared[index + 1]?.marks ?? []).map((mark) => mark.type);
    const plain = !node.marks;
    out += renderContent(node.text, Boolean(marks.code), atLineStart && plain, heading && index === prepared.length - 1 && plain, plain && next.includes('code'), plain && next.includes('link'));
    atLineStart = false;
  });

  return out + closeAll(state);
}

const DELIMITER = { bold: '**', italic: '*' };

function closeAll(state) {
  let out = [...state.open].reverse().map((mark) => DELIMITER[mark]).join('');
  state.open = [];
  if (state.href !== null) {
    out += `](${renderDestination(state.href)})`;
    state.href = null;
  }
  return out;
}

/** Schließt, was nicht mehr gilt (und was danach geöffnet wurde, das wieder geöffnet wird), dann öffnet es Neues. */
function changeMarks(state, wanted) {
  let out = '';
  const cut = state.open.findIndex((mark) => !wanted.includes(mark));
  if (cut !== -1) {
    const closed = state.open.splice(cut);
    out += [...closed].reverse().map((mark) => DELIMITER[mark]).join('');
    for (const mark of closed) {
      if (wanted.includes(mark)) {
        out += DELIMITER[mark];
        state.open.push(mark);
      }
    }
  }
  for (const mark of wanted) {
    if (!state.open.includes(mark)) {
      out += DELIMITER[mark];
      state.open.push(mark);
    }
  }
  return out;
}

function sortedMarks(marks) {
  const byType = new Map();
  for (const mark of marks ?? []) {
    if (MARK_ORDER.includes(mark?.type)) {
      byType.set(mark.type, mark.type === 'link' ? { type: 'link', attrs: { href: String(mark.attrs?.href ?? '') } } : { type: mark.type });
    }
  }
  return MARK_ORDER.filter((type) => byType.has(type)).map((type) => byType.get(type));
}

/**
 * Nur Textknoten, Marks in fester Reihenfolge; Leerzeichen am Rand eines Textes mit Marks
 * werden ohne Marks geschrieben (`**a** b`, nicht `**a **b`), Leerzeichen am Zeilenrand entfallen.
 */
function prepareInline(nodes) {
  const split = [];
  for (const node of nodes) {
    if (node.type === 'hardBreak') {
      split.push({ type: 'hardBreak' });
      continue;
    }
    if (node.type !== 'text' || typeof node.text !== 'string' || node.text === '') continue;

    const text = node.text.replace(/\r\n|[\r\n\t]/g, ' ');
    const marks = sortedMarks(node.marks);
    if (marks.length === 0) {
      split.push({ type: 'text', text });
      continue;
    }
    const [, before, core, after] = text.match(/^([ \p{Zs}]*)([\s\S]*?)([ \p{Zs}]*)$/u);
    if (core === '') {
      split.push({ type: 'text', text });
      continue;
    }
    for (const [piece, pieceMarks] of [[before, []], [core, marks], [after, []]]) {
      if (piece !== '') split.push(pieceMarks.length === 0 ? { type: 'text', text: piece } : { type: 'text', text: piece, marks: pieceMarks });
    }
  }

  const joined = [];
  for (const node of split) {
    const last = joined[joined.length - 1];
    if (last && node.type === 'text' && last.type === 'text' && JSON.stringify(last.marks ?? []) === JSON.stringify(node.marks ?? [])) {
      last.text += node.text;
    } else {
      joined.push({ ...node });
    }
  }
  return trimLines(joined);
}

/** Leerzeichen ohne Marks am Anfang und Ende des Absatzes und neben einem Zeilenumbruch entfallen. */
function trimLines(nodes) {
  const trimmed = [];
  let lineStart = true;
  for (const node of nodes) {
    if (node.type === 'hardBreak') {
      trimmed.push(node);
      lineStart = true;
      continue;
    }
    if (lineStart && !node.marks) node.text = node.text.replace(/^ +/, '');
    if (node.text !== '') {
      trimmed.push(node);
      lineStart = false;
    }
  }
  while (trimmed.length > 0 && trimmed[trimmed.length - 1].type === 'hardBreak') trimmed.pop();
  for (let i = trimmed.length - 1; i >= 0; i -= 1) {
    const isEdge = i === trimmed.length - 1 || trimmed[i + 1].type === 'hardBreak';
    if (trimmed[i].type === 'text' && !trimmed[i].marks && isEdge) {
      trimmed[i].text = trimmed[i].text.replace(/ +$/, '');
      if (trimmed[i].text === '') trimmed.splice(i, 1);
    }
  }
  return trimmed;
}

function renderContent(text, isCode, atLineStart, endsHeading, beforeCode, beforeLink) {
  if (isCode) return renderCode(text);
  let out = escapeText(text, atLineStart, endsHeading);
  // Ein maskierter Backtick direkt vor dem Code liefe mit dessen Umrandung zusammen.
  if (beforeCode) out = out.replace(/\\`$/, '&#96;');
  // Ein „!“ direkt vor einem Link würde ein Bild.
  if (beforeLink && out.endsWith('!')) out = `${out.slice(0, -1)}\\!`;
  return out;
}

/** Zeichen, die Markdown wären, mit Backslash; am Zeilenanfang auch `#`, `>`, `-`, `1.` u. a. */
function escapeText(text, atLineStart, endsHeading) {
  let out = text.replace(/[\\`*_[\]<]|&(?=#?[A-Za-z0-9]+;)|~(?=~)|(?<=~)~/g, (char) => `\\${char}`);
  if (atLineStart) {
    out = out
      .replace(/^(#{1,6})(?=[ \t]|$)/, '\\$1')
      .replace(/^>/, '\\>')
      .replace(/^([-+])(?=[ \t]|$)/, '\\$1')
      .replace(/^(\d{1,9})([.)])(?=[ \t]|$)/, '$1\\$2')
      .replace(/^([-=]+)(?=[ \t]*$)/, '\\$1')
      .replace(/^(:?-+:?)(?=[ \t]*\|)/, '\\$1')
      .replace(/^\|/, '\\|');
  }
  return endsHeading ? out.replace(/(?<![^ \t])#+$/, '\\$&') : out;
}

/** Inline-Code; enthält der Text Backticks, mit längerer Umrandung (und Leerzeichen, wenn nötig). */
function renderCode(text) {
  const runs = text.match(/`+/g) ?? [];
  const fence = '`'.repeat(Math.max(0, ...runs.map((run) => run.length)) + 1);
  const pad = text.startsWith('`') || text.endsWith('`') || (text.startsWith(' ') && text.endsWith(' ') && text.trim() !== '') ? ' ' : '';
  return `${fence}${pad}${text}${pad}${fence}`;
}

/** Eine Adresse mit Leerzeichen, spitzen oder unpaarigen runden Klammern steht in <…>. */
function renderDestination(href) {
  const clean = href.replace(/[\r\n]/g, '');
  let depth = 0;
  let balanced = true;
  for (const char of clean) {
    depth += char === '(' ? 1 : char === ')' ? -1 : 0;
    balanced = balanced && depth >= 0;
  }
  if (clean !== '' && balanced && depth === 0 && !/[\s<>\\]/.test(clean)) return clean;
  return `<${clean.replace(/\\/g, '\\\\').replace(/</g, '%3C').replace(/>/g, '%3E')}>`;
}
