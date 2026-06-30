<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$adminOps = $root.'/packages/sr-admin-ops';

foreach ([
    '/src/Favorite/FavoriteCacheKeys.php',
    '/src/Favorite/FavoriteException.php',
    '/src/Favorite/FavoriteListItem.php',
    '/src/Favorite/FavoriteRecord.php',
    '/src/Favorite/FavoriteRepository.php',
    '/src/Favorite/FavoriteResourceSnapshot.php',
    '/src/Favorite/FavoriteService.php',
    '/src/Favorite/FavoriteToggleResult.php',
    '/src/Favorite/InMemoryFavoriteRepository.php',
] as $file) {
    require_once $adminOps.$file;
}

use StockResource\AdminOps\Favorite\FavoriteException;
use StockResource\AdminOps\Favorite\FavoriteResourceSnapshot;
use StockResource\AdminOps\Favorite\FavoriteService;
use StockResource\AdminOps\Favorite\InMemoryFavoriteRepository;

function sr062_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function sr062_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$repository = new InMemoryFavoriteRepository();
$service = new FavoriteService($repository);

$first = $service->addFavorite(77, 1001, '2026-06-30T10:00:00+00:00');
sr062_same('added', $first->action, 'first add creates favorite');
sr062_same(true, $first->favorited, 'first add returns favorited=true');
sr062_same(['favorite:user:77', 'favorite:user:77:resource:1001'], $first->cacheKeysToInvalidate, 'add invalidates user and resource cache keys');

$second = $service->addFavorite(77, 1001, '2026-06-30T10:01:00+00:00');
sr062_same('already_present', $second->action, 'second add is idempotent');
sr062_same($first->record?->id, $second->record?->id, 'idempotent add returns original record');
sr062_same(1, $repository->countForUser(77), 'user+resource uniqueness prevents duplicates');

$otherUser = $service->addFavorite(78, 1001, '2026-06-30T10:02:00+00:00');
sr062_same('added', $otherUser->action, 'another user can favorite same resource');
sr062_same(1, $repository->countForUser(78), 'unique key is scoped by user');

$otherResource = $service->addFavorite(77, 1002, '2026-06-30T10:03:00+00:00');
sr062_same('added', $otherResource->action, 'same user can favorite another resource');
sr062_same(2, $repository->countForUser(77), 'same user has two distinct favorites');

$removed = $service->removeFavorite(77, 1001);
sr062_same('removed', $removed->action, 'delete removes existing favorite');
sr062_same(false, $removed->favorited, 'delete returns favorited=false');
sr062_same(null, $repository->findByUserAndResource(77, 1001), 'removed favorite is absent');

$removedAgain = $service->removeFavorite(77, 1001);
sr062_same('already_absent', $removedAgain->action, 'delete is idempotent when absent');
sr062_same(false, $removedAgain->favorited, 'absent delete returns favorited=false');

$enabled = $service->setFavorite(77, 1001, true, '2026-06-30T10:04:00+00:00');
sr062_same('added', $enabled->action, 'set favorite true adds favorite');
$disabled = $service->setFavorite(77, 1001, false, '2026-06-30T10:05:00+00:00');
sr062_same('removed', $disabled->action, 'set favorite false removes favorite');
$service->removeFavorite(77, 1002);

try {
    $service->addFavorite(0, 1001, '2026-06-30T10:06:00+00:00');
    throw new RuntimeException('invalid user should fail');
} catch (FavoriteException $exception) {
    sr062_same('invalid_user_id', $exception->code(), 'invalid user has stable error code');
}

try {
    $service->addFavorite(77, 0, '2026-06-30T10:06:00+00:00');
    throw new RuntimeException('invalid resource should fail');
} catch (FavoriteException $exception) {
    sr062_same('invalid_resource_id', $exception->code(), 'invalid resource has stable error code');
}

$service->addFavorite(77, 1001, '2026-06-30T10:10:00+00:00');
$service->addFavorite(77, 1003, '2026-06-30T10:11:00+00:00');

$published = FavoriteResourceSnapshot::fromArray([
    'id' => 1001,
    'status' => 'publish',
    'slug' => 'tdx-trend',
    'title' => 'Trend indicator',
    'excerpt' => 'Public summary',
    'access_mode' => 'purchase',
]);
$draft = FavoriteResourceSnapshot::fromArray([
    'id' => 1003,
    'status' => 'draft',
    'slug' => 'private-draft',
    'title' => 'Private draft title',
    'excerpt' => 'Private draft excerpt',
    'access_mode' => 'purchase',
]);

$items = $service->listForUser(77, [
    1001 => $published,
    1003 => $draft,
]);
sr062_same(2, count($items), 'list returns all user favorites');
sr062_same(false, $items[0]->resourceUnavailable, 'published resource is visible');
sr062_same('Trend indicator', $items[0]->toCustomerPayload()['resource']['title'], 'published resource title is visible');
sr062_same(true, $items[1]->resourceUnavailable, 'draft resource is represented as unavailable');
$draftPayload = $items[1]->toCustomerPayload();
sr062_same(null, $draftPayload['resource'], 'draft resource details are not exposed');
sr062_same('resource_unavailable', $draftPayload['unavailable_reason'], 'draft resource has stable unavailable reason');
$encoded = json_encode($draftPayload, JSON_THROW_ON_ERROR);
foreach (['Private draft title', 'Private draft excerpt', 'private-draft'] as $forbidden) {
    sr062_true(! str_contains($encoded, $forbidden), 'unavailable favorite does not leak '.$forbidden);
}

$foreignItems = $service->listForUser(78, [1001 => $published]);
sr062_same(1, count($foreignItems), 'user listing is scoped to requested user');
sr062_same(1001, $foreignItems[0]->resourceId, 'other user sees only own favorite');

echo "SR-062 favorite checks passed\n";
