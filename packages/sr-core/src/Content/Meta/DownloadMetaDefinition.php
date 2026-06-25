<?php
declare(strict_types=1);

namespace StockResource\Core\Content\Meta;

final readonly class DownloadMetaDefinition
{
    /**
     * @param list<string> $enumValues
     */
    public function __construct(
        private string $key,
        private string $type,
        private mixed $default,
        private bool $public,
        private array $enumValues = [],
        private bool $nullable = false,
        private string $editCapability = 'edit_sr_resource_meta',
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function default(): mixed
    {
        return $this->default;
    }

    /**
     * @return list<string>
     */
    public function enumValues(): array
    {
        return $this->enumValues;
    }

    public function public(): bool
    {
        return $this->public;
    }

    public function sanitize(mixed $value): mixed
    {
        return match ($this->type) {
            'enum' => $this->sanitizeEnum($value),
            'string' => $this->sanitizeString($value),
            'html' => $this->sanitizeHtml($value),
            'bool' => $this->sanitizeBool($value),
            'int' => $this->sanitizeInt($value),
            'bigint' => $this->sanitizeBigint($value),
            'json_array' => $this->sanitizeJsonArray($value),
            'json_object' => $this->sanitizeJsonObject($value),
            default => $this->default,
        };
    }

    /**
     * @param callable(string): bool|null $canEdit
     * @return array<string, mixed>
     */
    public function registrationArgs(?callable $canEdit = null): array
    {
        $schema = $this->restSchema();

        return [
            'type' => $this->wordpressType(),
            'single' => true,
            'default' => $this->default,
            'show_in_rest' => $this->public ? ['schema' => $schema] : false,
            'sanitize_callback' => fn(mixed $value): mixed => $this->sanitize($value),
            'auth_callback' => fn(): bool => $canEdit !== null && $canEdit($this->editCapability),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function restSchema(): array
    {
        $type = $this->wordpressType();

        $schema = [
            'type' => $this->nullable ? [$type, 'null'] : $type,
            'default' => $this->default,
        ];

        if ($this->enumValues !== []) {
            $schema['enum'] = $this->enumValues;
        }

        if ($this->type === 'json_array') {
            $schema['items'] = ['type' => 'string'];
        }

        if ($this->type === 'json_object') {
            $schema['additionalProperties'] = true;
        }

        return $schema;
    }

    private function wordpressType(): string
    {
        return match ($this->type) {
            'bool' => 'boolean',
            'int', 'bigint' => 'integer',
            'json_array' => 'array',
            'json_object' => 'object',
            default => 'string',
        };
    }

    private function sanitizeEnum(mixed $value): string
    {
        $normalized = is_scalar($value) ? strtolower(trim((string) $value)) : '';

        return in_array($normalized, $this->enumValues, true) ? $normalized : (string) $this->default;
    }

    private function sanitizeString(mixed $value): ?string
    {
        if ($value === null && $this->nullable) {
            return null;
        }

        if (! is_scalar($value)) {
            return $this->nullable ? null : (string) $this->default;
        }

        $normalized = trim((string) $value);

        return $normalized === '' && $this->nullable ? null : $normalized;
    }

    private function sanitizeHtml(mixed $value): string
    {
        if (! is_scalar($value)) {
            return (string) $this->default;
        }

        $withoutScripts = preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string) $value);
        $html = $withoutScripts ?? '';

        return trim(strip_tags($html, '<p><br><ul><ol><li><strong><em><code><pre><a>'));
    }

    private function sanitizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return is_int($value) || is_float($value) ? (int) $value === 1 : (bool) $this->default;
    }

    private function sanitizeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?[0-9]+$/', trim($value))) {
            return (int) trim($value);
        }

        return $this->nullable ? null : (int) $this->default;
    }

    private function sanitizeBigint(mixed $value): ?int
    {
        $integer = $this->sanitizeInt($value);
        if ($integer === null) {
            return null;
        }

        return $integer > 0 ? $integer : ($this->nullable ? null : (int) $this->default);
    }

    /**
     * @return list<mixed>
     */
    private function sanitizeJsonArray(mixed $value): array
    {
        $decoded = $this->decodeJson($value);

        return is_array($decoded) && array_is_list($decoded) ? $decoded : (array) $this->default;
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeJsonObject(mixed $value): array
    {
        $decoded = $this->decodeJson($value);

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : (array) $this->default;
    }

    private function decodeJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
