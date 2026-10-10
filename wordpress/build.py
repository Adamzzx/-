from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED, ZipInfo
import hashlib
root=Path(__file__).resolve().parent
out=root/'dist';out.mkdir(exist_ok=True)
sums=[]
for component in ['theme','plugin']:
    archive=out/f'exchange-guide-{component}.zip'
    with ZipFile(archive,'w',ZIP_DEFLATED) as z:
        for p in sorted((root/component/'exchange-guide').rglob('*')):
            if p.is_file():
                info=ZipInfo(str(p.relative_to(root/component)),date_time=(2026,1,1,0,0,0))
                info.external_attr=0o100644 << 16
                info.compress_type=ZIP_DEFLATED
                z.writestr(info,p.read_bytes())
    archive.chmod(0o644)
    with ZipFile(archive) as z:
        assert z.testzip() is None
        required='exchange-guide/style.css' if component=='theme' else 'exchange-guide/exchange-guide.php'
        assert required in z.namelist()
    sums.append(hashlib.sha256(archive.read_bytes()).hexdigest()+'  '+archive.name)
    print(archive.name, archive.stat().st_size,'bytes; archive verified')
(out/'SHA256SUMS').write_text('\n'.join(sums)+'\n')
