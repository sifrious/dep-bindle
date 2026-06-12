/**
 * maryeperry-vite-plugin-bindle
 * -----------------------------
 * A Vite plugin that parses Vue (.vue), React (.jsx/.tsx) and Svelte (.svelte)
 * components encountered during the build and writes a manifest of their
 * names and prop signatures to `public/build/bindle-manifest.json`.
 *
 * The Laravel-side Bindle package reads this manifest — it never executes
 * Node code at scan time.
 */

import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, basename, extname, relative } from 'node:path';

export default function bindle(userOptions = {}) {
  const options = {
    output: userOptions.output || 'public/build/bindle-manifest.json',
    cwd: userOptions.cwd || process.cwd(),
  };

  /** @type {Map<string, object>} */
  const components = new Map();

  return {
    name: 'maryeperry-vite-plugin-bindle',

    async transform(code, id) {
      const ext = extname(id).toLowerCase();
      if (!['.vue', '.jsx', '.tsx', '.svelte'].includes(ext)) return null;

      try {
        if (ext === '.vue') {
          const entry = await parseVue(code, id);
          if (entry) components.set(id, entry);
        } else if (ext === '.jsx' || ext === '.tsx') {
          const entry = await parseReact(code, id, ext === '.tsx');
          if (entry) components.set(id, entry);
        } else if (ext === '.svelte') {
          const entry = await parseSvelte(code, id);
          if (entry) components.set(id, entry);
        }
      } catch (e) {
        // Keep the build going; record the failure in the manifest later.
        components.set(id, {
          file: relative(options.cwd, id),
          name: basename(id, ext),
          kind: ext === '.vue' ? 'vue' : ext === '.svelte' ? 'svelte' : 'react',
          props: [],
          parseError: String(e.message || e),
        });
      }

      return null;
    },

    buildEnd() {
      const manifest = {
        generatedAt: new Date().toISOString(),
        components: Array.from(components.values()),
      };

      const out = options.output;
      mkdirSync(dirname(out), { recursive: true });
      writeFileSync(out, JSON.stringify(manifest, null, 2));
    },
  };
}

async function parseVue(code, id) {
  const { parse } = await import('@vue/compiler-sfc');
  const { descriptor } = parse(code);
  const scriptBlock = descriptor.scriptSetup ?? descriptor.script;
  if (!scriptBlock) {
    return { file: relativeFile(id), name: componentName(id), kind: 'vue', props: [] };
  }

  const props = extractDefineProps(scriptBlock.content) ?? extractOptionsProps(scriptBlock.content) ?? [];

  return {
    file: relativeFile(id),
    name: componentName(id),
    kind: 'vue',
    props,
  };
}

function extractDefineProps(src) {
  // `defineProps<{ name: string; required?: number }>()` (TS form)
  const tsMatch = src.match(/defineProps<\s*\{([^}]*)\}\s*>/s);
  if (tsMatch) {
    const body = tsMatch[1];
    const props = [];
    for (const line of body.split(/[;\n,]/)) {
      const m = line.match(/\s*(\w+)(\??)\s*:\s*([\w\[\]<>|]+)/);
      if (m) props.push({ name: m[1], type: m[3], required: m[2] !== '?', default: null });
    }
    if (props.length) return props;
  }

  // `defineProps({ name: { type: String, required: true, default: 'x' } })`
  const objMatch = src.match(/defineProps\(\s*\{([^}]*)\}\s*\)/s);
  if (objMatch) {
    return parseOptionsLikeBody(objMatch[1]);
  }

  // `defineProps(['name', 'count'])`
  const arrMatch = src.match(/defineProps\(\s*\[([^\]]*)\]\s*\)/s);
  if (arrMatch) {
    return arrMatch[1]
      .split(',')
      .map((s) => s.trim().replace(/['"`]/g, ''))
      .filter(Boolean)
      .map((name) => ({ name, type: null, required: false, default: null }));
  }

  return null;
}

function extractOptionsProps(src) {
  const m = src.match(/props\s*:\s*\{([^}]*)\}/s);
  return m ? parseOptionsLikeBody(m[1]) : null;
}

function parseOptionsLikeBody(body) {
  const props = [];
  const entries = body.split(/,\s*(?=\w+\s*:)/);
  for (const entry of entries) {
    const m = entry.match(/^\s*(\w+)\s*:\s*(.*)$/s);
    if (!m) continue;
    const name = m[1];
    const detail = m[2];
    const type = (detail.match(/type\s*:\s*([\w\[\]|]+)/) || [, null])[1];
    const required = /required\s*:\s*true/.test(detail);
    const defaultMatch = detail.match(/default\s*:\s*([^,}]+)/);
    props.push({
      name,
      type,
      required,
      default: defaultMatch ? defaultMatch[1].trim() : null,
    });
  }
  return props;
}

