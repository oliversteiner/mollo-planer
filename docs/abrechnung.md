# Abrechnung

Rechnungen aus erledigten Meilensteinen (Auftrag 39). Recht `billing.manage` (Systemrecht, unabhängig von den Rollen im Projekt, nie für Agent-Konten). Ablauf, Regeln und Fehlercodes stehen im Auftrag und im GraphQL-Schema (`backend/graphql/billing.graphql`); der Stand in `docs/umsetzung.md`.

## Zwei PDF-Bibliotheken: mPDF und FPDF

Eine Rechnung entsteht mit **mPDF**, wie die PDFs der Dokumente: Briefseiten im gemeinsamen Rahmen der Basis (`App\Core\Pdf\PdfFrame`, Satzspiegel, Schrift, Briefpapier). Nur die **letzte Seite, der Zahlteil der QR-Rechnung**, entsteht mit **FPDF** (`fpdf/fpdf`): `sprain/swiss-qr-bill` zeichnet den Zahlteil mit FPDF, TCPDF oder als HTML, nicht mit mPDF, und die HTML-Ausgabe gibt mPDF für die Masse der Swiss Payment Standards (Empfangsschein 62 mm, Zahlteil 148 mm, Höhe 105 mm, Schriftgrössen) nicht genau genug wieder. Darum zeichnet die Bibliothek diese eine Seite mit FPDF (`App\Services\Billing\PaymentPartPage`), und mPDF fügt sie unverändert mit FPDI ein (`App\Services\Billing\InvoicePdf`), ohne Briefpapier. Sonst verwendet nichts FPDF: Der Test `FpdfBoundaryTest` hält das fest, und `BillingPaymentPageTest` prüft, dass FPDI die von FPDF erzeugte Seite lesen kann (die freie FPDI liest keine komprimierten Objekt-Streams; ein Update von FPDF darf das nicht unbemerkt ändern). Entscheid Oliver, 25.11.2026.
