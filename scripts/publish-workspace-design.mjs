import { cp, mkdir, readFile, rm } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = fileURLToPath(new URL('../', import.meta.url));
const source = path.join(root, 'resources/workspace-design/dist');
const target = path.join(root, 'public/workspace-design-build');
const manifest = JSON.parse(await readFile(path.join(source, '.vite/manifest.json'), 'utf8'));
const entry = manifest['src/main.tsx'];
if (!entry?.file || !entry.isEntry) throw new Error('Workspace Design build entry is missing. Run the isolated build first.');
for (const file of [entry.file, ...(entry.css ?? [])]) {
  if (path.isAbsolute(file) || file.split(/[\\/]/).includes('..')) throw new Error('Unsafe asset path in build manifest.');
  await readFile(path.join(source, file));
}
await mkdir(path.dirname(target), { recursive: true });
await rm(target, { recursive: true, force: true });
await cp(source, target, { recursive: true });
const published = JSON.parse(await readFile(path.join(target, '.vite/manifest.json'), 'utf8'));
if (published['src/main.tsx'].file !== entry.file) throw new Error('Published asset manifest mismatch.');

// AnyBuild exports the whole application tree. Its Linux builder can drop these
// build-only dependencies; preserve the local Windows install for editor tooling.
if (process.platform !== 'win32') {
  await rm(path.join(root, 'resources/workspace-design/node_modules'), { recursive: true, force: true });
}

console.log(`Workspace Design published: ${entry.file}`);
