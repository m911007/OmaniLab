// ينسخ ناتج البناء إلى public/ في Laravel: assets/ كما هي، وindex.html باسم spa.html (فلا يتعارض مع public/index.php).
import { cpSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const dist = join(root, 'dist');
const pub = join(root, '..', 'public');

rmSync(join(pub, 'assets'), { recursive: true, force: true });
mkdirSync(join(pub, 'assets'), { recursive: true });
cpSync(join(dist, 'assets'), join(pub, 'assets'), { recursive: true });
writeFileSync(join(pub, 'spa.html'), readFileSync(join(dist, 'index.html')));
console.log('✓ نُشرت الواجهة إلى public/ (assets/ + spa.html)');
