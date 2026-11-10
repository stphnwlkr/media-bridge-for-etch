# Ramka Gallery sources

The viewer uses the MIT-licensed Ramka library and adapts its landing demo skin
from [revision 3e223d5](https://github.com/arturbien/ramka/tree/3e223d5b353587e2629a7c2029dc0accbeaa65fd).
The relevant upstream files are under `packages/ramka/src` and
`apps/docs/app/lightbox-demo`. The editable WordPress integration is in
`viewer.jsx` and `viewer.css`. The runtime uses WordPress's own React and
ReactDOM, and makes no requests to Ramka's site.

From the Uplink development checkout, install esbuild 0.27.7 locally, check out
that Ramka revision, and run `node tools/build-ramka.cjs /path/to/ramka`. It
builds `viewer.js` for both plugin packages. The checked-in bundle is ready to
use when installing either plugin.

Ramka and its Base UI notices are in `LICENSE.txt` and
`THIRD-PARTY-NOTICES.md`. The toolbar glyphs are from
[Lucide](https://github.com/lucide-icons/lucide) with their notice in
`LUCIDE-LICENSE.txt`. The Latin Inter variable font is the
[font subset served by the demo](https://ramka.dev/_next/static/media/e4af272ccee01ff0-s.p.woff2)
and is bundled with its [SIL Open Font License](https://github.com/google/fonts/tree/main/ofl/inter)
in `INTER-OFL.txt`.
