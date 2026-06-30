<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

final readonly class SupportMessage
{
    public function __construct(
        public int $id,
        public int $ticketId,
        public string $actorType,
        public ?int $actorId,
        public string $visibility,
        public string $body,
        public ?string $attachmentStorageKey,
        public string $createdAt,
    ) {
        if ($id < 0 || $ticketId <= 0) {
            throw new SupportException('invalid_message_id', 'Message IDs must be valid.');
        }
        if (! in_array($actorType, ['customer', 'support', 'system'], true)) {
            throw new SupportException('invalid_actor_type', 'Message actor type is unsupported.');
        }
        if (! in_array($visibility, ['customer', 'internal'], true)) {
            throw new SupportException('invalid_visibility', 'Message visibility is unsupported.');
        }
        if (trim($body) === '') {
            throw new SupportException('empty_message_body', 'Message body is required.');
        }
        (new AttachmentPolicy())->assertPrivate($attachmentStorageKey);
        if (date_create_immutable($createdAt) === false) {
            throw new SupportException('invalid_created_at', 'created_at must be an ISO-8601 datetime.');
        }
    }

    public static function customer(
        int $id,
        int $ticketId,
        int $actorId,
        string $body,
        ?string $attachmentStorageKey,
        string $createdAt,
    ): self {
        return new self($id, $ticketId, 'customer', $actorId, 'customer', $body, $attachmentStorageKey, $createdAt);
    }

    public static function internal(
        int $id,
        int $ticketId,
        int $actorId,
        string $body,
        string $createdAt,
    ): self {
        return new self($id, $ticketId, 'support', $actorId, 'internal', $body, null, $createdAt);
    }

    public function visibleToCustomer(): bool
    {
        return $this->visibility === 'customer';
    }

    /**
     * @param list<SupportMessage> $messages
     * @return list<SupportMessage>
     */
    public static function customerVisible(array $messages): array
    {
        return array_values(array_filter($messages, static fn (self $message): bool => $message->visibleToCustomer()));
    }

    /**
     * @return array<string, mixed>
     */
    public function customerPayload(): array
    {
        if (! $this->visibleToCustomer()) {
            throw new SupportException('message_not_customer_visible', 'Internal notes cannot be projected to customers.');
        }

        return [
            'id' => $this->id,
            'ticket_id' => $this->ticketId,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'visibility' => $this->visibility,
            'body' => $this->body,
            'has_attachment' => $this->attachmentStorageKey !== null,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * @param list<SupportMessage> $messages
     * @return list<array<string, mixed>>
     */
    public static function customerVisiblePayloads(array $messages): array
    {
        return array_values(array_map(
            static fn (self $message): array => $message->customerPayload(),
            self::customerVisible($messages),
        ));
    }
}
