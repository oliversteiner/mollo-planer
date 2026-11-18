<?php

namespace App\Webhooks;

use App\Models\ProjectWebhook;

/**
 * The target of a webhook (docs/auftrag-m5.md §4.1). GitHub: owner/name. Generic: an
 * https URL (http too in local and testing). In production a generic target must not
 * point into a private, loopback or link-local network; the name is resolved and
 * checked when it is saved and again when it is used (the answer may change).
 */
final class WebhookTarget
{
    private const REPOSITORY = '/^[A-Za-z0-9_.-]{1,100}\/[A-Za-z0-9_.-]{1,100}$/';

    /**
     * The key of the message in lang/{de,en}/planer.php (webhook.*), or null when the target is fine.
     */
    public static function problem(string $kind, string $target): ?string
    {
        if ($kind === ProjectWebhook::GITHUB) {
            return preg_match(self::REPOSITORY, $target) === 1 ? null : 'github_target';
        }

        $parts = parse_url($target);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if ($host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return 'url_invalid';
        }

        $allowedHttp = app()->environment(['local', 'testing']);

        if ($scheme !== 'https' && ! ($scheme === 'http' && $allowedHttp)) {
            return 'url_https';
        }

        return self::reachable($host) ? null : 'url_private';
    }

    /**
     * Whether the host may be called: outside production always; in production only a host that
     * resolves, and only to public addresses.
     */
    public static function reachable(string $host): bool
    {
        if (! app()->isProduction()) {
            return true;
        }

        $addresses = self::addresses(trim($host, '[]'));

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $found = [];

        foreach ((array) @dns_get_record($host, DNS_A | DNS_AAAA) as $record) {
            $found[] = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
        }

        return array_values(array_filter($found));
    }
}
