<?php

declare(strict_types=1);

namespace Atlas\Webhook;

/** A verified webhook delivery, as {@see WebhookDedupe::verify()} returns it. */
final class WebhookDelivery
{
    /**
     * @param array<string,mixed> $event      the parsed envelope `{id, type, timestamp, instance_id, data}`
     * @param bool                $duplicate  true when this `atlas-id` was already seen: acknowledge with 2xx and do nothing
     * @param string              $deliveryId the signed `atlas-id`, identical on every retry of one event
     */
    public function __construct(
        public readonly array $event,
        public readonly bool $duplicate,
        public readonly string $deliveryId,
        private readonly WebhookDedupeStore $store,
        private readonly string $key,
    ) {
    }

    /** Forget this delivery so Atlas's retry is processed (call it when your handler failed). */
    public function release(): void
    {
        $this->store->delete($this->key);
    }
}
