"""Restore missing local attachments from the user-provided archive, never overwrite files."""
from pathlib import Path
import tarfile,shutil,json
ARCHIVE=Path('/Users/ramonoem/Downloads/bsunits-contract-attachments.tar.gz')
ROOT=Path('/Users/ramonoem/Documents/project/webbsunitapp/storage/app/public').resolve()
counts={'restored_files':0,'existing_files_preserved':0,'restored_bytes':0}
with tarfile.open(ARCHIVE,'r|gz') as archive:
 for member in archive:
  relative=Path(member.name)
  if relative.is_absolute() or '..' in relative.parts or member.issym() or member.islnk():raise RuntimeError('Unsafe archive member; names withheld')
  target=(ROOT/relative).resolve()
  if ROOT not in target.parents and target!=ROOT:raise RuntimeError('Archive destination outside storage')
  if member.isdir():target.mkdir(parents=True,exist_ok=True);continue
  if not member.isfile():raise RuntimeError('Unsupported archive member')
  if target.exists():counts['existing_files_preserved']+=1;continue
  target.parent.mkdir(parents=True,exist_ok=True)
  with archive.extractfile(member) as source,target.open('xb') as destination:shutil.copyfileobj(source,destination,1024*1024)
  if target.stat().st_size!=member.size:raise RuntimeError('Extracted attachment size mismatch')
  target.chmod(0o644);counts['restored_files']+=1;counts['restored_bytes']+=member.size
  if counts['restored_files']%5000==0:print('Restored',counts['restored_files'],'files',flush=True)
result={'status':'RESTORED','counts':counts,'scope':'Missing local files restored; existing files and downloaded archive retained. No database or production changes.'}
Path('outputs/unified-db/local-attachment-restore-result.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
