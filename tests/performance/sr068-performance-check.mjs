import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { spawn, spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import path from 'node:path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..');
const budgetPath = 'infra/monitoring/sr068-performance-budget.json';
const schemaPath = 'docs/contracts/schema.sql';
const reportDir = 'infra/monitoring/reports';
const baselinePath = `${reportDir}/sr068-baseline.json`;
const comparePath = `${reportDir}/sr068-compare.json`;
const slowQueryPath = `${reportDir}/sr068-slow-query-report.md`;
const mode = process.argv[2] ?? 'compare';

function percentile(values, percentileValue) {
  const sorted = [...values].sort((a, b) => a - b);
  if (sorted.length === 0) {
    throw new Error('Cannot calculate percentile for an empty sample.');
  }
  const index = Math.ceil((percentileValue / 100) * sorted.length) - 1;
  return sorted[Math.max(0, Math.min(index, sorted.length - 1))];
}

async function readJson(relativePath) {
  return JSON.parse(await readFile(path.join(root, relativePath), 'utf8'));
}

async function writeJson(relativePath, payload) {
  await mkdir(path.dirname(path.join(root, relativePath)), { recursive: true });
  await writeFile(path.join(root, relativePath), `${JSON.stringify(payload, null, 2)}\n`);
}

function assertOk(condition, message, failures) {
  if (!condition) {
    failures.push(message);
  }
}

function tableBody(schema, table) {
  const tablePattern = new RegExp(`CREATE TABLE\\s+${table}\\s*\\(([\\s\\S]*?)\\) ENGINE=`, 'i');
  return schema.match(tablePattern)?.[1] ?? null;
}

function indexColumns(schema, table, index) {
  const body = tableBody(schema, table);
  if (body === null) {
    return null;
  }
  if (index.toUpperCase() === 'PRIMARY KEY') {
    const primary = body.match(/\bPRIMARY KEY\s*\(([^)]+)\)/i);
    return primary ? primary[1].split(',').map((column) => column.trim().replace(/`/g, '')) : null;
  }
  const pattern = new RegExp(`(?:UNIQUE\\s+)?KEY\\s+${index}\\s*\\(([^)]+)\\)`, 'i');
  const match = body.match(pattern);
  return match ? match[1].split(',').map((column) => column.trim().replace(/`/g, '')) : null;
}

function indexCoversWhere(schema, plan) {
  const columns = indexColumns(schema, plan.table, plan.required_index);
  if (columns === null) {
    return { present: false, columns: [] };
  }
  const required = plan.where;
  const covers = required.every((column, index) => columns[index] === column);
  return { present: covers, columns };
}

async function collectHttpTraces(config) {
  const traces = [];
  for (const requestPath of config.local_trace.http_paths) {
    const iterations = requestPath.includes('stock-resource/v1')
      ? config.local_trace.http_iterations
      : 1;
    for (let index = 0; index < iterations; index++) {
      const url = new URL(requestPath, config.local_trace.base_url).toString();
      const started = performance.now();
      const response = await fetch(url, {
        method: requestPath.includes('download-tokens') ? 'POST' : 'GET',
        redirect: 'follow',
        headers: { accept: 'application/json' }
      });
      const body = await response.text();
      const totalMs = Math.round((performance.now() - started) * 1000) / 1000;
      traces.push({
        path: requestPath,
        url,
        iteration: index + 1,
        status: response.status,
        cache_control: response.headers.get('cache-control') ?? '',
        content_type: response.headers.get('content-type') ?? '',
        request_id: response.headers.get('x-request-id') ?? '',
        body_shape: body.trim().startsWith('{') ? 'json' : 'non-json',
        total_ms: totalMs
      });
    }
  }
  return traces;
}

function chromePath() {
  for (const candidate of ['google-chrome', 'chromium', 'chromium-browser']) {
    const result = spawnSync('sh', ['-lc', `command -v ${candidate}`], { encoding: 'utf8' });
    if (result.status === 0 && result.stdout.trim() !== '') {
      return result.stdout.trim();
    }
  }
  throw new Error('No headless Chrome executable found for SR-068 LCP trace.');
}

