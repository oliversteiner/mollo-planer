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

function parseBlocks(lines, depth, st, parentKind = null) {
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
      if (parentKind !== null && parentKind !== list.node.type) {
        st.errors.push('Gemischte verschachtelte Listen sind nicht erlaubt');
      }
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
    node.content.push({ type: 'listItem', content: parseBlocks(item, depth + 1, st, node.type) });
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
      const end = s.indexOf('`', i + 1);
      if (end === -1) {
        buf += c;
        i += 1;
      } else {
        flush();
        out.push({ type: 'text', text: s.slice(i + 1, end), marks: [...marks(), { type: 'code' }] });
        i = end + 1;
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

/** Liest [Text](URL), auch mit Titel. Null, wenn es kein Link ist. */
function readLink(s, start) {
  const close = s.indexOf(']', start + 1);
  if (close === -1 || s[close + 1] !== '(') return null;
  const end = s.indexOf(')', close + 2);
  if (end === -1) return null;
  const target = s.slice(close + 2, end).trim();
  const titled = target.match(/^(\S+)\s+"[^"]*"$/);
  return {
    label: s.slice(start + 1, close),
    url: titled ? titled[1] : target,
    title: titled !== null,
    end: end + 1,
  };
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

function renderBlocks(blocks, separator = '\n\n') {
  return blocks.map(renderBlock).filter((text) => text !== '').join(separator);
}

function renderBlock(block) {
  switch (block.type) {
    case 'heading':
      return `${'#'.repeat(block.attrs.level)} ${renderInline(block.content)}`;
    case 'paragraph':
      return renderInline(block.content);
    case 'blockquote':
      return renderBlocks(block.content).split('\n').map((line) => (line === '' ? '>' : `> ${line}`)).join('\n');
    case 'bulletList':
      return block.content.map((item) => prefixLines(renderBlocks(item.content, '\n'), '- ', '  ')).join('\n');
    case 'orderedList':
      return block.content
        .map((item, index) => prefixLines(renderBlocks(item.content, '\n'), `${index + 1}. `, '   '))
        .join('\n');
    default:
      return '';
  }
}

function prefixLines(text, first, rest) {
  return text.split('\n').map((line, index) => {
    if (index === 0) return `${first}${line}`;
    return line === '' ? '' : `${rest}${line}`;
  }).join('\n');
}

function renderInline(nodes) {
  return normalizeText(nodes.map((node) => ({ ...node }))).map(renderNode).join('');
}

function renderNode(node) {
  if (node.type === 'hardBreak') return '\\\n';
  const marks = node.marks ?? [];
  const isCode = marks.some((mark) => mark.type === 'code');
  let text = isCode ? `\`${node.text}\`` : escapeText(node.text);
  const bold = marks.some((mark) => mark.type === 'bold');
  const italic = marks.some((mark) => mark.type === 'italic');
  if (bold && italic) text = `***${text}***`;
  else if (bold) text = `**${text}**`;
  else if (italic) text = `*${text}*`;
  const link = marks.find((mark) => mark.type === 'link');
  return link ? `[${text}](${link.attrs.href})` : text;
}

function escapeText(text) {
  return text.replace(/[\\`*_[\]<]/g, (char) => `\\${char}`);
}
