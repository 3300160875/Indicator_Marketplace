<?php

declare(strict_types=1);

$sr_checkout_terms_model = $sr_checkout_terms_model ?? [];
$termsSnapshot = $sr_checkout_terms_model['terms_snapshot'] ?? [];
$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="sr-checkout-terms" aria-labelledby="sr-checkout-terms-title">
    <h2 id="sr-checkout-terms-title"><?php echo $esc($sr_checkout_terms_model['title'] ?? '数字内容条款确认'); ?></h2>
    <dl class="sr-checkout-terms__versions">
        <div>
            <dt>服务条款</dt>
            <dd><?php echo $esc($termsSnapshot['service_terms_version'] ?? ''); ?></dd>
        </div>
        <div>
            <dt>数字内容交付</dt>
            <dd><?php echo $esc($termsSnapshot['digital_delivery_version'] ?? ''); ?></dd>
        </div>
        <div>
            <dt>退款规则</dt>
            <dd><?php echo $esc($termsSnapshot['refund_rule_version'] ?? ''); ?></dd>
        </div>
        <div>
            <dt>隐私政策</dt>
            <dd><?php echo $esc($termsSnapshot['privacy_version'] ?? ''); ?></dd>
        </div>
    </dl>
    <label class="sr-checkout-terms__confirm">
        <input type="checkbox" name="sr_terms_confirmed" value="1" required>
        <span>我已阅读并同意服务条款、数字内容交付确认、退款规则和隐私政策。</span>
    </label>
    <input type="hidden" name="sr_terms_snapshot[service_terms_version]" value="<?php echo $esc($termsSnapshot['service_terms_version'] ?? ''); ?>">
    <input type="hidden" name="sr_terms_snapshot[digital_delivery_version]" value="<?php echo $esc($termsSnapshot['digital_delivery_version'] ?? ''); ?>">
    <input type="hidden" name="sr_terms_snapshot[refund_rule_version]" value="<?php echo $esc($termsSnapshot['refund_rule_version'] ?? ''); ?>">
    <input type="hidden" name="sr_terms_snapshot[privacy_version]" value="<?php echo $esc($termsSnapshot['privacy_version'] ?? ''); ?>">
</section>
