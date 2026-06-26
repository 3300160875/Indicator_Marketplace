<?php
declare(strict_types=1);

namespace StockResource\Core\Rest\Public;

final readonly class PublicResourceQuery
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 12;
    private const MAX_PER_PAGE = 48;

    /** @var list<string> */
    private const FILTER_KEYS = ['category', 'platform', 'indicator_type', 'strategy_tag', 'content_type'];

    /** @var list<string> */
    private const ALLOWED_KEYS = ['category', 'content_type', 'indicator_type', 'page', 'per_page', 'platform', 'search', 'sort', 'strategy_tag'];

    /** @var list<string> */
    private const SORTS = ['updated_desc', 'title_asc', 'popular_desc'];

    /**
     * @param array<string, int|string> $params
     */
    private function __construct(private array $params)
    {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $params = [
            'page' => self::positiveInt($input['page'] ?? self::DEFAULT_PAGE, self::DEFAULT_PAGE),
            'per_page' => min(self::MAX_PER_PAGE, self::positiveInt($input['per_page'] ?? self::DEFAULT_PER_PAGE, self::DEFAULT_PER_PAGE)),
            'sort' => self::stringValue($input['sort'] ?? 'updated_desc'),
        ];

        foreach ($input as $key => $value) {
            if (! is_string($key) || ! in_array($key, self::ALLOWED_KEYS, true)) {
                throw PublicRestError::invalidFilter((string) $key);
            }

            if (in_array($key, self::FILTER_KEYS, true)) {
                $params[$key] = self::slugValue($value, $key);
            }
        }

        if (! in_array($params['sort'], self::SORTS, true)) {
            throw PublicRestError::invalidFilter('sort');
        }

        if (array_key_exists('search', $input)) {
            $search = preg_replace('/\s+/', ' ', trim((string) $input['search'])) ?? '';
            if ($search !== '') {
                $params['search'] = substr($search, 0, 100);
            }
        }

        ksort($params);

        return new self($params);
    }

    /**
     * @return array<string, mixed>
     */
    public static function argumentSchema(): array
    {
        $filter = ['type' => 'string', 'pattern' => '^[a-z0-9]+(?:-[a-z0-9]+)*$'];

        return [
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => self::DEFAULT_PAGE],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE, 'default' => self::DEFAULT_PER_PAGE],
            'search' => ['type' => 'string', 'maxLength' => 100],
            'sort' => ['type' => 'string', 'enum' => self::SORTS, 'default' => 'updated_desc'],
            'category' => $filter,
            'platform' => $filter,
            'indicator_type' => $filter,
            'strategy_tag' => $filter,
            'content_type' => $filter,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function canonicalParams(): array
    {
        return $this->params;
    }

    public function canonicalQueryString(): string
    {
        return http_build_query($this->params, '', '&', PHP_QUERY_RFC3986);
    }

    public function page(): int
    {
        return (int) $this->params['page'];
    }

    public function perPage(): int
    {
        return (int) $this->params['per_page'];
    }

    public function search(): ?string
    {
        return isset($this->params['search']) ? (string) $this->params['search'] : null;
    }

    public function sort(): string
    {
        return (string) $this->params['sort'];
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            if (isset($this->params[$key])) {
                $filters[$key] = (string) $this->params[$key];
            }
        }

        return $filters;
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        $int = (int) $value;

        return $int > 0 ? $int : $default;
    }

    private static function stringValue(mixed $value): string
    {
        return trim((string) $value);
    }

    private static function slugValue(mixed $value, string $field): string
    {
        $slug = strtolower(trim((string) $value));
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw PublicRestError::invalidFilter($field);
        }

        return $slug;
    }
}
