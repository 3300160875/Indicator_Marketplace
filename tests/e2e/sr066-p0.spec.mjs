import { expect, test } from '@playwright/test';

const e2eKey = process.env.SR_E2E_KEY || 'local-e2e-only';

test.describe('SR-066 P0 marketplace flow', () => {
  test('guest can buy, submit proof, receive approval, download, and refund revokes access', async ({ page, request }, testInfo) => {
    const runId = `sr066-${testInfo.project.name}-${Date.now()}`;
    const pageUrl = `/?sr_e2e_p0=${encodeURIComponent(runId)}&sr_e2e_key=${encodeURIComponent(e2eKey)}`;

    await page.goto(pageUrl);
    await expect(page.getByTestId('resource-title')).toContainText('TDX Trend Indicator');
    await expect(page.getByTestId('guest-browse')).toHaveText('guest_ready');
    await expect(page.getByTestId('p0-state')).toContainText('"stage": "guest_ready"');

    await page.getByTestId('create-order').click();
    await expect(page.getByTestId('p0-state')).toContainText('"stage": "order_pending"');
    await expect(page.getByTestId('p0-state')).toContainText('"status": "pending"');

    await page.getByTestId('submit-proof').click();
    await expect(page.getByTestId('p0-state')).toContainText('"stage": "proof_submitted"');
    await expect(page.getByTestId('p0-state')).toContainText('"status": "submitted"');

    await page.getByTestId('approve-review').click();
    await expect(page.getByTestId('p0-state')).toContainText('"stage": "entitlement_active"');
    await expect(page.getByTestId('p0-state')).toContainText('"status": "active"');

    await page.getByTestId('issue-token').click();
    await expect(page.getByTestId('p0-state')).toContainText('"stage": "download_token_issued"');

    await Promise.all([
      page.waitForURL(/sr_e2e_download=ok/),
      page.getByTestId('download-file').click(),
    ]);
    await expect(page.getByTestId('download-status')).toHaveText('download_redirected');

    await page.goto(pageUrl);
    await page.getByTestId('refund-order').click();
    await expect(page.getByTestId('p0-state')).toContainText('"stage": "refunded"');
    await expect(page.getByTestId('p0-state')).toContainText('"status": "revoked"');

    const state = await request.get(`/?rest_route=${encodeURIComponent('/stock-resource-e2e/v1/p0/state')}&run_id=${encodeURIComponent(runId)}`, {
      headers: { 'x-sr-e2e-key': e2eKey },
    });
    expect(state.ok()).toBeTruthy();
    const body = await state.json();
    expect(body.data.order.status).toBe('refunded');
    expect(body.data.proof.status).toBe('submitted');
    expect(body.data.review.status).toBe('approved');
    expect(body.data.download.status).toBe('redirected');
    expect(body.data.entitlement.status).toBe('revoked');
    expect(body.data.refund.status).toBe('refunded');
  });
});
