import ExcelJS from 'exceljs';
import PDFDocument from 'pdfkit';
import { reportColumns, reportRows, CURRENCY, DATE } from './reports.js';
import Settings from './settings.js';
import { config } from '../config.js';

const filtersLabel = (filters) =>
  Object.entries(filters).filter(([, v]) => v).map(([k, v]) => `${k}: ${v}`).join(', ') || 'none';

/** CSV export. */
export async function toCsv(report, filters) {
  const columns = reportColumns(report);
  const rows = await reportRows(report, filters);
  const escape = (v) => {
    const s = v === null || v === undefined ? '' : String(v);
    return /[",\n]/.test(s) ? `"${s.replaceAll('"', '""')}"` : s;
  };
  return [columns.map((c) => escape(c.label)).join(','), ...rows.map((r) => r.map(escape).join(','))].join('\n');
}

/**
 * Formatted XLSX: metadata block (title, generated, prepared-by, applied
 * filters), bold frozen header row, auto filter, currency/date formats,
 * wrapped text, totals for currency columns, landscape Letter print setup
 * with repeated header rows and a confidentiality footer.
 */
export async function toXlsx(report, reportName, filters, preparedBy) {
  const columns = reportColumns(report);
  const rows = await reportRows(report, filters);
  const brand = await Settings.brand('name');

  const workbook = new ExcelJS.Workbook();
  workbook.creator = preparedBy;
  workbook.title = reportName;
  workbook.company = await Settings.brand('legal_name');

  const sheet = workbook.addWorksheet(reportName.slice(0, 31), {
    pageSetup: {
      orientation: 'landscape', paperSize: 1 /* Letter */,
      fitToPage: true, fitToWidth: 1, fitToHeight: 0,
      printTitlesRow: '4:4',
    },
    headerFooter: {
      oddFooter: `&L${config.confidentialityFooter.replace('{name}', preparedBy)}&RPage &P of &N`,
    },
    views: [{ state: 'frozen', ySplit: 4 }],
  });

  sheet.addRow([`${reportName} — ${brand}`]).font = { bold: true, size: 14 };
  sheet.addRow([`Generated: ${new Date().toISOString().slice(0, 16).replace('T', ' ')}  •  Prepared by: ${preparedBy}`]);
  sheet.addRow([`Applied filters: ${filtersLabel(filters)}`]);

  const headerRow = sheet.addRow(columns.map((c) => c.label));
  headerRow.font = { bold: true };
  headerRow.border = { bottom: { style: 'medium' } };
  sheet.autoFilter = { from: { row: 4, column: 1 }, to: { row: 4, column: columns.length } };

  for (const row of rows) {
    const added = sheet.addRow(row.map((cell, i) => {
      if (columns[i].format === CURRENCY && cell !== null) return Number(cell);
      if (columns[i].format === DATE && cell) return new Date(cell);
      return cell;
    }));
    added.alignment = { wrapText: true, vertical: 'top' };
  }

  columns.forEach((column, i) => {
    const excelColumn = sheet.getColumn(i + 1);
    excelColumn.width = Math.min(50, Math.max(12, column.label.length + 4,
      ...rows.slice(0, 50).map((r) => String(r[i] ?? '').length + 2)));
    if (column.format === CURRENCY) excelColumn.numFmt = '"$"#,##0.00';
    if (column.format === DATE) excelColumn.numFmt = 'yyyy-mm-dd';
  });

  const currencyIndexes = columns.map((c, i) => (c.format === CURRENCY ? i : -1)).filter((i) => i >= 0);
  if (currencyIndexes.length && rows.length) {
    const first = 5;
    const last = 4 + rows.length;
    const totals = sheet.addRow(columns.map((c, i) => {
      if (i === 0) return 'Totals';
      if (currencyIndexes.includes(i)) {
        const letter = sheet.getColumn(i + 1).letter;
        return { formula: `SUM(${letter}${first}:${letter}${last})` };
      }
      return null;
    }));
    totals.font = { bold: true };
    currencyIndexes.forEach((i) => { totals.getCell(i + 1).numFmt = '"$"#,##0.00'; });
  }

  return workbook.xlsx.writeBuffer();
}

/** PDF report: repeated headers per page, page numbers, confidentiality footer, currency totals. */
export async function toPdf(report, reportName, filters, preparedBy, { orientation = 'landscape', paper = 'letter' } = {}) {
  const columns = reportColumns(report);
  const rows = await reportRows(report, filters);
  const brand = await Settings.brand('name');

  const doc = new PDFDocument({
    size: paper === 'legal' ? 'LEGAL' : 'LETTER',
    layout: orientation === 'portrait' ? 'portrait' : 'landscape',
    margins: { top: 60, bottom: 50, left: 36, right: 36 },
    bufferPages: true,
  });
  const chunks = [];
  doc.on('data', (c) => chunks.push(c));
  const done = new Promise((resolve) => doc.on('end', () => resolve(Buffer.concat(chunks))));

  const pageWidth = doc.page.width - 72;
  const widths = columns.map(() => pageWidth / columns.length);
  const money = (v) => `$${Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;

  const drawHeader = () => {
    doc.font('Helvetica-Bold').fontSize(13).fillColor('#1b2a4a')
      .text(`${reportName} — ${brand}`, 36, 24, { width: pageWidth });
    doc.font('Helvetica').fontSize(7).fillColor('#555555')
      .text(`Generated: ${new Date().toISOString().slice(0, 16).replace('T', ' ')} • Prepared by: ${preparedBy} • Filters: ${filtersLabel(filters)}`);
    doc.moveDown(0.5);
    let x = 36;
    const y = doc.y;
    doc.rect(36, y, pageWidth, 16).fill('#1b2a4a');
    doc.fillColor('#ffffff').font('Helvetica-Bold').fontSize(7);
    columns.forEach((column, i) => { doc.text(column.label, x + 3, y + 4, { width: widths[i] - 6 }); x += widths[i]; });
    doc.y = y + 20;
    doc.fillColor('#1b2a4a').font('Helvetica').fontSize(7);
  };

  drawHeader();
  const totals = {};
  for (const row of rows) {
    const heights = row.map((cell, i) => doc.heightOfString(String(cell ?? ''), { width: widths[i] - 6 }));
    const rowHeight = Math.max(12, ...heights) + 4;
    if (doc.y + rowHeight > doc.page.height - 60) { doc.addPage(); drawHeader(); }
    let x = 36;
    const y = doc.y;
    row.forEach((cell, i) => {
      const isCurrency = columns[i].format === CURRENCY;
      if (isCurrency && cell !== null && cell !== undefined) totals[i] = (totals[i] ?? 0) + Number(cell);
      doc.text(isCurrency && cell != null ? money(cell) : String(cell ?? ''), x + 3, y, { width: widths[i] - 6, align: isCurrency ? 'right' : 'left' });
      x += widths[i];
    });
    doc.y = y + rowHeight;
    doc.moveTo(36, doc.y - 2).lineTo(36 + pageWidth, doc.y - 2).strokeColor('#e3e6ee').lineWidth(0.5).stroke();
  }

  if (Object.keys(totals).length) {
    let x = 36;
    const y = doc.y + 2;
    doc.font('Helvetica-Bold');
    columns.forEach((column, i) => {
      doc.text(i === 0 ? 'Totals' : (i in totals ? money(totals[i]) : ''), x + 3, y, { width: widths[i] - 6, align: column.format === CURRENCY ? 'right' : 'left' });
      x += widths[i];
    });
  }

  const range = doc.bufferedPageRange();
  for (let i = range.start; i < range.start + range.count; i++) {
    doc.switchToPage(i);
    doc.font('Helvetica').fontSize(6).fillColor('#666666')
      .text(`${config.confidentialityFooter.replace('{name}', preparedBy)}   |   Page ${i + 1} of ${range.count}`,
        36, doc.page.height - 36, { width: pageWidth, align: 'center' });
  }

  doc.end();
  return done;
}
