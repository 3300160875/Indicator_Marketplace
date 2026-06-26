<?php
declare(strict_types=1);

test('SR-020 fixture catalog is present and contains twenty resources', function (): void {
    $catalogPath = __DIR__ . '/catalog.json';
    $catalog = json_decode((string) file_get_contents($catalogPath), true);

    expect(is_array($catalog))->toBeTrue();
    expect($catalog['schema_version'] ?? null)->toBe(1);
    expect($catalog['resources'] ?? [])->toHaveCount(20);
});
