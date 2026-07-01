<?php
declare(strict_types=1);

if (! function_exists('add_action')) {
    return;
}

function sr_e2e_runtime_enabled(): bool
{
    if (getenv('SR_E2E_ENABLED') !== '1') {
        return false;
    }

    if (trim((string) getenv('SR_E2E_KEY')) === '') {
        return false;
    }

    $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';

    return in_array($environment, ['local', 'development'], true);
}

if (! sr_e2e_runtime_enabled()) {
    return;
}

function sr_e2e_secret(): string
{
    return trim((string) getenv('SR_E2E_KEY'));
}

function sr_e2e_authorized(?WP_REST_Request $request = null): bool
{
    $expected = sr_e2e_secret();
    $actual = '';

    if ($request instanceof WP_REST_Request) {
        $actual = trim((string) $request->get_header('x-sr-e2e-key'));
    } elseif (isset($_GET['sr_e2e_key'])) {
        $actual = trim((string) $_GET['sr_e2e_key']);
    }

    return hash_equals($expected, $actual);
}

function sr_e2e_option_key(string $runId): string
{
    return 'sr_e2e_p0_run_' . md5($runId);
}

function sr_e2e_state(string $runId): array
{
    $state = get_option(sr_e2e_option_key($runId), []);

    return is_array($state) ? $state : [];
}

function sr_e2e_save_state(string $runId, array $state): array
{
    $state['run_id'] = $runId;
    $state['updated_at'] = gmdate(DATE_ATOM);
    update_option(sr_e2e_option_key($runId), $state, false);

    return $state;
}

function sr_e2e_request_run_id(WP_REST_Request $request): string
{
    $runId = sanitize_key((string) ($request->get_param('run_id') ?? ''));
    if ($runId === '') {
        return 'sr066-' . gmdate('YmdHis');
    }

    return $runId;
}

function sr_e2e_error(string $code, string $message, int $status = 400): WP_Error
{
    return new WP_Error($code, $message, ['status' => $status]);
}