async function parseReact(code, id, isTs) {
  const { parse } = await import('@babel/parser');
  const ast = parse(code, {
    sourceType: 'module',
    plugins: [isTs ? 'typescript' : 'flow', 'jsx'].filter(Boolean),
  });

  const props = [];

  for (const node of ast.program.body) {
    if (
      node.type === 'ExportDefaultDeclaration' ||
      node.type === 'ExportNamedDeclaration'
    ) {
      const decl =
        node.type === 'ExportDefaultDeclaration' ? node.declaration : node.declaration;
      collectFunctionProps(decl, props);
    } else if (node.type === 'FunctionDeclaration' || node.type === 'VariableDeclaration') {
      collectFunctionProps(node, props);
    } else if (node.type === 'TSInterfaceDeclaration' || node.type === 'TSTypeAliasDeclaration') {
      collectTsInterfaceProps(node, props);
    }
  }

  return {
    file: relativeFile(id),
    name: componentName(id),
    kind: 'react',
    props,
  };
}

function collectFunctionProps(node, props) {
  if (!node) return;
  let params = null;

  if (node.type === 'FunctionDeclaration') params = node.params;
  if (node.type === 'ArrowFunctionExpression') params = node.params;
  if (node.type === 'VariableDeclaration' && node.declarations?.[0]?.init?.params) {
    params = node.declarations[0].init.params;
  }
  if (!params || params.length === 0) return;

  const first = params[0];
  if (first.type === 'ObjectPattern') {
    for (const prop of first.properties) {
      if (prop.type === 'ObjectProperty' && prop.key?.name) {
        props.push({
          name: prop.key.name,
          type: null,
          required: prop.value?.type !== 'AssignmentPattern',
          default: prop.value?.type === 'AssignmentPattern' ? '<expr>' : null,
        });
      }
    }
  }
}

function collectTsInterfaceProps(node, props) {
  const body = node.body?.body || node.typeAnnotation?.members || [];
  for (const member of body) {
    if (member.type !== 'TSPropertySignature' || !member.key?.name) continue;
    props.push({
      name: member.key.name,
      type: member.typeAnnotation?.typeAnnotation?.type || null,
      required: !member.optional,
      default: null,
    });
  }
}

async function parseSvelte(code, id) {
  let parse;
  try {
    ({ parse } = await import('svelte/compiler'));
  } catch {
    return { file: relativeFile(id), name: componentName(id), kind: 'svelte', props: [] };
  }

  const props = [];

  // Svelte 3/4 — `export let foo = default;`
  for (const match of code.matchAll(/export\s+let\s+(\w+)(?:\s*=\s*([^;]+))?\s*;/g)) {
    const [, name, def] = match;
    props.push({
      name,
      type: null,
      required: !def,
      default: def ? def.trim() : null,
    });
  }

  // Svelte 5 — `let { foo, bar = 0 } = $props();`
  const runeMatch = code.match(/let\s*\{([^}]*)\}\s*=\s*\$props\(\)/);
  if (runeMatch) {
    for (const entry of runeMatch[1].split(',')) {
      const t = entry.trim();
      if (!t) continue;
      const [name, def] = t.split('=').map((s) => s.trim());
      props.push({ name, type: null, required: def === undefined, default: def ?? null });
    }
  }

  return {
    file: relativeFile(id),
    name: componentName(id),
    kind: 'svelte',
    props,
  };
}

function relativeFile(id) {
  return relative(process.cwd(), id);
}

function componentName(id) {
  return basename(id, extname(id));
}
