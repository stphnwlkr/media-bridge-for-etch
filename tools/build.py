"""Build only runtime plugin files, excluding source tests, handoffs and old ZIPs."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import re
root = Path(__file__).resolve().parents[1]
version = re.search(r' \* Version: (\S+)', (root / 'media-bridge-for-etch.php').read_text())[1]
output = root / 'builds' / f'uplink-media-bridge-for-etch-{version}.zip'
output.parent.mkdir(exist_ok=True)
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for name in ('media-bridge-for-etch.php', 'uninstall.php', 'readme.txt', 'LICENSE', 'includes', 'assets', 'blocks'):
        path = root / name
        for file in sorted(path.rglob('*')) if path.is_dir() else [path]:
            if file.is_file() and file.name != '.DS_Store':
                archive.write(file, Path('media-bridge-for-etch') / file.relative_to(root))
print(output)
