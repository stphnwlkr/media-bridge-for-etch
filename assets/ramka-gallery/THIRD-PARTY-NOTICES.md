# Third-party notices

`@ramka/react` ships no runtime dependencies. It does contain code adapted from
one other project, listed below with its license.

## Base UI (`@base-ui/react`, `@base-ui/utils`)

`src/render/merge-props.ts`, `src/render/use-render.ts` and
`src/render/use-merged-refs.ts` are adapted from Base UI's prop merging, element
rendering and ref merging utilities. The behaviour is deliberately close to Base
UI's, so a part composes the way Base UI and Radix users expect.

Three Base UI features are deliberately left out, since nothing here uses them:
props getters, `event.preventBaseUIHandler()`, and unwrapping a bare
`React.lazy` object passed as `render` (which relies on React 19's `Children`
and so could not hold on React 18, which this package supports).

```
The MIT License (MIT)

Copyright (c) 2019 Material-UI SAS

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
