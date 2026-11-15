<?php

declare(strict_types=1);

namespace Atlas\Webhook;

/**
 * A {@see WebhookDedupeStore} on PDO (`pgsql`, `sqlite` or `mysql`). Create the table once:
 *
 *   pgsql:  create table atlas_webhook_seen (key text primary key, expires_at bigint not null);
 *   sqlite: create table atlas_webhook_seen (key text primary key, expires_at integer not null);
 *   mysql:  create table atlas_webhook_seen (`key` varchar(255) primary key, expires_at bigint not null);
 *
 * `expires_at` is epoch seconds; an expired row counts as unseen and is claimed again.
 */
final class PdoWebhookDedupeStore implements WebhookDedupeStore
{
    private readonly string $driver;
    private readonly string $col;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->col = $this->driver === 'mysql' ? '`key`' : 'key';
    }

    public function setIfAbsent(string $key, int $ttlSeconds): bool
    {
        $now = time();
        $c = $this->col;
        if ($this->driver === 'mysql') {
            $this->pdo->prepare("delete from atlas_webhook_seen where $c = ? and expires_at <= ?")->execute([$key, $now]);
            $st = $this->pdo->prepare("insert ignore into atlas_webhook_seen ($c, expires_at) values (?, ?)");
            $st->execute([$key, $now + $ttlSeconds]);

            return $st->rowCount() > 0;
        }
        $st = $this->pdo->prepare(
            "insert into atlas_webhook_seen ($c, expires_at) values (?, ?) on conflict ($c) do update set expires_at = excluded.expires_at where atlas_webhook_seen.expires_at <= ?",
        );
        $st->execute([$key, $now + $ttlSeconds, $now]);

        return $st->rowCount() > 0;
    }

    public function delete(string $key): void
    {
        $this->pdo->prepare("delete from atlas_webhook_seen where {$this->col} = ?")->execute([$key]);
    }
}
