"""Erzeugt docs/briefpapier/briefpapier-anleitung.pdf (Stuart, 2026-11-23).
Aufruf: python3 docs/briefpapier/briefpapier-anleitung.py  (braucht reportlab und die Schrift DejaVu Sans).
Die Masse stehen in V und müssen mit den Vorgaben des Satzspiegels im Planer übereinstimmen.
"""
import os
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer,
                                Table, TableStyle, NextPageTemplate, PageBreak, KeepTogether)
from reportlab.lib.styles import ParagraphStyle

D = os.environ.get('DEJAVU_DIR', '/usr/share/fonts/truetype/dejavu/')
pdfmetrics.registerFont(TTFont('Sans', D + 'DejaVuSans.ttf'))
pdfmetrics.registerFont(TTFont('Sans-Bold', D + 'DejaVuSans-Bold.ttf'))
from reportlab.pdfbase.pdfmetrics import registerFontFamily
registerFontFamily('Sans', normal='Sans', bold='Sans-Bold', italic='Sans', boldItalic='Sans-Bold')

W, H = A4
ACCENT = colors.HexColor('#5b3fa6')
INK = colors.HexColor('#1f1f24')
MUTED = colors.HexColor('#5c5c66')

body = ParagraphStyle('b', fontName='Sans', fontSize=9.5, leading=13.5, textColor=INK, spaceAfter=5)
small = ParagraphStyle('s', parent=body, fontSize=8, leading=11, textColor=MUTED)
h1 = ParagraphStyle('h1', fontName='Sans-Bold', fontSize=20, leading=24, textColor=INK, spaceAfter=4)
sub = ParagraphStyle('sub', parent=body, fontSize=10.5, textColor=MUTED, spaceAfter=14)
h2 = ParagraphStyle('h2', fontName='Sans-Bold', fontSize=12, leading=16, textColor=ACCENT, spaceBefore=10, spaceAfter=5)
bul = ParagraphStyle('bul', parent=body, bulletFontName='Sans', leftIndent=12, bulletIndent=2, spaceAfter=3)
cell = ParagraphStyle('c', parent=body, fontSize=8.5, leading=11.5, spaceAfter=0)
cellb = ParagraphStyle('cb', parent=cell, fontName='Sans-Bold')


def P(t, s=body):
    return Paragraph(t, s)


def B(t):
    return Paragraph(t, bul, bulletText='•')


def table(rows, widths, head=True):
    data = [[Paragraph(str(c), cellb if (head and i == 0) else cell) for c in r] for i, r in enumerate(rows)]
    t = Table(data, colWidths=widths, repeatRows=1 if head else 0)
    st = [('VALIGN', (0, 0), (-1, -1), 'TOP'),
          ('LINEBELOW', (0, 0), (-1, -1), 0.4, colors.HexColor('#d9d6e3')),
          ('TOPPADDING', (0, 0), (-1, -1), 4), ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
          ('LEFTPADDING', (0, 0), (-1, -1), 4), ('RIGHTPADDING', (0, 0), (-1, -1), 4)]
    if head:
        st.append(('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#efeaf9')))
    t.setStyle(TableStyle(st))
    return t


# ---------- values (one place) ----------
# The values the planer offers as defaults are in values.json next to this script (the backend test
# PageLayoutDefaultsTest compares them with App\Core\Pdf\PageLayout::DEFAULTS), so they cannot drift apart.
import json
P = json.load(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'values.json'), encoding='utf-8'))
V = dict(
    head_s1=30, text_top_s1=P['topFirst'], head_f=25, text_top_f=P['topFollowing'], bottom=P['bottom'], foot=20,
    left=P['left'], right=P['right'], safe=5, fold=148.5,
    winL=(15, 33, 99, 62), winL_vis=(15, 47, 80, 34),  # x, y(from top), w, h
)
V['winR'] = (W / mm - V['winL'][0] - V['winL'][2], 33, 99, 62)
V['winR_vis'] = (W / mm - 15 - 80, 47, 80, 34)


# ---------- drawing helpers (y measured from top, in mm) ----------
def rect(c, x, y, w, h, fill, stroke, dash=None, lw=0.6):
    c.saveState()
    if fill:
        c.setFillColor(fill)
    c.setStrokeColor(stroke)
    c.setLineWidth(lw)
    if dash:
        c.setDash(*dash)
    c.rect(x * mm, H - (y + h) * mm, w * mm, h * mm, fill=1 if fill else 0, stroke=1)
    c.restoreState()


