"""Resume authorized copies of original signed attachments; never fabricate or overwrite."""
import argparse, concurrent.futures, importlib.util, json, os, time, urllib.parse, urllib.request
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
ROOT=Path('/Users/ramonoem/Documents/project/webbsunitapp/storage/app/public').resolve()
OUT=HERE.parents[1]/'outputs/unified-db/signed-contract-restore-result.json'

def copy_file(raw):
    rel=Path(raw)
    target=(ROOT/rel).resolve()
    if rel.is_absolute() or '..' in rel.parts or ROOT not in target.parents or not raw.startswith('contract/'):
        return {'unsafe':1}
    if target.is_file() and target.stat().st_size>0:return {'existing':1}
    target.parent.mkdir(parents=True,exist_ok=True)
    temporary=target.with_name(target.name+'.restore-part')
    url='https://contract.chaktomukcity.com/storage/'+urllib.parse.quote(raw,safe='/')
    for attempt in range(3):
        try:
            req=urllib.request.Request(url,headers={'User-Agent':'BSUnits-local-authorized-file-restore/1.0'})
            with urllib.request.urlopen(req,timeout=45) as response:
                if response.status!=200 or urllib.parse.urlsplit(response.url).netloc!='contract.chaktomukcity.com':raise ValueError('Unexpected response')
                length=int(response.headers.get('Content-Length','0'))
                kind=response.headers.get('Content-Type','').split(';')[0].lower()
                if kind in ('text/html','application/json'):raise ValueError('Error document')
                size=0;head=b''
                with temporary.open('wb') as dest:
                    while True:
                        chunk=response.read(1024*1024)
                        if not chunk:break
                        if not head:head=chunk[:16]
                        size+=len(chunk)
                        if size>512*1024*1024:raise ValueError('Unexpectedly large file')
                        dest.write(chunk)
                valid=(head.startswith(b'%PDF-') or head.startswith(b'\xff\xd8\xff') or head.startswith(b'\x89PNG\r\n\x1a\n') or head.startswith((b'GIF87a',b'GIF89a')) or head.startswith(b'RIFF'))
                if not size or not valid or (length and size!=length):raise ValueError('Invalid or incomplete document')
                temporary.chmod(0o644)
                try:os.link(temporary,target)
                except FileExistsError:
                    if target.stat().st_size==0:raise ValueError('Existing empty file retained for review')
                    return {'existing':1}
                return {'restored':1,'bytes':size}
        except Exception:
            if attempt<2:time.sleep(1+attempt)
        finally:temporary.unlink(missing_ok=True)
    return {'failed':1}

def run():
    parser=argparse.ArgumentParser();parser.add_argument('--limit',type=int);parser.add_argument('--workers',type=int,default=4);args=parser.parse_args()
    paths=m.target('SELECT DISTINCT path FROM contract_attachments WHERE deleted_at IS NULL AND path IS NOT NULL ORDER BY path')
    if args.limit:paths=paths[:args.limit]
    counters={k:0 for k in ['restored','existing','bytes','failed','unsafe']};started=time.time()
    def checkpoint(status):
        report={'status':status,'requested_files':len(paths),'processed_files':counters['restored']+counters['existing']+counters['failed']+counters['unsafe'],'counts':dict(counters),'elapsed_seconds':round(time.time()-started),'scope':'Actual active signed-contract files copied from original public storage URLs to local storage. Existing nonempty files retained; DB and production unchanged. Filenames and contents withheld.'}
        temp=OUT.with_suffix('.tmp');temp.write_text(json.dumps(report,indent=2)+'\n');temp.replace(OUT)
    checkpoint('RUNNING')
    with concurrent.futures.ThreadPoolExecutor(max_workers=min(max(args.workers,1),4)) as executor:
        for index,result in enumerate(executor.map(copy_file,paths),1):
            for key,value in result.items():counters[key]+=value
            if index%100==0:checkpoint('RUNNING')
            if index%1000==0:print('Processed',index,'of',len(paths),'files; failures',counters['failed'],flush=True)
    checkpoint('PASSED' if not counters['failed'] and not counters['unsafe'] else 'REVIEW_REQUIRED')
    print(OUT.read_text(),flush=True)
    return 1 if counters['failed'] or counters['unsafe'] else 0
if __name__=='__main__':raise SystemExit(run())
