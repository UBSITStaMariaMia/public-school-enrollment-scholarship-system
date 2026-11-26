<style>
@page { size: A4 <?= !empty($formalLandscape) ? 'landscape' : 'portrait' ?>; margin: 14mm; }
* { box-sizing: border-box; }
html, body { margin: 0; background: #fff; color: #000; }
body, table, th, td, button, a { font: 10pt/1.35 Arial, Helvetica, sans-serif; color: #000; }
.report-page, .report-actions { width: min(100% - 32px, <?= !empty($formalLandscape) ? '269mm' : '182mm' ?>); margin: 16px auto; }
.report-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.report-actions button, .report-actions a { padding: 8px 12px; border: 1px solid #000; background: #fff; color: #000; cursor: pointer; text-decoration: none; }
.report-header { border-bottom: 1px solid #000; text-align: center; padding-bottom: 7pt; margin-bottom: 10pt; }
.school-name { font-size: 15pt; margin: 0; }
.school-subtitle, .report-description { margin: 3pt 0; }
.report-title { font-size: 13pt; text-align: center; margin: 0 0 10pt; }
.report-section-title, .report-page h3, .report-page .report-section > h2 { font-size: 11pt; margin: 12pt 0 4pt; break-after: avoid; }
.report-meta, .report-details { margin: 0 0 10pt; }
.report-page p { margin: 4pt 0; }
.report-page table { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
.report-page th, .report-page td { border: .5pt solid #000; padding: 3pt 4pt; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
.report-page th { font-weight: bold; }
.report-page thead { display: table-header-group; break-inside: avoid; }
.report-page tr { break-inside: avoid; }
.report-page .report-details th { white-space: nowrap; }
.report-page .report-footer { margin-top: 12pt; border-top: .5pt solid #000; padding-top: 5pt; break-inside: avoid; }
.application-report-card { margin-top: 12pt; }
.application-report-card h3 { border-bottom: .5pt solid #000; padding-bottom: 3pt; }

.portal-back-link {
    display: inline-flex;
    align-self: flex-start;
    align-items: center;
    gap: 8px;
    width: fit-content;
    min-height: 40px;
    padding: 4px 8px 4px 4px;
    color: var(--maroon, #000);
    font-weight: 650;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.portal-back-link::before {
    content: "";
    width: 7px;
    height: 7px;
    border-left: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: rotate(45deg);
}

.portal-back-link:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 3px;
}

.report-actions .portal-back-link {
    padding: 4px 8px 4px 4px;
    border: 0;
    text-decoration: underline;
}

@media screen and (max-width: 600px) {
    .report-page {
        overflow-x: auto;
    }

    .report-page .report-details th {
        white-space: normal;
    }
}

@media print {
    /* Leave no room for browser-generated headers or footers on the first sheet.
       Chromium uses that first sheet when deciding whether to print them. */
    @page :first {
        margin-top: 0;
        margin-bottom: 0;
    }
    .report-actions { display: none; }
    .report-page { width: auto; margin: 0; padding-block: 14mm; }
}
</style>