def label(c, x, y, text, size=7.5, color=INK, bold=False, anchor='l'):
    c.saveState()
    c.setFillColor(color)
    c.setFont('Sans-Bold' if bold else 'Sans', size)
    lines = text.split('\n')
    for i, ln in enumerate(lines):
        yy = H - (y + i * size * 0.45) * mm
        if anchor == 'c':
            c.drawCentredString(x * mm, yy, ln)
        elif anchor == 'r':
            c.drawRightString(x * mm, yy, ln)
        else:
            c.drawString(x * mm, yy, ln)
    c.restoreState()


def hline(c, y, color, text=None, dash=(3, 2)):
    c.saveState()
    c.setStrokeColor(color)
    c.setLineWidth(0.6)
    c.setDash(*dash)
    c.line(0, H - y * mm, W, H - y * mm)
    c.restoreState()
    if text:
        label(c, 207, y - 1, text, 6.5, color, anchor='r')


def rulers(c):
    c.saveState()
    c.setStrokeColor(colors.HexColor('#9a98a6'))
    c.setFillColor(colors.HexColor('#9a98a6'))
    c.setFont('Sans', 5)
    for x in range(0, 211, 5):
        ln = 4 if x % 10 == 0 else 2
        c.setLineWidth(0.3)
        c.line(x * mm, H, x * mm, H - ln * mm)
        c.line(x * mm, 0, x * mm, ln * mm)
        if x % 20 == 0 and 0 < x < 210:
            c.drawCentredString(x * mm, H - 6.5 * mm, str(x))
    for y in range(0, 298, 5):
        ln = 4 if y % 10 == 0 else 2
        c.line(0, H - y * mm, ln * mm, H - y * mm)
        c.line(W, H - y * mm, W - ln * mm, H - y * mm)
        if y % 20 == 0 and 0 < y < 297:
            c.drawString(4.8 * mm, H - y * mm - 1.6, str(y))
    c.restoreState()


def margins(c, top):
    v = V
    rect(c, v['left'], top, 210 - v['left'] - v['right'], 297 - top - v['bottom'],
         colors.HexColor('#f3f8f1'), colors.HexColor('#5c9a4c'), dash=(2, 2))


def safe_border(c):
    s = V['safe']
    c.saveState()
    c.setStrokeColor(colors.HexColor('#c0392b'))
    c.setLineWidth(0.4)
    c.setDash(1, 2)
    c.rect(s * mm, s * mm, W - 2 * s * mm, H - 2 * s * mm, fill=0, stroke=1)
    c.restoreState()


def title_box(c, t1, t2, y=240):
    rect(c, 22, y, 166, 13, colors.white, colors.HexColor('#cfcadd'), lw=0.5)
    label(c, 25, y + 5.2, t1, 8, ACCENT, bold=True)
    label(c, 25, y + 9.6, t2, 6.5, MUTED)


