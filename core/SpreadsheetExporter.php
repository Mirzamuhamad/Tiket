<?php

class SpreadsheetExporter
{
    public static function download($filename, $headers, $rows)
    {
        if (!class_exists('ZipArchive')) {
            self::downloadCsv(str_replace('.xlsx', '.csv', $filename), $headers, $rows);
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rels());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($headers, $rows));
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    private static function downloadCsv($filename, $headers, $rows)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    private static function sheetXml($headers, $rows)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $xml .= self::rowXml(1, $headers);
        $index = 2;
        foreach ($rows as $row) {
            $xml .= self::rowXml($index, array_values($row));
            $index++;
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private static function rowXml($index, $values)
    {
        $xml = '<row r="' . $index . '">';
        foreach ($values as $cellIndex => $value) {
            $ref = self::columnName($cellIndex + 1) . $index;
            if (is_numeric($value) && !preg_match('/^0[0-9]+/', (string) $value)) {
                $xml .= '<c r="' . $ref . '"><v>' . $value . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . self::escape($value) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
        return $xml;
    }

    private static function columnName($number)
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = (int) ($number / 26);
        }
        return $name;
    }

    private static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function contentTypes()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '</Types>';
    }

    private static function rels()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    private static function workbook()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Pembelian Tiket" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private static function workbookRels()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '</Relationships>';
    }
}
