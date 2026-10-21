from pathlib import Path
import re,shutil
source=Path('/tmp/mbe-webawesome/package');base=source/'dist-cdn';dest=Path('assets/vendor/webawesome');dest.mkdir(exist_ok=True)
seen=set()
def copy(relative):
 relative=Path(relative);p=(base/relative).resolve();relative=p.relative_to(base.resolve())
 if relative in seen:return
 seen.add(relative);text=p.read_text();out=dest/relative;out.parent.mkdir(parents=True,exist_ok=True);out.write_text(text)
 for name in re.findall(r'(?:from\s*|import\s*)[\'\"]([^\'\"]+)[\'\"]',text):
  if name.startswith('.'):copy(relative.parent/name)
for name in ['button','button-group','radio-group','radio','checkbox','progress-bar','card','badge','input','textarea','select','option','slider','tab','tab-group','tab-panel','tag','callout','tooltip']:copy(Path('components')/name/(name+'.js'))
# Flatten only the default theme and layout utilities. No native/reset styles in WordPress.
def flatten(p):
 text=p.read_text()
 return re.sub(r"@import url\(['\"]([^'\"]+)['\"]\);",lambda m:flatten(p.parent/m[1]),text)
css=flatten(base/'styles/themes/default.css')+'\n'+flatten(base/'styles/utilities.css')
css=css.replace(':where(:root)', '.uplink-mbe-optimization-theme').replace(':root', '.uplink-mbe-optimization-theme')
(dest/'theme.css').write_text(css)
shutil.copy2(source/'LICENSE.md',dest/'LICENSE')
(dest/'SOURCES.md').write_text('Web Awesome 3.14.0, MIT. Source: https://github.com/shoelace-style/webawesome/tree/v3.14.0 . Official npm package @awesome.me/webawesome. Selected readable dist-cdn components and transitive chunks are included unchanged. theme.css flattens the default theme and utilities, scopes root tokens to the Enhanced Manager and optimization UI, and excludes the native/reset stylesheet. Rebuild using tools/vendor-webawesome.py with the official package extracted under /tmp/mbe-webawesome/package. No CDN runtime or Pro assets.\n')
Path('assets/optimization-components.js').write_text('\n'.join("import './vendor/webawesome/components/{0}/{0}.js';".format(x) for x in ['button','button-group','radio-group','radio','checkbox','progress-bar','card','badge','input','textarea','select','option','slider','tab','tab-group','tab-panel','tag','callout','tooltip'])+'\n')
print('Vendored',len(seen),'component files')