def page_sheet1(c, doc):
    v = V
    c.saveState()
    rect(c, 0, 0, 210, v['head_s1'], colors.HexColor('#efeaf9'), colors.HexColor('#efeaf9'))
    label(c, 105, 13, 'BRIEFKOPF', 13, ACCENT, bold=True, anchor='c')
    label(c, 105, 19, f'0–{v["head_s1"]} mm: Logo, Firmenname, Gestaltung frei', 7.5, MUTED, anchor='c')
    label(c, 105, 23.5, 'Neben dem Fenster darf die Gestaltung tiefer reichen, das Fenster selbst bleibt weiss.', 6.5, MUTED, anchor='c')
    margins(c, v['text_top_s1'])
    label(c, 105, 170, 'TEXTBEREICH', 12, colors.HexColor('#5c9a4c'), bold=True, anchor='c')
    label(c, 105, 176, f'Rechnung: Titel, Nummer, Datum, Positionen, Total – ab {v["text_top_s1"]} mm', 7.5, MUTED, anchor='c')
    label(c, 105, 180.5, 'Hier nur sehr zurückhaltende Gestaltung (z. B. Wasserzeichen ≤ 5 % Deckung)', 7, MUTED, anchor='c')
    # windows
    x, y, w, h = v['winL']
    rect(c, x, y, w, h, None, colors.HexColor('#2f6fb5'), dash=(3, 2))
    x2, y2, w2, h2 = v['winL_vis']
    rect(c, x2, y2, w2, h2, colors.HexColor('#e3eefa'), colors.HexColor('#2f6fb5'), lw=0.8)
    label(c, x2 + 2, y2 + 4.5, 'ADRESSE (Fenster links)', 7, colors.HexColor('#2f6fb5'), bold=True)
    label(c, x2 + 2, y2 + 9, 'immer sichtbar:\n15–95 mm v. l., 47–81 mm v. o.', 6.5, colors.HexColor('#2f6fb5'))
    x, y, w, h = v['winR']
    rect(c, x, y, w, h, None, colors.HexColor('#c77d1a'), dash=(1.5, 2))
    x2, y2, w2, h2 = v['winR_vis']
    rect(c, x2, y2, w2, h2, colors.HexColor('#fbf1e2'), colors.HexColor('#c77d1a'), lw=0.8)
    label(c, x2 + 2, y2 + 4.5, 'ADRESSE (Fenster rechts)', 7, colors.HexColor('#c77d1a'), bold=True)
    label(c, x2 + 2, y2 + 9, 'gespiegelt – mit eigenem\nCouvert nachmessen!', 6.5, colors.HexColor('#c77d1a'))
    label(c, 20, 98.3, 'Gestrichelt: Fensterbereich inkl. Verrutschen (±14 mm). Dort keine Gestaltung, nur weiss.', 6.3, MUTED)
    # fold, footer
    hline(c, v['fold'], colors.HexColor('#7a7a85'), 'Falzmarke 148,5 mm (A4 einmal gefaltet in C5)')
    c.saveState()
    c.setStrokeColor(INK)
    c.setLineWidth(0.6)
    c.line(3 * mm, H - v['fold'] * mm, 7 * mm, H - v['fold'] * mm)
    c.restoreState()
    rect(c, 0, 297 - v['foot'], 210, v['foot'], colors.HexColor('#efeaf9'), colors.HexColor('#efeaf9'))
    label(c, 105, 297 - v['foot'] + 8, f'FUSSZEILE (unterste {v["foot"]} mm): Adresse, Telefon, E-Mail, Web, IBAN', 7.5, ACCENT, bold=True, anchor='c')
    label(c, 105, 297 - v['foot'] + 12.5, f'Textbereich endet {v["bottom"]} mm über dem unteren Rand', 6.8, MUTED, anchor='c')
    safe_border(c)
    label(c, 6, 296, 'rot gepunktet: 5 mm Rand, den Bürodrucker oft nicht drucken', 5.8, colors.HexColor('#c0392b'))
    rulers(c)
    title_box(c, 'Massblatt Seite 1 · Massstab 1:1 · A4 210 × 297 mm',
              'Als Hilfsebene in dein Grafikprogramm importieren (Ebene ausblenden beim Export!). Alle Werte sind Vorschläge und im Planer einstellbar.')
    c.restoreState()


def page_sheetF(c, doc):
    v = V
    c.saveState()
    rect(c, 0, 0, 210, v['head_f'], colors.HexColor('#efeaf9'), colors.HexColor('#efeaf9'))
    label(c, 105, 12, 'KOPF FOLGESEITEN', 11, ACCENT, bold=True, anchor='c')
    label(c, 105, 17.5, f'0–{v["head_f"]} mm: kleines Logo, schlicht', 7.5, MUTED, anchor='c')
    margins(c, v['text_top_f'])
    label(c, 105, 150, 'TEXTBEREICH FOLGESEITEN', 12, colors.HexColor('#5c9a4c'), bold=True, anchor='c')
    label(c, 105, 156, f'ab {v["text_top_f"]} mm: weitere Positionen, Total', 7.5, MUTED, anchor='c')
    label(c, 105, 160.5, 'Kein Adressfenster, kein grosser Briefkopf', 7, MUTED, anchor='c')
    rect(c, 0, 297 - v['foot'], 210, v['foot'], colors.HexColor('#efeaf9'), colors.HexColor('#efeaf9'))
    label(c, 105, 297 - v['foot'] + 8, 'FUSSZEILE wie Seite 1 (oder leer)', 7.5, ACCENT, bold=True, anchor='c')
    safe_border(c)
    rulers(c)
    title_box(c, 'Massblatt Folgeseiten · Massstab 1:1 · A4',
              'Optional. Ohne eigene Folgeseite nutzt der Planer Seite 1 nur einmal und lässt Folgeseiten weiss.')
    c.restoreState()


