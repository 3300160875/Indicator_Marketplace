<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Auth;

use InvalidArgumentException;

final readonly class RoleCapabilityMatrix
{
    /**
     * @param  array<string, CapabilityDefinition>  $capabilities
     * @param  array<string, RoleDefinition>  $roles
     */
    private function __construct(
        private array $capabilities,
        private array $roles,
    ) {}

    public static function defaults(): self
    {
        $capabilities = self::indexCapabilities([
            new CapabilityDefinition('sr_read_admin', 'Read Stock Resource admin'),
            new CapabilityDefinition('sr_edit_resource_content', 'Edit owned resource content', ownerRestricted: true),
            new CapabilityDefinition('sr_submit_resource_for_review', 'Submit resource for review', ownerRestricted: true),
            new CapabilityDefinition('sr_manage_resource_versions', 'Manage owned resource versions', ownerRestricted: true),
            new CapabilityDefinition('sr_view_scan_results', 'View resource scan results', ownerRestricted: true),
            new CapabilityDefinition('sr_view_orders', 'View orders'),
            new CapabilityDefinition('sr_view_revenue_reports', 'View revenue reports'),
            new CapabilityDefinition('sr_export_finance_reports', 'Export finance reports'),
            new CapabilityDefinition('sr_view_customer_entitlements', 'View customer entitlements'),
            new CapabilityDefinition('sr_resend_delivery_notice', 'Resend delivery notice'),
            new CapabilityDefinition('sr_review_rights_evidence', 'Review rights evidence', ownerRestricted: true),
            new CapabilityDefinition('sr_flag_compliance_risk', 'Flag compliance risk', ownerRestricted: true),
            new CapabilityDefinition('sr_manage_taxonomy_terms', 'Manage controlled taxonomy terms'),
            new CapabilityDefinition('sr_manage_featured_resources', 'Manage featured resources'),
            new CapabilityDefinition('sr_manage_capabilities', 'Manage role capabilities', highRisk: true),
            new CapabilityDefinition('sr_delete_resources', 'Delete resources', highRisk: true),
            new CapabilityDefinition('sr_override_compliance_gate', 'Override compliance gate', highRisk: true),
            new CapabilityDefinition('sr_manage_payment_settings', 'Manage payment settings', highRisk: true),
        ]);

        $administratorCapabilities = array_keys($capabilities);

        return new self($capabilities, self::indexRoles([
            new RoleDefinition('sr_resource_editor', 'Resource editor', [
                'sr_read_admin',
                'sr_edit_resource_content',
                'sr_submit_resource_for_review',
            ]),
            new RoleDefinition('sr_technical_reviewer', 'Technical reviewer', [
                'sr_read_admin',
                'sr_manage_resource_versions',
                'sr_view_scan_results',
            ]),
            new RoleDefinition('sr_finance_operator', 'Finance operator', [
                'sr_read_admin',
                'sr_view_orders',
                'sr_view_revenue_reports',
                'sr_export_finance_reports',
            ]),
            new RoleDefinition('sr_customer_support', 'Customer support', [
                'sr_read_admin',
                'sr_view_customer_entitlements',
                'sr_resend_delivery_notice',
            ]),
            new RoleDefinition('sr_compliance_reviewer', 'Compliance reviewer', [
                'sr_read_admin',
                'sr_review_rights_evidence',
                'sr_flag_compliance_risk',
            ]),
            new RoleDefinition('sr_operations_manager', 'Operations manager', [
                'sr_read_admin',
                'sr_manage_taxonomy_terms',
                'sr_manage_featured_resources',
            ]),
            new RoleDefinition('administrator', 'Administrator', $administratorCapabilities),
        ]));
    }

    /**
     * @return array<string, RoleDefinition>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    public function role(string $slug): RoleDefinition
    {
        if (! isset($this->roles[$slug])) {
            throw new InvalidArgumentException('Unknown role: '.$slug);
        }

        return $this->roles[$slug];
    }

    public function capability(string $slug): CapabilityDefinition
    {
        if (! isset($this->capabilities[$slug])) {
            throw new InvalidArgumentException('Unknown capability: '.$slug);
        }

        return $this->capabilities[$slug];
    }

    /**
     * @return list<string>
     */
    public function highRiskCapabilities(): array
    {
        return array_values(array_map(
            static fn (CapabilityDefinition $capability): string => $capability->slug,
            array_filter($this->capabilities, static fn (CapabilityDefinition $capability): bool => $capability->highRisk),
        ));
    }

    /**
     * @param  list<CapabilityDefinition>  $capabilities
     * @return array<string, CapabilityDefinition>
     */
    private static function indexCapabilities(array $capabilities): array
    {
        $indexed = [];
        foreach ($capabilities as $capability) {
            $indexed[$capability->slug] = $capability;
        }

        return $indexed;
    }

    /**
     * @param  list<RoleDefinition>  $roles
     * @return array<string, RoleDefinition>
     */
    private static function indexRoles(array $roles): array
    {
        $indexed = [];
        foreach ($roles as $role) {
            $indexed[$role->slug] = $role;
        }

        return $indexed;
    }
}
