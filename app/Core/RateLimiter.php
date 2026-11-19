<?php

namespace App\Core;

/**
 * Pembatas percobaan berbasis database (jendela tetap).
 * Jika tabel belum ada, pembatas dilewati dan error dicatat di log.
 */
class RateLimiter
{
    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
    }

    public static function key(string ...$parts): string
    {
        return hash('sha256', implode('|', $parts));
    }

    /**
     * Sisa detik pemblokiran, atau 0 jika belum mencapai batas.
     */
    public static function blocked(string $key, int $max, int $window): int
    {
        try {
            $stmt = Database::getInstance()->getConnection()
                ->prepare('SELECT hits, window_start FROM rate_limits WHERE rl_key = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
        } catch (\Throwable $e) {
            error_log('[RateLimiter] ' . $e->getMessage());

            return 0;
        }

        if (!$row) {
            return 0;
        }

        $left = (int) $row['window_start'] + $window - time();

        return ($left > 0 && (int) $row['hits'] >= $max) ? $left : 0;
    }

    public static function hit(string $key, int $window): void
    {
        $now = time();

        try {
            $pdo = Database::getInstance()->getConnection();

            // hits dihitung lebih dulu, memakai window_start lama.
            $pdo->prepare(
                'INSERT INTO rate_limits (rl_key, hits, window_start) VALUES (?, 1, ?)
                 ON DUPLICATE KEY UPDATE
                   hits = IF(window_start <= ?, 1, hits + 1),
                   window_start = IF(window_start <= ?, ?, window_start)'
            )->execute([$key, $now, $now - $window, $now - $window, $now]);

            if (random_int(1, 100) === 1) {
                $pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([$now - 86400]);
            }
        } catch (\Throwable $e) {
            error_log('[RateLimiter] ' . $e->getMessage());
        }
    }

    public static function clear(string $key): void
    {
        try {
            Database::getInstance()->getConnection()
                ->prepare('DELETE FROM rate_limits WHERE rl_key = ?')->execute([$key]);
        } catch (\Throwable $e) {
            error_log('[RateLimiter] ' . $e->getMessage());
        }
    }
}