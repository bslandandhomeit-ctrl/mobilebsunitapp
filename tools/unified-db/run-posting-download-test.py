"""Check actual original controller CSV/XLSX downloads without persisting customer data."""
import csv, io, json, subprocess, zipfile
from decimal import Decimal
from pathlib import Path
from xml.etree import ElementTree as ET

HERE=Path(__file__).resolve().parent
script=(HERE/'test-posting-download.php').read_bytes()
def run(format, scoped):
    args=['docker','exec','-i','-e','POSTING_DOWNLOAD_FORMAT='+format]
    if scoped: args+=['-e','POSTING_BRANCH_SCOPE=1']
    r=subprocess.run(args+['beloan-local','php'],input=script,capture_output=True)
    if r.returncode: raise RuntimeError('Original report download failed; output withheld.')
    return r.stdout

def xlsx_rows(data):
    ns={'s':'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
    with zipfile.ZipFile(io.BytesIO(data)) as z:
        strings=[]
        if 'xl/sharedStrings.xml' in z.namelist():
            for item in ET.fromstring(z.read('xl/sharedStrings.xml')).findall('s:si',ns):
                strings.append(''.join(t.text or '' for t in item.findall('.//s:t',ns)))
        out=[]
        for row in ET.fromstring(z.read('xl/worksheets/sheet1.xml')).findall('s:sheetData/s:row',ns):
            cells={}
            for c in row.findall('s:c',ns):
                col=''.join(ch for ch in c.attrib['r'] if ch.isalpha());v=c.find('s:v',ns)
                value=v.text if v is not None else ''
                if c.attrib.get('t')=='s':value=strings[int(value)]
                elif c.attrib.get('t')=='inlineStr':value=''.join(t.text or '' for t in c.findall('.//s:t',ns))
                cells[col]=value
            out.append([cells.get(col,'') for col in 'ABCDEFGHIJK'])
        return out

def money(value):return Decimal(value or '0').quantize(Decimal('0.0001'))
report={'status':'PASSED','scope':'Original controller downloads; original middleware and broader accounting reports are not covered.','cases':[]}
for scoped in [False,True]:
    expected=json.loads(run('metadata',scoped))['rows']
    for format in ['xlsx','csv']:
        data=run(format,scoped)
        rows=xlsx_rows(data) if format=='xlsx' else list(csv.reader(io.StringIO(data.decode('utf-8-sig'))))
        if len(rows)<2 or rows[1][1]!='Posting ID':raise RuntimeError('Export headers invalid.')
        actual=rows[2:]
        if len(actual)!=len(expected):raise RuntimeError('Export duplicated or dropped postings.')
        for row,stored in zip(actual,expected):
            if row[1]!=stored[0] or [money(v) for v in row[8:11]]!=[money(v) for v in stored[1:]]:
                raise RuntimeError('Export ID or stored money differs; source values withheld.')
        report['cases'].append({'format':format,'branch_scoped':scoped,'rows':len(actual),'stored_money_verified':True})
(HERE.parents[1]/'outputs/unified-db/posting-download-test-result.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
