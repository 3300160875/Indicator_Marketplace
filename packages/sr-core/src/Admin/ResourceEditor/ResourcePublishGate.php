<?php
declare(strict_types=1);

namespace StockResource\Core\Admin\ResourceEditor;

final readonly class ResourcePublishGate
{
    /** @var list<string> */
    private const PROHIBITED_TITLE_TERMS = ['稳赚', '必涨', '百分百', '抓牛股'];

    public function evaluate(ResourceDraft $draft): PublishGateResult
    {
        $issues = [];

        if ($draft->title === '') {
            $issues[] = $this->issue('title_required', 'post_title', 'editorial', '资源标题不能为空。');
        }
        foreach (self::PROHIBITED_TITLE_TERMS as $term) {
            if (str_contains($draft->title, $term)) {
                $issues[] = $this->issue('prohibited_claim', 'post_title', 'editorial', '标题包含收益承诺或误导性表述。');
                break;
            }
        }
        if ($draft->excerpt === '') {
            $issues[] = $this->issue('summary_required', 'post_excerpt', 'editorial', '资源摘要不能为空。');
        }
        if (! $draft->taxonomySelected('download_category')) {
            $issues[] = $this->issue('category_required', 'download_category', 'editorial', '必须选择主分类。');
        }
        if ($draft->screenshotCount < 1) {
            $issues[] = $this->issue('screenshot_required', 'gallery', 'editorial', '至少需要一张真实界面截图。');
        }
        if (trim((string) $draft->meta('_sr_install_steps')) === '') {
            $issues[] = $this->issue('install_steps_required', '_sr_install_steps', 'editorial', '必须填写安装步骤。');
        }
        if (trim((string) $draft->meta('_sr_usage_scenarios')) === '') {
            $issues[] = $this->issue('usage_scenarios_required', '_sr_usage_scenarios', 'editorial', '必须填写使用场景。');
        }
        if (trim((string) $draft->meta('_sr_limitations')) === '') {
            $issues[] = $this->issue('limitations_required', '_sr_limitations', 'editorial', '必须填写限制说明和风险提示。');
        }

        if (! $this->compatibilityComplete($draft)) {
            $issues[] = $this->issue('compatibility_required', '_sr_software_versions', 'technical', '必须完整填写兼容性字段。');
        }
        if ($draft->meta('_sr_future_function_status') === 'unknown') {
            $issues[] = $this->issue('future_function_verification_required', '_sr_future_function_status', 'technical', '未来函数状态必须核实。');
        }
        if ($draft->meta('_sr_l2_required') === 'unknown') {
            $issues[] = $this->issue('l2_requirement_required', '_sr_l2_required', 'technical', 'Level-2 数据依赖必须核实。');
        }
        if ((int) ($draft->meta('_sr_current_version_id') ?? 0) <= 0) {
            $issues[] = $this->issue('current_version_required', '_sr_current_version_id', 'technical', '必须绑定当前可交付版本。');
        }

        if ($draft->meta('_sr_rights_status') !== 'approved') {
            $issues[] = $this->issue('rights_approval_required', '_sr_rights_status', 'rights', '版权状态必须审核通过。');
        }
        if ($this->requiresPaidRightsRecord($draft) && (int) ($draft->meta('_sr_rights_record_id') ?? 0) <= 0) {
            $issues[] = $this->issue('rights_record_required', '_sr_rights_record_id', 'rights', '付费或 VIP 资源必须绑定版权证据记录。');
        }
        if ($draft->meta('_sr_risk_level') === 'blocked') {
            $issues[] = $this->issue('risk_blocked', '_sr_risk_level', 'rights', '合规风险为 blocked 时禁止发布。');
        }
        if (trim((string) $draft->meta('_sr_disclaimer_version')) === '') {
            $issues[] = $this->issue('disclaimer_required', '_sr_disclaimer_version', 'rights', '必须绑定风险声明版本。');
        }

        if ($draft->meta('_sr_access_mode') === 'unavailable') {
            $issues[] = $this->issue('access_mode_required', '_sr_access_mode', 'commercial', '必须配置访问模式。');
        }
        if (in_array($draft->meta('_sr_access_mode'), ['purchase', 'purchase_or_vip'], true) && ! $draft->priceConfigured) {
            $issues[] = $this->issue('price_required', 'edd_price', 'commercial', '付费资源必须完成价格配置。');
        }

        return new PublishGateResult($issues);
    }

    private function compatibilityComplete(ResourceDraft $draft): bool
    {
        return $draft->taxonomySelected('sr_platform')
            && $draft->taxonomySelected('sr_indicator_type')
            && $draft->taxonomySelected('sr_content_type')
            && is_array($draft->meta('_sr_software_versions'))
            && $draft->meta('_sr_software_versions') !== []
            && ! in_array($draft->meta('_sr_device'), ['', 'unknown', null], true)
            && ! in_array($draft->meta('_sr_os'), ['', 'unknown', null], true)
            && ! in_array($draft->meta('_sr_file_format'), ['', 'other', null], true)
            && ! in_array($draft->meta('_sr_charset'), ['', 'unknown', null], true)
            && ! in_array($draft->meta('_sr_source_included'), ['', 'unknown', null], true);
    }

    private function requiresPaidRightsRecord(ResourceDraft $draft): bool
    {
        return in_array($draft->meta('_sr_access_mode'), ['purchase', 'purchase_or_vip', 'vip'], true);
    }

    private function issue(string $code, string $field, string $section, string $message): GateIssue
    {
        return new GateIssue($code, $field, $section, $message);
    }
}
