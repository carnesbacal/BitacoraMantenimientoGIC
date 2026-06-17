<?php
/**
 * config/xlsx_writer.php
 * Clase XlsxWriter — genera archivos .xlsx sin dependencias externas.
 * Requiere la extensión ZipArchive (incluida en PHP/XAMPP/cPanel).
 *
 * Estilos predefinidos:
 *   0 = normal
 *   1 = header gris
 *   2 = número (2 decimales)
 *   3 = moneda ($)
 *   4 = fecha
 *   5 = header oscuro
 */
class XlsxWriter
{
    private array $sheets      = [];   // [name => rows[]]
    private array $sheetCols   = [];   // [name => [maxWidth[]]]
    private string $currentSheet = '';

    // ── API pública ───────────────────────────────────────────────────────────

    public function addSheet(string $name): void
    {
        $this->sheets[$name]    = [];
        $this->sheetCols[$name] = [];
        $this->currentSheet     = $name;
    }

    /** Agrega una fila con estilo por celda o uno global. */
    public function addRow(array $row, int $rowStyle = 0): void
    {
        $this->appendRow($row, $rowStyle);
    }

    public function addHeaderRow(array $row, bool $dark = false): void
    {
        $this->appendRow($row, $dark ? 5 : 1);
    }

    public function addBlankRow(): void
    {
        $this->sheets[$this->currentSheet][] = [];
    }

    public function download(string $filename): void
    {
        $zip = $this->buildZip();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Cache-Control: max-age=0');
        echo $zip;
        exit;
    }

    public function save(string $path): void
    {
        file_put_contents($path, $this->buildZip());
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    private function appendRow(array $row, int $style): void
    {
        $s = $this->currentSheet;
        if ($s === '') return;
        $this->sheets[$s][] = ['cells' => $row, 'style' => $style];
        // Actualizar anchos de columna
        foreach ($row as $ci => $val) {
            $len = mb_strlen((string) $val) + 2;
            if (!isset($this->sheetCols[$s][$ci]) || $this->sheetCols[$s][$ci] < $len) {
                $this->sheetCols[$s][$ci] = min($len, 60);
            }
        }
    }

    private function buildZip(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $sheetNames = array_keys($this->sheets);
        $sheetCount = count($sheetNames);

        // [Content_Types].xml
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml"  ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml"   ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ct .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $ct);

        // _rels/.rels
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        // xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
                . '<Relationship Id="rIdSt" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $wbRels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $wbRels .= '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // xl/workbook.xml
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>';
        for ($i = 0; $i < $sheetCount; $i++) {
            $sName = $this->xmlEscape($sheetNames[$i]);
            $wb .= '<sheet name="' . $sName . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        $wb .= '</sheets></workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // xl/styles.xml
        $zip->addFromString('xl/styles.xml', $this->buildStyles());

        // Shared strings
        $ssIndex = [];
        $ssArr   = [];

        // xl/worksheets/sheetN.xml
        for ($i = 0; $i < $sheetCount; $i++) {
            $name = $sheetNames[$i];
            $xml  = $this->buildSheet($name, $ssIndex, $ssArr);
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $xml);
        }

        // xl/sharedStrings.xml
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
               . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($ssArr) . '" uniqueCount="' . count($ssArr) . '">';
        foreach ($ssArr as $s) {
            $ssXml .= '<si><t xml:space="preserve">' . $this->xmlEscape($s) . '</t></si>';
        }
        $ssXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);

        $zip->close();
        $data = file_get_contents($tmp);
        unlink($tmp);
        return $data;
    }

    private function buildSheet(string $name, array &$ssIndex, array &$ssArr): string
    {
        $rows    = $this->sheets[$name];
        $colWidths = $this->sheetCols[$name];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Column widths
        if (!empty($colWidths)) {
            $xml .= '<cols>';
            foreach ($colWidths as $ci => $w) {
                $xml .= '<col min="' . ($ci + 1) . '" max="' . ($ci + 1) . '" width="' . $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($rows as $ri => $row) {
            if (empty($row)) { continue; } // blank rows skip
            $rowNum = $ri + 1;
            $xml .= '<row r="' . $rowNum . '">';
            $cells  = $row['cells'] ?? [];
            $rStyle = $row['style'] ?? 0;
            foreach ($cells as $ci => $val) {
                $col   = $this->colLetter($ci);
                $addr  = $col . $rowNum;
                $style = $rStyle;

                if (is_null($val) || $val === '') {
                    $xml .= '<c r="' . $addr . '" s="' . $style . '"/>';
                } elseif (is_numeric($val) && !in_array($style, [1, 5])) {
                    // Número
                    $fStyle = ($style === 0) ? 2 : $style;
                    $xml .= '<c r="' . $addr . '" t="n" s="' . $fStyle . '"><v>' . $val . '</v></c>';
                } else {
                    // Cadena compartida
                    $sval = (string) $val;
                    if (!isset($ssIndex[$sval])) {
                        $ssIndex[$sval] = count($ssArr);
                        $ssArr[]        = $sval;
                    }
                    $xml .= '<c r="' . $addr . '" t="s" s="' . $style . '"><v>' . $ssIndex[$sval] . '</v></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function buildStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<fonts count="3">'
             . '<font><sz val="11"/><name val="Calibri"/></font>'                               // 0 normal
             . '<font><sz val="11"/><b/><name val="Calibri"/></font>'                           // 1 bold
             . '<font><sz val="11"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'    // 2 white bold
             . '</fonts>'
             . '<fills count="4">'
             . '<fill><patternFill patternType="none"/></fill>'
             . '<fill><patternFill patternType="gray125"/></fill>'
             . '<fill><patternFill patternType="solid"><fgColor rgb="FFE5E7EB"/></patternFill></fill>' // gris claro (header)
             . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F2937"/></patternFill></fill>' // gris oscuro
             . '</fills>'
             . '<borders count="2">'
             . '<border><left/><right/><top/><bottom/><diagonal/></border>'
             . '<border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border>'
             . '</borders>'
             . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
             . '<cellXfs count="6">'
             // 0: normal
             . '<xf numFmtId="0"  fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
             // 1: header gris
             . '<xf numFmtId="0"  fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
             // 2: número
             . '<xf numFmtId="2"  fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1"/>'
             // 3: moneda
             . '<xf numFmtId="44" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1"/>'
             // 4: fecha
             . '<xf numFmtId="14" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1"/>'
             // 5: header oscuro
             . '<xf numFmtId="0"  fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
             . '</cellXfs>'
             . '</styleSheet>';
    }

    private function colLetter(int $index): string
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $mod     = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index   = intdiv($index - $mod, 26);
        }
        return $letters;
    }

    private function xmlEscape(string $s): string
    {
        return str_replace(['&','<','>','"',"'"], ['&amp;','&lt;','&gt;','&quot;','&apos;'], $s);
    }
}
