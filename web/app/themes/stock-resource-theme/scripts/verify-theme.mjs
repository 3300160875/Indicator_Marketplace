import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('..', import.meta.url).pathname;

const requiredFiles = [
  'style.css',
  'functions.php',
  'theme.json',
  'templates/index.php',
  'templates/front-page.php',
  'templates/page.php',
  'templates/404.php',
  'partials/header.php',
  'partials/footer.php',
  'assets/css/theme.css',
  'assets/js/theme.ts',
  'scripts/build.mjs',
];

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function read(relativePath) {
  return readFileSync(join(root, relativePath), 'utf8');
}

function walk(directory) {
  const files = [];
  for (const entry of readdirSync(directory)) {
    const path = join(directory, entry);
    const stat = statSync(path);
    if (stat.isDirectory()) {
      files.push(...walk(path));
    } else {
      files.push(path);
    }
  }

  return files;
}

for (const file of requiredFiles) {
  assert(statSync(join(root, file)).isFile(), `${file} must exist`);
}

const style = read('style.css');
assert(style.includes('Theme Name: Stock Resource Theme'), 'style.css must declare the theme name');
assert(style.includes('Requires PHP: 8.3'), 'style.css must declare the PHP requirement');

const themeJson = JSON.parse(read('theme.json'));
assert(themeJson.version === 3, 'theme.json must use schema version 3');
assert(themeJson.settings?.layout?.contentSize, 'theme.json must define a content layout size');
assert(Array.isArray(themeJson.settings?.color?.palette), 'theme.json must define a color palette');

const functions = read('functions.php');
assert(functions.includes('add_theme_support'), 'functions.php must register theme support');
assert(functions.includes('wp_enqueue_style'), 'functions.php must enqueue the theme stylesheet');
assert(functions.includes('wp_enqueue_script'), 'functions.php must enqueue the theme script');

for (const file of walk(root)) {
  if (!file.endsWith('.php') && !file.endsWith('.js') && !file.endsWith('.ts') && !file.endsWith('.css')) {
    continue;
  }

  const contents = readFileSync(file, 'utf8');
  assert(!/SELECT\s+.*sr_/i.test(contents), `${file} must not query sr_* tables directly`);
  assert(!/wpdb\s*->/i.test(contents), `${file} must not use wpdb directly`);
}

console.log('stock-resource-theme verification: ok');