function sr_e2e_ensure_edd(): void
{
    if (! function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugin = 'easy-digital-downloads/easy-digital-downloads.php';
    if (! is_plugin_active($plugin)) {
        $result = activate_plugin($plugin);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
    }

    if (function_exists('edd_setup_components')) {
        edd_setup_components();
    }

    if (function_exists('edd_register_component') && function_exists('edd_get_component') && ! edd_get_component('session')) {
        edd_register_component('session', [
            'schema' => '\\EDD\\Database\\Schemas\\Sessions',
            'table' => '\\EDD\\Database\\Tables\\Sessions',
            'query' => '\\EDD\\Database\\Queries\\Session',
            'object' => '\\EDD\\Sessions\\Session',
            'meta' => false,
        ]);
    }

    if (function_exists('edd_install_component_database_tables')) {
        edd_install_component_database_tables();
    }

    update_option('edd_settings', array_merge((array) get_option('edd_settings', []), [
        'test_mode' => '1',
        'currency' => 'USD',
    ]));
}

function sr_e2e_create_user(string $runId): int
{
    $login = 'sr066_' . substr(md5($runId), 0, 10);
    $email = $login . '@example.test';
    $existing = get_user_by('email', $email);
    if ($existing instanceof WP_User) {
        return (int) $existing->ID;
    }

    $userId = wp_create_user($login, wp_generate_password(20), $email);
    if (is_wp_error($userId)) {
        throw new RuntimeException($userId->get_error_message());
    }

    wp_update_user([
        'ID' => (int) $userId,
        'display_name' => 'SR-066 P0 Buyer',
    ]);

    return (int) $userId;
}

function sr_e2e_create_order(string $runId, array $state): array
{
    sr_e2e_ensure_edd();

    if (isset($state['order']['id'])) {
        return $state['order'];
    }

    if (! function_exists('edd_add_order')) {
        throw new RuntimeException('EDD order API is unavailable.');
    }

    $amount = 12.34;
    $productId = wp_insert_post([
        'post_type' => 'download',
        'post_status' => 'publish',
        'post_title' => 'SR-066 P0 Indicator ' . $runId,
        'post_name' => 'sr-066-p0-' . $runId,
    ], true);
    if (is_wp_error($productId)) {
        throw new RuntimeException($productId->get_error_message());
    }

    update_post_meta((int) $productId, 'edd_price', (string) $amount);

    $userId = sr_e2e_create_user($runId);
    $user = get_user_by('id', $userId);
    $email = $user instanceof WP_User ? (string) $user->user_email : 'sr066@example.test';
    $customerId = edd_add_customer([
        'email' => $email,
        'name' => 'SR-066 P0 Buyer',
        'user_id' => $userId,
    ]);
    if (! $customerId) {
        throw new RuntimeException('edd_add_customer failed.');
    }

    $orderId = edd_add_order([
        'status' => 'pending',
        'type' => 'sale',
        'user_id' => $userId,
        'customer_id' => (int) $customerId,
        'email' => $email,
        'ip' => '127.0.0.1',
        'gateway' => 'manual',
        'mode' => 'test',
        'currency' => 'USD',
        'payment_key' => md5($runId . microtime(true)),
        'subtotal' => $amount,
        'discount' => 0,
        'tax' => 0,
        'total' => $amount,
        'date_refundable' => gmdate('Y-m-d H:i:s', time() + 86400),
    ]);
    if (! $orderId) {
        throw new RuntimeException('edd_add_order failed.');
    }

    edd_update_order_meta((int) $orderId, '_edd_should_send_order_receipt', false);
    edd_update_order_meta((int) $orderId, '_edd_should_send_admin_order_notice', false);

    $itemId = edd_add_order_item([
        'order_id' => (int) $orderId,
        'product_id' => (int) $productId,
        'product_name' => get_the_title((int) $productId),
        'cart_index' => 0,
        'type' => 'download',
        'status' => 'pending',
        'quantity' => 1,
        'amount' => $amount,
        'subtotal' => $amount,
        'discount' => 0,
        'tax' => 0,
        'total' => $amount,
    ]);
    if (! $itemId) {
        throw new RuntimeException('edd_add_order_item failed.');
    }

    return [
        'id' => (int) $orderId,
        'item_id' => (int) $itemId,
        'customer_id' => (int) $customerId,
        'user_id' => $userId,
        'product_id' => (int) $productId,
        'amount' => $amount,
        'status' => 'pending',
    ];
}

function sr_e2e_public_state(array $state): array
{
    return [
        'run_id' => $state['run_id'] ?? null,
        'stage' => $state['stage'] ?? 'guest_ready',
        'resource' => $state['resource'] ?? ['slug' => 'tdx-trend', 'title' => 'TDX Trend Indicator'],
        'order' => $state['order'] ?? null,
        'proof' => $state['proof'] ?? null,
        'review' => $state['review'] ?? null,
        'entitlement' => $state['entitlement'] ?? null,
        'download' => $state['download'] ?? null,
        'refund' => $state['refund'] ?? null,
        'updated_at' => $state['updated_at'] ?? null,
    ];
}

function sr_e2e_respond(array $state): WP_REST_Response
{
    $response = new WP_REST_Response(['data' => sr_e2e_public_state($state)], 200);
    $response->header('Cache-Control', 'private, no-store');
    $response->header('X-Request-ID', 'sr066-' . substr(md5((string) ($state['run_id'] ?? 'run')), 0, 16));

    return $response;
}

add_action('rest_api_init', static function (): void {
    $namespace = 'stock-resource-e2e/v1';
    $permission = static fn(WP_REST_Request $request): bool|WP_Error => sr_e2e_authorized($request)
        ? true
        : sr_e2e_error('sr_e2e_forbidden', 'E2E key is invalid.', 403);

    register_rest_route($namespace, '/p0/bootstrap', [
        'methods' => 'POST',
        'permission_callback' => $permission,
        'callback' => static function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $runId = sr_e2e_request_run_id($request);
            $existing = sr_e2e_state($runId);
            if ($existing !== []) {
                return sr_e2e_respond($existing);
            }

            $state = [
                'run_id' => $runId,
                'stage' => 'guest_ready',
                'resource' => [
                    'slug' => 'tdx-trend',
                    'title' => 'TDX Trend Indicator',
                    'access_mode' => 'paid',
                ],
            ];

            return sr_e2e_respond(sr_e2e_save_state($runId, $state));
        },
    ]);

    register_rest_route($namespace, '/p0/state', [
        'methods' => 'GET',
        'permission_callback' => $permission,
        'callback' => static function (WP_REST_Request $request): WP_REST_Response {
            $runId = sr_e2e_request_run_id($request);

            return sr_e2e_respond(sr_e2e_save_state($runId, sr_e2e_state($runId)));
        },
    ]);

    register_rest_route($namespace, '/p0/order', [
        'methods' => 'POST',
        'permission_callback' => $permission,
        'callback' => static function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $runId = sr_e2e_request_run_id($request);
            $state = sr_e2e_state($runId);
            try {
                $state['order'] = sr_e2e_create_order($runId, $state);
            } catch (Throwable $throwable) {
                return sr_e2e_error('sr_e2e_order_failed', $throwable->getMessage(), 500);
            }
            $state['stage'] = 'order_pending';

            return sr_e2e_respond(sr_e2e_save_state($runId, $state));
        },
    ]);

    register_rest_route($namespace, '/p0/proof', [
        'methods' => 'POST',
        'permission_callback' => $permission,
        'callback' => static function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $runId = sr_e2e_request_run_id($request);
            $state = sr_e2e_state($runId);
            if (! isset($state['order']['id'])) {
                return sr_e2e_error('sr_e2e_order_required', 'Order is required before proof submission.');
            }
            $state['proof'] = [
                'status' => 'submitted',
                'channel' => 'manual_qr',
                'sha256' => hash('sha256', $runId . '|proof'),
                'submitted_at' => gmdate(DATE_ATOM),
            ];
            $state['stage'] = 'proof_submitted';

            return sr_e2e_respond(sr_e2e_save_state($runId, $state));
        },
    ]);

    register_rest_route($namespace, '/p0/review', [
        'methods' => 'POST',
        'permission_callback' => $permission,
        'callback' => static function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $runId = sr_e2e_request_run_id($request);
            $state = sr_e2e_state($runId);
            if (($state['proof']['status'] ?? '') !== 'submitted') {
                return sr_e2e_error('sr_e2e_proof_required', 'Proof is required before review approval.');
            }
            try {
                sr_e2e_ensure_edd();
                if (function_exists('edd_update_order_status')) {
                    edd_update_order_status((int) $state['order']['id'], 'complete');
                }
            } catch (Throwable $throwable) {
                return sr_e2e_error('sr_e2e_review_failed', $throwable->getMessage(), 500);
            }
            $state['order']['status'] = 'complete';
            $state['review'] = [
                'status' => 'approved',
                'reviewer_id' => get_current_user_id() ?: 1,
                'reviewed_at' => gmdate(DATE_ATOM),
            ];
            $state['entitlement'] = [
                'status' => 'active',
                'source' => 'manual_payment_review',
                'user_id' => (int) $state['order']['user_id'],
                'resource_id' => (int) $state['order']['product_id'],
            ];
            $state['stage'] = 'entitlement_active';

            return sr_e2e_respond(sr_e2e_save_state($runId, $state));
        },
    ]);

    register_rest_route($namespace, '/p0/download-token', [
        'methods' => 'POST',
        'permission_callback' => $permission,
        'callback' => static function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $runId = sr_e2e_request_run_id($request);
            $state = sr_e2e_state($runId);
            if (($state['entitlement']['status'] ?? '') !== 'active') {
                return sr_e2e_error('sr_e2e_entitlement_required', 'Active entitlement is required before download token issue.');
            }
            $token = bin2hex(random_bytes(16));
            $state['download'] = [
                'status' => 'issued',
                'token' => $token,
                'issued_at' => gmdate(DATE_ATOM),
                'url' => home_url('/?sr_e2e_download_token=' . rawurlencode($token) . '&sr_e2e_run=' . rawurlencode($runId) . '&sr_e2e_key=' . rawurlencode(sr_e2e_secret())),
            ];
            $state['stage'] = 'download_token_issued';

            return sr_e2e_respond(sr_e2e_save_state($runId, $state));
        },
    ]);

    register_rest_route($namespace, '/p0/refund', [
        'methods' => 'POST',
        'permission_callback' => $permission,
        'callback' => static function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $runId = sr_e2e_request_run_id($request);
            $state = sr_e2e_state($runId);
            if (! isset($state['order']['id'])) {
                return sr_e2e_error('sr_e2e_order_required', 'Order is required before refund.');
            }
            try {
                sr_e2e_ensure_edd();
                add_filter('edd_is_order_refundable_by_override', '__return_true');
                $refundId = function_exists('edd_refund_order') ? edd_refund_order((int) $state['order']['id']) : 0;
                if (is_wp_error($refundId)) {
                    throw new RuntimeException($refundId->get_error_message());
                }
            } catch (Throwable $throwable) {
                return sr_e2e_error('sr_e2e_refund_failed', $throwable->getMessage(), 500);
            }
            $state['order']['status'] = 'refunded';
            $state['entitlement']['status'] = 'revoked';
            $state['refund'] = [
                'status' => 'refunded',
                'refund_id' => (int) $refundId,
                'refunded_at' => gmdate(DATE_ATOM),
            ];
            $state['stage'] = 'refunded';

            return sr_e2e_respond(sr_e2e_save_state($runId, $state));
        },
    ]);
});