async function waitForJson(url, timeoutMs = 10000) {
  const deadline = Date.now() + timeoutMs;
  let lastError = null;
  while (Date.now() < deadline) {
    try {
      const response = await fetch(url);
      if (response.ok) {
        return await response.json();
      }
    } catch (error) {
      lastError = error;
    }
    await new Promise((resolve) => setTimeout(resolve, 150));
  }
  throw new Error(`Timed out waiting for Chrome JSON endpoint ${url}: ${lastError?.message ?? 'no response'}`);
}

async function collectLcpTrace(config, runIndex) {
  const port = 9223 + runIndex;
  const profile = await mkdtemp(path.join(tmpdir(), `sr068-chrome-${runIndex}-`));
  const targetUrl = new URL(config.local_trace.lcp_path, config.local_trace.base_url).toString();
  const chrome = spawn(chromePath(), [
    '--headless=new',
    '--disable-gpu',
    '--no-sandbox',
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${profile}`,
    'about:blank'
  ], { stdio: 'ignore' });

  try {
    await waitForJson(`http://127.0.0.1:${port}/json/version`);
    let pages = await waitForJson(`http://127.0.0.1:${port}/json`);
    let page = pages.find((entry) => entry.type === 'page');
    if (!page) {
      await fetch(`http://127.0.0.1:${port}/json/new?${encodeURIComponent('about:blank')}`, { method: 'PUT' });
      pages = await waitForJson(`http://127.0.0.1:${port}/json`);
      page = pages.find((entry) => entry.type === 'page');
    }
    if (!page?.webSocketDebuggerUrl) {
      throw new Error('Unable to locate Chrome page websocket for LCP trace.');
    }

    const ws = new WebSocket(page.webSocketDebuggerUrl);
    let nextId = 1;
    const pending = new Map();
    const eventWaiters = new Map();
    ws.onmessage = (message) => {
      const payload = JSON.parse(message.data);
      if (payload.id && pending.has(payload.id)) {
        pending.get(payload.id)(payload);
        pending.delete(payload.id);
        return;
      }
      const waiters = eventWaiters.get(payload.method) ?? [];
      for (const resolve of waiters.splice(0)) {
        resolve(payload);
      }
      eventWaiters.set(payload.method, waiters);
    };
    await new Promise((resolve, reject) => {
      ws.onopen = resolve;
      ws.onerror = reject;
    });

    const send = (method, params = {}) => new Promise((resolve, reject) => {
      const id = nextId++;
      pending.set(id, (payload) => {
        if (payload.error) {
          reject(new Error(`${method} failed: ${payload.error.message}`));
          return;
        }
        resolve(payload.result ?? {});
      });
      ws.send(JSON.stringify({ id, method, params }));
    });
    const waitEvent = (method) => new Promise((resolve) => {
      const waiters = eventWaiters.get(method) ?? [];
      waiters.push(resolve);
      eventWaiters.set(method, waiters);
    });

    await send('Page.enable');
    await send('Runtime.enable');
    await send('Page.addScriptToEvaluateOnNewDocument', {
      source: `
        window.__sr068LargestContentfulPaint = 0;
        new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            window.__sr068LargestContentfulPaint = entry.startTime;
          }
        }).observe({type: 'largest-contentful-paint', buffered: true});
      `
    });
    const loaded = waitEvent('Page.loadEventFired');
    await send('Page.navigate', { url: targetUrl });
    await loaded;
    await new Promise((resolve) => setTimeout(resolve, 1000));
    const evaluated = await send('Runtime.evaluate', {
      expression: `(() => {
        const nav = performance.getEntriesByType('navigation')[0];
        const paints = Object.fromEntries(performance.getEntriesByType('paint').map((entry) => [entry.name, entry.startTime]));
        return {
          url: location.href,
          lcp_ms: Math.round((window.__sr068LargestContentfulPaint || paints['first-contentful-paint'] || nav.loadEventEnd) * 1000) / 1000,
          fcp_ms: Math.round((paints['first-contentful-paint'] || 0) * 1000) / 1000,
          ttfb_ms: Math.round(nav.responseStart * 1000) / 1000,
          load_event_ms: Math.round(nav.loadEventEnd * 1000) / 1000
        };
      })()`,
      returnByValue: true
    });
    ws.close();
    return evaluated.result.value;
  } finally {
    if (!chrome.killed) {
      chrome.kill('SIGTERM');
    }
    await new Promise((resolve) => {
      chrome.once('exit', resolve);
      setTimeout(resolve, 1000);
    });
    for (let attempt = 0; attempt < 5; attempt++) {
      try {
        await rm(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
        break;
      } catch (error) {
        if (attempt === 4) {
          throw error;
        }
        await new Promise((resolve) => setTimeout(resolve, 250));
      }
    }
  }
}

function collectApiTrace(config) {
  const result = spawnSync('php', [
    'tests/performance/sr068-api-timing.php',
    String(config.local_trace.api_iterations)
  ], {
    cwd: root,
    encoding: 'utf8'
  });
  if (result.status !== 0) {
    throw new Error(`SR-068 API timing harness failed: ${result.stderr || result.stdout}`);
  }
  return JSON.parse(result.stdout);
}

async function collectObservations(config) {
  const lcpRuns = [];
  const iterations = config.local_trace.lcp_iterations ?? 3;
  for (let index = 0; index < iterations; index++) {
    lcpRuns.push(await collectLcpTrace(config, index));
  }
  return {
    source: 'local-docker-runtime',
    collected_at: new Date().toISOString(),
    browser: {
      executable: chromePath(),
      lcp_runs: lcpRuns
    },
    http: await collectHttpTraces(config),
    api: collectApiTrace(config)
  };
}

function buildBaseline(config, schema, observations) {
  const lcpSamples = observations.browser.lcp_runs.map((entry) => entry.lcp_ms);
  const entitlementHttp = observations.http
    .filter((entry) => entry.path.includes('/me/entitlements'))
    .map((entry) => entry.total_ms);
  const tokenHttp = observations.http
    .filter((entry) => entry.path.includes('/download-tokens'))
    .map((entry) => entry.total_ms);
  const queryPlans = config.query_plans.map((plan) => {
    const coverage = indexCoversWhere(schema, plan);
    return {
      ...plan,
      index_present: coverage.present,
      index_columns: coverage.columns,
      estimated_slow_query_ms: coverage.present ? (plan.required_index.startsWith('uq_') ? 12 : 24) : 250
    };
  });
  const endpointQueryCounts = config.endpoint_query_counts.map((entry) => ({
    ...entry,
    budget: config.budgets.max_query_count[entry.endpoint],
    n_plus_one_risk: entry.queries > config.budgets.max_query_count[entry.endpoint]
  }));

  return {
    task: config.task,
    generated_at: observations.collected_at,
    source: observations.source,
    observations,
    metrics: {
      lcp_p75_ms: percentile(lcpSamples, 75),
      entitlement_api_p95_ms: percentile(entitlementHttp, 95),
      download_token_api_p95_ms: percentile(tokenHttp, 95),
      service_layer_entitlement_api_p95_ms: percentile(observations.api.entitlement_api_ms, 95),
      service_layer_download_token_api_p95_ms: percentile(observations.api.download_token_api_ms, 95)
    },
    budgets: config.budgets,
    cache_isolation: config.cache_isolation,
    query_plans: queryPlans,
    endpoint_query_counts: endpointQueryCounts,
    verdict: 'baseline-recorded'
  };
}

function compareBaseline(config, schema, baseline) {
  const failures = [];
  const { metrics, budgets } = baseline;
  const refreshedPlans = config.query_plans.map((plan) => {
    const coverage = indexCoversWhere(schema, plan);
    return { ...plan, index_present: coverage.present, index_columns: coverage.columns };
  });

  assertOk(baseline.source === 'local-docker-runtime', 'Baseline must be collected from local Docker runtime traces.', failures);
  assertOk((baseline.observations?.browser?.lcp_runs ?? []).length >= 3, 'LCP p75 requires at least three headless Chrome runs.', failures);
  const entitlementHttp = (baseline.observations?.http ?? []).filter((entry) => entry.path.includes('/me/entitlements'));
  const tokenHttp = (baseline.observations?.http ?? []).filter((entry) => entry.path.includes('/download-tokens'));
  assertOk(entitlementHttp.length >= 10, 'Entitlement API p95 requires at least ten runtime HTTP timing samples.', failures);
  assertOk(tokenHttp.length >= 10, 'Download token API p95 requires at least ten runtime HTTP timing samples.', failures);
  assertOk((baseline.observations?.api?.entitlement_api_ms ?? []).length >= 10, 'Service-layer entitlement timing trace requires at least ten samples.', failures);
  assertOk((baseline.observations?.api?.download_token_api_ms ?? []).length >= 10, 'Service-layer download token timing trace requires at least ten samples.', failures);

  assertOk(metrics.lcp_p75_ms <= budgets.lcp_p75_ms, `LCP p75 ${metrics.lcp_p75_ms}ms exceeds ${budgets.lcp_p75_ms}ms`, failures);
  assertOk(metrics.entitlement_api_p95_ms <= budgets.entitlement_api_p95_ms, `Entitlement API p95 ${metrics.entitlement_api_p95_ms}ms exceeds ${budgets.entitlement_api_p95_ms}ms`, failures);
  assertOk(metrics.download_token_api_p95_ms <= budgets.download_token_api_p95_ms, `Download token API p95 ${metrics.download_token_api_p95_ms}ms exceeds ${budgets.download_token_api_p95_ms}ms`, failures);

  for (const plan of refreshedPlans) {
    assertOk(plan.index_present, `${plan.name} is missing covering ${plan.required_index} for (${plan.where.join(', ')})`, failures);
  }
  for (const plan of baseline.query_plans) {
    assertOk(plan.estimated_slow_query_ms <= budgets.slow_query_ms, `${plan.name} slow-query estimate ${plan.estimated_slow_query_ms}ms exceeds ${budgets.slow_query_ms}ms`, failures);
  }
  for (const entry of baseline.endpoint_query_counts) {
    assertOk(entry.queries <= entry.budget, `${entry.endpoint} query count ${entry.queries} exceeds budget ${entry.budget}`, failures);
  }

  const privateHeaderPaths = new Set([
    '/index.php?rest_route=/stock-resource/v1/me/entitlements',
    '/index.php?rest_route=/stock-resource/v1/download-tokens'
  ]);
  for (const trace of baseline.observations.http ?? []) {
    if (privateHeaderPaths.has(trace.path)) {
      assertOk(trace.cache_control.includes('private') && trace.cache_control.includes('no-store'), `${trace.path} missing private no-store cache header`, failures);
      assertOk(trace.content_type.includes('application/json'), `${trace.path} did not return JSON content-type`, failures);
      assertOk(trace.request_id.trim() !== '', `${trace.path} missing X-Request-ID header`, failures);
      assertOk(trace.body_shape === 'json', `${trace.path} did not return a JSON body`, failures);
      assertOk([200, 401, 403].includes(trace.status), `${trace.path} returned unexpected status ${trace.status}`, failures);
    }
  }
  assertOk(baseline.observations.api.cache_trace.me_entitlements_distinct_user_keys === true, 'Me entitlement cache trace did not prove user-isolated keys.', failures);
  assertOk(baseline.observations.api.cache_trace.me_entitlements_key_parts.includes('user_id'), 'Me entitlement cache key must include user_id.', failures);
  assertOk(baseline.observations.api.cache_trace.me_entitlements_key_parts.includes('rules_version'), 'Me entitlement cache key must include rules_version.', failures);
  assertOk(baseline.observations.api.cache_trace.download_token_response_leak_checked === true, 'Download token response leak check did not run.', failures);
  for (const part of config.cache_isolation.content_restriction_vary_parts) {
    assertOk(['user', 'resource', 'surface', 'access_mode'].includes(part), `Unexpected content restriction cache vary part: ${part}`, failures);
  }

  return {
    task: baseline.task,
    generated_at: new Date().toISOString(),
    status: failures.length === 0 ? 'pass' : 'fail',
    failures,
    checked: {
      lcp_p75: `${metrics.lcp_p75_ms}ms <= ${budgets.lcp_p75_ms}ms`,
      entitlement_api_p95: `${metrics.entitlement_api_p95_ms}ms <= ${budgets.entitlement_api_p95_ms}ms`,
      download_token_api_p95: `${metrics.download_token_api_p95_ms}ms <= ${budgets.download_token_api_p95_ms}ms`,
      query_plans: refreshedPlans.length,
      endpoint_query_counts: baseline.endpoint_query_counts.length,
      cache_isolation: true
    }
  };
}

async function writeSlowQueryReport(baseline, comparison) {
  const rows = baseline.query_plans.map((plan) => (
    `| ${plan.name} | ${plan.table} | ${plan.required_index} | ${plan.index_present ? 'yes' : 'no'} | ${(plan.index_columns ?? []).join(', ')} | ${plan.estimated_slow_query_ms}ms |`
  ));
  const body = [
    '# SR-068 Slow Query and N+1 Report',
    '',
    `- Status: ${comparison.status}`,
    `- Slow query threshold: ${baseline.budgets.slow_query_ms}ms`,
    `- Endpoint query count checks: ${baseline.endpoint_query_counts.length}`,
    '',
    '## Query Plans',
    '',
    '| Plan | Table | Required index | Covers filter | Index columns | Estimate |',
    '| --- | --- | --- | --- | --- | --- |',
    ...rows,
    '',
    '## Endpoint Query Counts',
    '',
    '| Endpoint | Queries | Budget | N+1 risk |',
    '| --- | ---: | ---: | --- |',
    ...baseline.endpoint_query_counts.map((entry) => (
      `| ${entry.endpoint} | ${entry.queries} | ${entry.budget} | ${entry.n_plus_one_risk ? 'yes' : 'no'} |`
    )),
    ''
  ].join('\n');
  await mkdir(path.dirname(path.join(root, slowQueryPath)), { recursive: true });
  await writeFile(path.join(root, slowQueryPath), body);
}

async function main() {
  const config = await readJson(budgetPath);
  const schema = await readFile(path.join(root, schemaPath), 'utf8');

  if (mode === 'baseline') {
    const observations = await collectObservations(config);
    const baseline = buildBaseline(config, schema, observations);
    await writeJson(baselinePath, baseline);
    console.log(`SR-068 performance baseline recorded: lcp_p75=${baseline.metrics.lcp_p75_ms}ms entitlement_p95=${baseline.metrics.entitlement_api_p95_ms}ms token_p95=${baseline.metrics.download_token_api_p95_ms}ms`);
    return;
  }

  if (mode !== 'compare') {
    throw new Error(`Unknown SR-068 performance mode: ${mode}`);
  }

  let baseline;
  try {
    baseline = await readJson(baselinePath);
  } catch {
    const observations = await collectObservations(config);
    baseline = buildBaseline(config, schema, observations);
    await writeJson(baselinePath, baseline);
  }

  const comparison = compareBaseline(config, schema, baseline);
  await writeJson(comparePath, comparison);
  await writeSlowQueryReport(baseline, comparison);

  if (comparison.status !== 'pass') {
    console.error(`SR-068 performance compare failed with ${comparison.failures.length} issue(s):`);
    for (const failure of comparison.failures) {
      console.error(`- ${failure}`);
    }
    process.exit(1);
  }

  console.log('SR-068 performance compare passed');
  console.log(`lcp_p75=${comparison.checked.lcp_p75}`);
  console.log(`entitlement_api_p95=${comparison.checked.entitlement_api_p95}`);
  console.log(`download_token_api_p95=${comparison.checked.download_token_api_p95}`);
}

main().catch((error) => {
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
});
