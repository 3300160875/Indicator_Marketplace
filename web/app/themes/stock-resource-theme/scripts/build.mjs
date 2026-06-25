import { copyFileSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('..', import.meta.url).pathname;
copyFileSync(join(root, 'assets/js/theme.ts'), join(root, 'assets/js/theme.js'));
console.log('stock-resource-theme build: ok');