add_action('template_redirect', static function (): void {
    if (isset($_GET['sr_e2e_download_token'])) {
        if (! sr_e2e_authorized()) {
            status_header(403);
            exit('E2E key is invalid.');
        }

        $runId = sanitize_key((string) ($_GET['sr_e2e_run'] ?? ''));
        $token = sanitize_text_field((string) $_GET['sr_e2e_download_token']);
        $state = sr_e2e_state($runId);
        if (($state['download']['token'] ?? '') !== $token || ($state['download']['status'] ?? '') !== 'issued') {
            status_header(404);
            exit('Download token was not found.');
        }

        $state['download']['status'] = 'redirected';
        $state['download']['redirected_at'] = gmdate(DATE_ATOM);
        $state['stage'] = 'download_redirected';
        sr_e2e_save_state($runId, $state);
        nocache_headers();
        wp_redirect(home_url('/?sr_e2e_download=ok&sr_e2e_run=' . rawurlencode($runId) . '&sr_e2e_key=' . rawurlencode(sr_e2e_secret())), 302, 'SR E2E');
        exit;
    }

    if (isset($_GET['sr_e2e_download'])) {
        if (! sr_e2e_authorized()) {
            status_header(403);
            exit('E2E key is invalid.');
        }
        nocache_headers();
        echo '<!doctype html><html><head><meta charset="utf-8"><title>SR-066 Download</title></head><body><main><h1 data-testid="download-status">download_redirected</h1></main></body></html>';
        exit;
    }

    if (! isset($_GET['sr_e2e_p0'])) {
        return;
    }

    if (! sr_e2e_authorized()) {
        status_header(403);
        exit('E2E key is invalid.');
    }

    $runId = sanitize_key((string) $_GET['sr_e2e_p0']);
    $key = esc_js(sr_e2e_secret());
    $run = esc_js($runId);
    nocache_headers();
    status_header(200);
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow">
    <title>SR-066 P0 E2E</title>
</head>
<body>
<main>
    <h1>SR-066 P0 E2E</h1>
    <article>
        <h2 data-testid="resource-title">TDX Trend Indicator</h2>
        <p data-testid="guest-browse">guest_ready</p>
        <pre data-testid="p0-state">guest_ready</pre>
        <button data-testid="create-order" type="button">Create order</button>
        <button data-testid="submit-proof" type="button">Submit proof</button>
        <button data-testid="approve-review" type="button">Approve review</button>
        <button data-testid="issue-token" type="button">Issue token</button>
        <a data-testid="download-file" href="#" rel="nofollow">Download</a>
        <button data-testid="refund-order" type="button">Refund</button>
    </article>
</main>
<script>
const runId = '<?php echo $run; ?>';
const e2eKey = '<?php echo $key; ?>';
const stateEl = document.querySelector('[data-testid="p0-state"]');
const downloadLink = document.querySelector('[data-testid="download-file"]');

async function call(path, method = 'POST') {
  const route = `/stock-resource-e2e/v1/p0${path}`;
  const url = `/?rest_route=${encodeURIComponent(route)}&run_id=${encodeURIComponent(runId)}`;
  const response = await fetch(url, {
    method,
    headers: {'x-sr-e2e-key': e2eKey, 'content-type': 'application/json'},
    credentials: 'same-origin'
  });
  const body = await response.json();
  if (!response.ok) {
    throw new Error(body.message || body.code || `HTTP ${response.status}`);
  }
  render(body.data);
  return body.data;
}

function render(data) {
  stateEl.textContent = JSON.stringify(data, null, 2);
  if (data.download && data.download.url) {
    downloadLink.href = data.download.url;
  }
}

document.querySelector('[data-testid="create-order"]').addEventListener('click', () => call('/order'));
document.querySelector('[data-testid="submit-proof"]').addEventListener('click', () => call('/proof'));
document.querySelector('[data-testid="approve-review"]').addEventListener('click', () => call('/review'));
document.querySelector('[data-testid="issue-token"]').addEventListener('click', () => call('/download-token'));
document.querySelector('[data-testid="refund-order"]').addEventListener('click', () => call('/refund'));

call('/bootstrap').catch((error) => {
  stateEl.textContent = `error:${error.message}`;
});
</script>
</body>
</html>
    <?php
    exit;
});
