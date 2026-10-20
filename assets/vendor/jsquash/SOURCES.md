# Local image codec sources

Media Bridge loads these files only from its own plugin directory. No CDN, external conversion service, license server or Cimo Pro dependency is used. Encoding runs in a local module worker. Single-thread builds avoid cross-origin isolation requirements. The integration worker is readable in assets/image-encode-worker.js.

* @jsquash/avif 2.1.1: https://registry.npmjs.org/@jsquash/avif/-/avif-2.1.1.tgz
  Source: https://github.com/jamsinclair/jSquash/tree/b7fa9ac9ec02f224847ad23d19d115f9e296a368/packages/avif
* @jsquash/webp 1.5.0: https://registry.npmjs.org/@jsquash/webp/-/webp-1.5.0.tgz
  Source: https://github.com/jamsinclair/jSquash/tree/8bcd212da8c2be7c9c223e8f222eb3d9574a713b/packages/webp
* @jsquash/jxl 1.3.0: https://registry.npmjs.org/@jsquash/jxl/-/jxl-1.3.0.tgz
  Source: https://github.com/jamsinclair/jSquash/tree/1b1964194611ece3ec984ab91c04726d1088f05e/packages/jxl

Unmodified generated encoder glue, WASM binaries, default options, upstream README and Apache-2.0 license are included in each codec directory. JPEG XL also includes its decoder for previewing in browsers that lack native JPEG XL decoding. The readable C/C++ codec sources and build instructions are maintained in the jSquash repository and the linked upstream libwebp, libavif and libjxl repositories. Follow the upstream codec build instructions for the package versions above to regenerate WASM. The plugin copies distributed package artifacts without a codec build step.

The Apache-2.0 wrappers and BSD codec licenses are compatible with the plugin's GPL-3.0-or-later license. Original copyright and patent notices are retained. Third-party files retain their own licenses.
