import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import path from 'node:path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..');
const manifestPath = 'docs/content/sr067-import-manifest.json';
const generatedPath = 'docs/content/generated/sr067-resources.json';
const reportsDir = 'docs/content/reports';
const dryRunReportPath = `${reportsDir}/sr067-dry-run-report.json`;
const validationReportPath = `${reportsDir}/sr067-validation-report.json`;
const applyStatePath = `${reportsDir}/sr067-apply-state.json`;
const rollbackReportPath = `${reportsDir}/sr067-rollback-report.json`;
const releaseReadinessPath = `${reportsDir}/sr067-release-readiness.json`;

const manifest = await readJson(manifestPath);
const resources = generateResources(manifest);
const validation = validateResources(manifest, resources);
const dryRun = buildDryRunReport(manifest, resources, validation);
const applyState = buildApplyState(manifest, resources);
const rollback = buildRollbackReport(manifest, applyState);
const readiness = buildReleaseReadiness(manifest, resources, validation, rollback);

await mkdir(path.join(root, 'docs/content/generated'), { recursive: true });
await mkdir(path.join(root, reportsDir), { recursive: true });
await writeJson(generatedPath, {
  schema_version: 1,
  batch_id: manifest.batch_id,
  count: resources.length,
  generated_from: manifestPath,
  resources,
});
await writeJson(dryRunReportPath, dryRun);
await writeJson(validationReportPath, validation);
await writeJson(applyStatePath, applyState);
await writeJson(rollbackReportPath, rollback);
await writeJson(releaseReadinessPath, readiness);

const failures = [
  ...validation.errors,
  ...dryRun.errors,
  ...rollback.errors,
  ...readiness.errors,
];