def page_sheetQR(c, doc):
    c.saveState()
    rect(c, 0, 0, 210, 192, colors.HexColor('#fafafa'), colors.HexColor('#fafafa'))
    label(c, 105, 40, 'ZAHLTEIL-SEITE (letzte Seite)', 13, ACCENT, bold=True, anchor='c')
    label(c, 105, 47, 'Diese Seite erzeugt der Planer immer OHNE Briefpapier.', 8, INK, anchor='c')
    label(c, 105, 52, 'Du musst hier nichts gestalten – nur wissen, dass sie existiert.', 7.5, MUTED, anchor='c')
    label(c, 105, 90, 'Oberhalb: kurzer Hinweis, z. B. «Zahlbar innert 30 Tagen» (Text vom Planer)', 7.5, MUTED, anchor='c')
    c.saveState()
    c.setStrokeColor(INK)
    c.setLineWidth(0.5)
    c.setDash(2, 2)
    c.line(0, 105 * mm, W, 105 * mm)
    c.line(62 * mm, 0, 62 * mm, 105 * mm)
    c.restoreState()
    label(c, 31, 240, 'EMPFANGSSCHEIN', 8.5, INK, bold=True, anchor='c')
    label(c, 31, 245, '62 × 105 mm', 7.5, MUTED, anchor='c')
    label(c, 136, 240, 'ZAHLTEIL MIT SWISS QR CODE', 8.5, INK, bold=True, anchor='c')
    label(c, 136, 245, '148 × 105 mm', 7.5, MUTED, anchor='c')
    rect(c, 67 + 0, 205, 46, 46, None, INK, lw=0.5)
    label(c, 90, 229, 'QR', 9, MUTED, anchor='c')
    label(c, 105, 188, 'Trennlinien (Perforation/Schere) zeichnet der Planer nach Swiss Payment Standards', 7, MUTED, anchor='c')
    rulers(c)
    title_box(c, 'Zahlteil-Seite · zur Information · A4', y=150, t2=
              'Unterste 105 mm sind nach Norm fix belegt. Keine Farben, kein Logo, keine Hintergründe in diesem Bereich.')
    c.restoreState()


def footer(c, doc):
    c.saveState()
    c.setFont('Sans', 7)
    c.setFillColor(MUTED)
    c.drawString(20 * mm, 12 * mm, 'Mollo Planer · Anleitung Briefpapier (Auftrag 39 F) · Stand 23.11.2026')
    c.drawRightString(190 * mm, 12 * mm, f'Seite {doc.page}')
    c.restoreState()


doc = BaseDocTemplate(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'briefpapier-anleitung.pdf'), pagesize=A4,
                      title='Anleitung Briefpapier für Rechnungen und Dokumente',
                      author='Stuart (Mollo Planer)', leftMargin=20 * mm, rightMargin=20 * mm,
                      topMargin=18 * mm, bottomMargin=20 * mm)
