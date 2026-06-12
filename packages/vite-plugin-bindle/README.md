# maryeperry-vite-plugin-bindle

Vite plugin that supplies the Laravel [Bindle](https://github.com/maryeperry/bindle) package with a JSON manifest of every Vue, React, and Svelte component in your project — including each component's prop signature.

PHP cannot reliably parse `.vue`, `.jsx/.tsx`, or `.svelte`. So at build time this plugin walks the Vite transform pipeline, parses each component with `@vue/compiler-sfc`, `@babel/parser`, or `svelte/compiler`, and writes the result to `public/build/bindle-manifest.json`. Bindle reads that file when it generates its audit; the plugin itself does nothing at scan time.

## Install

```bash
npm install --save-dev maryeperry-vite-plugin-bindle
```

## Use

```js
// vite.config.js
import { defineConfig } from 'vite';
import bindle from 'maryeperry-vite-plugin-bindle';

export default defineConfig({
  plugins: [
    bindle({
      // optional — defaults to public/build/bindle-manifest.json
      output: 'public/build/bindle-manifest.json',
    }),
  ],
});
```

Then run a normal Vite build:

```bash
npm run build
```

The manifest will appear at the configured path. Run `php artisan bindle:scan` after that.

## Manifest shape

```json
{
  "generatedAt": "2026-06-11T18:00:00.000Z",
  "components": [
    {
      "file": "resources/js/Components/Button.vue",
      "name": "Button",
      "kind": "vue",
      "props": [
        { "name": "label", "type": "string", "required": true, "default": null }
      ]
    }
  ]
}
```

If a file fails to parse, the entry is still emitted with `"parseError": "<message>"` so it surfaces in `bindle:errors` for triage.