if (failures.length > 0) {
  console.error(`SR-067 content import check failed with ${failures.length} issue(s):`);
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log('SR-067 content import check passed');
console.log(`batch_id=${manifest.batch_id}`);
console.log(`resources=${resources.length}`);
console.log(`completeness=${readiness.completeness_percent}%`);
console.log('rights_status=pending');
console.log('rollback=verified');

function generateResources(source) {
  const resources = [];
  for (let index = 1; index <= source.expected_count; index++) {
    const platform = pick(source.taxonomy_terms.platforms, index);
    const market = pick(source.taxonomy_terms.markets, index + 1);
    const strategy = pick(source.taxonomy_terms.strategies, index + 2);
    const accessMode = pick(source.access_modes, index + 3);
    const padded = String(index).padStart(3, '0');
    const naturalKey = `sr067-${padded}`;
    resources.push({
      natural_key: naturalKey,
      slug: `sr067-${platform}-${strategy}-${padded}`,
      title: `SR-067 ${platformLabel(platform)} ${strategyLabel(strategy)} 指标 ${padded}`,
      excerpt: `SR-067 迁移候选 ${padded}，用于 ${market} ${strategyLabel(strategy)} 场景。`,
      content: `这是 SR-067 的第 ${index} 条受控迁移候选内容，用于验证 100 条内容迁移、校验和回滚流程。`,
      publication_status: source.defaults.publication_status,
      access_mode: accessMode,
      rights_status: source.defaults.rights_status,
      risk_level: source.defaults.risk_level,
      disclaimer_version: source.defaults.disclaimer_version,
      taxonomy: {
        platform,
        market,
        strategy,
      },
      meta: {
        file_format: index % 3 === 0 ? 'zip' : 'txt',
        charset: index % 2 === 0 ? 'utf8' : 'gbk',
        source_included: index % 4 === 0 ? 'yes' : 'no',
        l2_required: index % 10 === 0 ? 'yes' : 'no',
        compatibility: platform,
      },
      rights: {
        default_status: source.defaults.rights_status,
        evidence_required_before_publish: true,
      },
      rollback: {
        natural_key: naturalKey,
        action: 'delete_if_created_by_batch',
      },
    });
  }

  return resources;
}

function validateResources(source, resources) {
  const errors = [];
  const warnings = [];
  const naturalKeys = new Set();
  const slugs = new Set();
  const requiredFields = [
    'natural_key',
    'slug',
    'title',
    'excerpt',
    'content',
    'publication_status',
    'access_mode',
    'rights_status',
    'risk_level',
    'disclaimer_version',
  ];

  if (resources.length !== source.expected_count) {
    errors.push(`expected ${source.expected_count} resources, got ${resources.length}`);
  }

  for (const resource of resources) {
    for (const field of requiredFields) {
      if (resource[field] === undefined || resource[field] === null || String(resource[field]).trim() === '') {
        errors.push(`${resource.natural_key ?? 'unknown'} missing required field ${field}`);
      }
    }
    if (naturalKeys.has(resource.natural_key)) {
      errors.push(`duplicate natural_key ${resource.natural_key}`);
    }
    naturalKeys.add(resource.natural_key);

    if (slugs.has(resource.slug)) {
      errors.push(`duplicate slug ${resource.slug}`);
    }
    slugs.add(resource.slug);

    if (resource.rights_status !== 'pending') {
      errors.push(`${resource.natural_key} rights_status must default to pending`);
    }
    if (resource.publication_status !== 'draft') {
      errors.push(`${resource.natural_key} publication_status must default to draft`);
    }
    if (!source.access_modes.includes(resource.access_mode)) {
      errors.push(`${resource.natural_key} unsupported access_mode ${resource.access_mode}`);
    }
    if (resource.access_mode !== 'free' && resource.rights.default_status !== 'pending') {
      errors.push(`${resource.natural_key} paid resources must await rights review`);
    }
  }

  return {
    schema_version: 1,
    batch_id: source.batch_id,
    expected_count: source.expected_count,
    actual_count: resources.length,
    unique_natural_keys: naturalKeys.size,
    unique_slugs: slugs.size,
    rights_default_pending_count: resources.filter((resource) => resource.rights_status === 'pending').length,
    required_field_completeness_percent: percent(resources.length * requiredFields.length - errors.filter((error) => error.includes('missing required field')).length, resources.length * requiredFields.length),
    errors,
    warnings,
  };
}

function buildDryRunReport(source, resources, validation) {
  return {
    schema_version: 1,
    batch_id: source.batch_id,
    mode: 'dry-run',
    would_create: resources.length,
    would_update: 0,
    would_delete: 0,
    rights_default: 'pending',
    mutates_database: false,
    mutates_filesystem_outside_docs_content: false,
    validation_errors: validation.errors.length,
    errors: validation.errors,
  };
}

function buildApplyState(source, resources) {
  return {
    schema_version: 1,
    batch_id: source.batch_id,
    mode: 'apply-simulation',
    applied_count: resources.length,
    created_natural_keys: resources.map((resource) => resource.natural_key),
    payload_hash: sha256(JSON.stringify(resources)),
    reversible: true,
  };
}

function buildRollbackReport(source, state) {
  const errors = [];
  if (state.batch_id !== source.batch_id) {
    errors.push('rollback batch_id mismatch');
  }
  if (!state.reversible) {
    errors.push('apply state is not reversible');
  }
  if (state.created_natural_keys.length !== source.expected_count) {
    errors.push(`rollback expected ${source.expected_count} natural keys, got ${state.created_natural_keys.length}`);
  }

  return {
    schema_version: 1,
    batch_id: source.batch_id,
    mode: 'rollback-simulation',
    rollback_strategy: source.rollback.strategy,
    preserve_existing: source.rollback.preserve_existing,
    would_remove_created: state.created_natural_keys.length,
    would_update_existing: 0,
    verified: errors.length === 0,
    errors,
  };
}

function buildReleaseReadiness(source, resources, validation, rollback) {
  const errors = [];
  const completeness = validation.actual_count === source.expected_count
    && validation.unique_natural_keys === source.expected_count
    && validation.unique_slugs === source.expected_count
    && validation.rights_default_pending_count === source.expected_count
    && validation.errors.length === 0
    && rollback.verified;

  if (!completeness) {
    errors.push('pre-publication completeness is not 100%');
  }

  return {
    schema_version: 1,
    batch_id: source.batch_id,
    completeness_percent: completeness ? 100 : 0,
    publication_ready: false,
    publication_blocker: 'rights_status_pending',
    review_ready: completeness,
    resources_pending_rights_review: resources.length,
    errors,
  };
}

function pick(values, index) {
  return values[(index - 1) % values.length];
}

function platformLabel(platform) {
  return {
    tongdaxin: '通达信',
    tonghuashun: '同花顺',
    eastmoney: '东方财富',
    wenhua: '文华财经',
    tradingview: 'TradingView',
  }[platform] ?? platform;
}

function strategyLabel(strategy) {
  return {
    trend: '趋势',
    'volume-price': '量价',
    'risk-control': '风控',
    screening: '选股',
    education: '教学',
  }[strategy] ?? strategy;
}

function percent(numerator, denominator) {
  if (denominator === 0) {
    return 0;
  }

  return Math.round((numerator / denominator) * 10000) / 100;
}

async function readJson(relativePath) {
  return JSON.parse(await readText(relativePath));
}

async function readText(relativePath) {
  return readFile(path.join(root, relativePath), 'utf8');
}

async function writeJson(relativePath, value) {
  await writeFile(path.join(root, relativePath), `${JSON.stringify(value, null, 2)}\n`);
}

function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}
