<?php
class SpreadsheetExportService
{
    public function build(array $rows, array $widths): string
    {
        $end=chr(64+count($widths));
        $last=count($rows)-1;
        $xml=function($s){return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$s), ENT_XML1 | ENT_QUOTES, 'UTF-8');};
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
        foreach ($widths as $i=>$w) $sheet.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.$w.'" customWidth="1"/>';
        $sheet.='</cols><sheetData>';
        foreach ($rows as $i=>$row) {
            $n=$i+1;$sheet.='<row r="'.$n.'" ht="'.($n===3?36:24).'" customHeight="1">';
            foreach ($row as $j=>$value) {
                $style=is_float($value)?2:($n===1||$n===5||$n===count($rows)?1:0);
                $cell='<c r="'.chr(65+$j).$n.'" s="'.$style.'"';
                // Inline strings prevent user-provided values from becoming spreadsheet formulas.
                $sheet.=$cell.(is_float($value)?'><v>'.$value.'</v></c>':' t="inlineStr"><is><t xml:space="preserve">'.$xml($value).'</t></is></c>');
            }
            $sheet.='</row>';
        }
        $sheet.='</sheetData><autoFilter ref="A5:'.$end.''.max(5,$last).'"/><mergeCells count="3"><mergeCell ref="A1:'.$end.'1"/><mergeCell ref="A2:'.$end.'2"/><mergeCell ref="A3:'.$end.'3"/></mergeCells><pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
        $files=[
            '[Content_Types].xml'=>'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml'=>'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.000"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF234638"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml'=>$sheet,
        ];
        $path=tempnam(sys_get_temp_dir(),'dagril-xlsx-');
        try {
            $zip=new ZipArchive();
            if($zip->open($path,ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Impossible de préparer le fichier Excel.');
            foreach($files as $name=>$content) $zip->addFromString($name,$content);
            $zip->close();
            return file_get_contents($path);
        } finally { if(is_file($path)) unlink($path); }
    }
}