text_frame = Frame(20 * mm, 20 * mm, W - 40 * mm, H - 38 * mm, id='t', showBoundary=0)
dummy = Frame(0, 0, 20, 20, id='d', showBoundary=0, leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
doc.addPageTemplates([
    PageTemplate('text', [text_frame], onPage=footer),
    PageTemplate('s1', [dummy], onPage=page_sheet1),
    PageTemplate('sf', [dummy], onPage=page_sheetF),
    PageTemplate('qr', [dummy], onPage=page_sheetQR),
])

s = []
s += [P('Briefpapier gestalten', h1),
      P('Anleitung für den Briefkopf von Rechnungen und Dokumenten im Mollo Planer. Zum Danebenlegen beim Gestalten.', sub)]

s += [P('So funktioniert es', h2),
      P('Du gestaltest das Briefpapier in deinem Grafikprogramm und lädst es als <b>PDF</b> im Planer hoch '
        '(Systemverwaltung → Firmendaten). Der Planer legt es als <b>Hintergrund</b> unter jede Rechnung und – wenn du es beim Export wählst – '
        'unter Dokumente. Den Text (Adresse, Positionen, Beträge) setzt der Planer darüber, in den Bereich, den du mit Rändern in mm festlegst.'),
      B('<b>Seite 1:</b> Briefkopf voll, mit freiem Adressfeld.'),
      B('<b>Folgeseiten (optional):</b> schlichter, z. B. nur kleines Logo und Fusszeile.'),
      B('<b>Zahlteil-Seite:</b> kommt immer als eigene letzte Seite, <b>ohne</b> Briefpapier. Du musst dafür nichts freihalten.'),
      B('Ein neues Briefpapier gilt nur für <b>neue</b> PDFs. Ausgestellte Rechnungen sind gespeichert und ändern sich nicht.')]

s += [P('Masse auf einen Blick (Vorschläge)', h2),
      table([
          ['Bereich', 'Wert', 'Bedeutung'],
          ['Format', 'A4 hochkant, 210 × 297 mm', 'Genau dieses Format, eine Datei pro Seitenart oder ein PDF mit 2 Seiten.'],
          ['Briefkopf Seite 1', f'0–{V["head_s1"]} mm von oben', 'Logo, Firmenname, Gestaltung frei.'],
          ['Adressfeld, Fenster links', '15–95 mm von links,\n47–81 mm von oben', 'Immer sichtbar im C5-Fenster (aus der Zeichnung der ePost-Vorgaben der Post abgelesen). Ganzer Fensterbereich mit Verrutschen: 15–114 mm, 33–95 mm – dort nur weiss.'],
          ['Adressfeld, Fenster rechts', 'gespiegelt: ca. 115–195 mm von links,\n47–81 mm von oben', '<b>Unsicher – mit deinem Couvert nachmessen</b> (siehe unten).'],
          ['Textbeginn Seite 1', f'{V["text_top_s1"]} mm von oben', 'Unter dem Adressfeld; im Planer einstellbar.'],
          ['Kopf Folgeseiten', f'0–{V["head_f"]} mm', 'Klein und ruhig.'],
          ['Textbeginn Folgeseiten', f'{V["text_top_f"]} mm von oben', ''],
          ['Ränder links / rechts', f'{V["left"]} mm / {V["right"]} mm', 'Text läuft nie näher an den Rand.'],
          ['Fusszeile', f'unterste {V["foot"]} mm', 'Adresse, Telefon, E-Mail, Web, IBAN.'],
          ['Textende unten', f'{V["bottom"]} mm über dem unteren Rand', 'Muss über deiner Fusszeile liegen.'],
          ['Druckfreier Rand', f'{V["safe"]} mm rundum', 'Bürodrucker drucken dort oft nicht. Nichts Wichtiges hineinlegen.'],
          ['Falzmarke (optional)', '148,5 mm von oben, links, 4 mm lang', 'A4 einmal gefaltet in ein C5-Couvert.'],
      ], [38 * mm, 50 * mm, 82 * mm])]

s += [P('Fenster nachmessen (2 Minuten)', h2),
      B('Das Massblatt «Seite 1» (weiter hinten in diesem PDF) in Originalgrösse drucken: «Tatsächliche Grösse», nicht «Anpassen».'),
      B('Gefaltet in dein Couvert stecken, Blatt in alle Richtungen schieben.'),
      B('Notieren, welcher Bereich <b>immer</b> im Fenster sichtbar bleibt. Diese Werte trägst du später im Planer als Adressfeld ein.'),
      P('Die Werte für das linke Fenster sind aus der Zeichnung in den Formatvorgaben von ePost (Post CH) für C5-Fensterkuverts abgelesen. '
        'Für das rechte Fenster habe ich keine gesicherte Quelle gefunden; die Zeichnung ist gespiegelt und nur ein Ausgangspunkt.', small)]


s += [P('Gestaltungsregeln', h2),
      B('<b>Adressfeld weiss lassen</b>, auch im Bereich, in den das Blatt im Couvert verrutschen kann.'),
      B('<b>Textbereich ruhig halten:</b> keine kräftigen Flächen oder Bilder hinter Positionen und Beträgen. Ein dezentes Wasserzeichen geht.'),
      B('<b>Keine Daten ins Briefpapier, die sich ändern</b> (Datum, Rechnungsnummer, Kunde). Das setzt der Planer.'),
      B('<b>IBAN in der Fusszeile</b> ist erlaubt, muss aber mit den Firmendaten im Planer übereinstimmen – der Zahlteil nimmt die IBAN aus dem Planer.'),
      B('Farben: RGB genügt (Bildschirm und Bürodrucker). Für Druckerei-Papier vorher klären.'),
      B('Bilder und Logos: 300 dpi bei Endgrösse, besser Vektor (SVG/AI/EPS eingebettet als Vektor).'),
      B('Datei klein halten: höchstens <b>5 MB</b>.')]

s += [P('Export als PDF – wichtig', h2),
      P('Der Planer liest dein PDF mit mPDF (FPDI). Diese Bibliothek kann neuere, stark komprimierte PDFs oft <b>nicht</b> lesen. Darum:'),
      table([
          ['Einstellung', 'Wert'],
          ['PDF-Version / Standard', '<b>PDF 1.4</b> (Acrobat 5) oder <b>PDF/X-1a</b>'],
          ['Objekt-Komprimierung', 'aus («Objekt-Streams» / «komprimierte Querverweise» deaktivieren), falls angeboten'],
          ['Schriften', 'vollständig einbetten oder in Pfade umwandeln'],
          ['Seitengrösse', 'A4, 210 × 297 mm, <b>ohne</b> Beschnittzugabe und ohne Schnittmarken'],
          ['Ebenen', 'Hilfsebene mit dem Massblatt vor dem Export ausblenden'],
          ['Sicherheit', 'kein Passwort, keine Einschränkungen'],
      ], [55 * mm, 115 * mm]),
      Spacer(1, 4),
      P('Beim Hochladen prüft der Planer Format, Seitenzahl und Lesbarkeit. Bei einem Fehler sagt er, was zu ändern ist.', small)]

s += [P('In gängigen Programmen', h2),
      table([
          ['Programm', 'So exportieren'],
          ['Affinity Designer / Publisher', 'Exportieren → PDF → Voreinstellung «PDF/X-1a:2003» oder «PDF (für Export)» mit Kompatibilität PDF 1.4; Beschnitt aus.'],
          ['Adobe InDesign / Illustrator', 'Adobe PDF-Vorgabe «PDF/X-1a:2001»; Marken und Anschnitt aus.'],
          ['Figma', 'Frame 210 × 297 mm (595 × 842 pt) → Export PDF. Ergebnis ist oft neuer als PDF 1.4: wenn der Upload es ablehnt, in Vorschau/Acrobat als PDF 1.4 bzw. PDF/X neu sichern.'],
          ['Pages / Word', 'Funktioniert für einfache Briefköpfe. Als PDF exportieren; falls abgelehnt, siehe Figma.'],
      ], [45 * mm, 125 * mm]),
      P('Hinweise zu einzelnen Programmen sind allgemein gehalten; Menünamen können je nach Version abweichen.', small)]

s += [P('Checkliste vor dem Hochladen', h2)]
for item in ['A4 hochkant, genau 210 × 297 mm, ohne Beschnitt',
             'Adressfeld (mit Verrutsch-Bereich) ist weiss',
             'Textbereich ist ruhig, keine wechselnden Daten im Briefpapier',
             'Fusszeile liegt unterhalb des Textendes',
             'Nichts Wichtiges in den äussersten 5 mm',
             'PDF 1.4 oder PDF/X-1a, Schriften eingebettet oder in Pfaden, Hilfsebene ausgeblendet',
             'Datei ≤ 5 MB',
             'Probedruck mit Couvert gemacht']:
    s.append(Paragraph(item, bul, bulletText='☐'))

s += [P('Werte für den Planer notieren', h2),
      P('Diese Felder stellst du nach dem Upload unter Firmendaten ein. Dann «Musterrechnung als PDF» drücken und prüfen.'),
      table([
          ['Feld im Planer', 'Vorschlag', 'Mein Wert'],
          ['Rand oben Seite 1', f'{V["text_top_s1"]} mm', ''],
          ['Rand oben Folgeseiten', f'{V["text_top_f"]} mm', ''],
          ['Rand unten', f'{V["bottom"]} mm', ''],
          ['Rand links / rechts', f'{V["left"]} / {V["right"]} mm', ''],
          ['Adressfeld: Fenster', 'links oder rechts', ''],
          ['Adressfeld: von links / von oben', f'{P["addressLeft"]} / {P["addressTop"]} mm (links)', ''],
          ['Adressfeld: Breite / Höhe', f'{P["addressWidth"]} / {P["addressHeight"]} mm', ''],
          ['Schrift', 'Auswahl im Planer', ''],
      ], [60 * mm, 50 * mm, 60 * mm])]

s += [NextPageTemplate('s1'), PageBreak(), Spacer(1, 1),
      NextPageTemplate('sf'), PageBreak(), Spacer(1, 1),
      NextPageTemplate('qr'), PageBreak(), Spacer(1, 1)]

doc.build(s)
print('ok')
