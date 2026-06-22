<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class LocalNetworkClient
{
    /**
     * RFC1918, loopback, link-local IPv4 (APIPA) y equivalentes IPv6 habituales en LAN.
     *
     * @return list<string>
     */
    public static function defaultLocalCidrs(): array
    {
        return [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '::1/128',
            'fc00::/7',
            'fe80::/10',
        ];
    }

    public static function allAllowedCidrs(): array
    {
        $extra = config('local_network.additional_cidrs', []);

        return array_values(array_unique(array_merge(self::defaultLocalCidrs(), is_array($extra) ? $extra : [])));
    }

    public static function clientIp(Request $request): string
    {
        return (string) ($request->ip() ?? '');
    }

    public static function isOnLocalNetwork(Request $request): bool
    {
        $ip = self::clientIp($request);
        if ($ip === '' || $ip === '0.0.0.0') {
            return false;
        }

        foreach (self::allAllowedCidrs() as $cidr) {
            if (IpUtils::checkIp($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }
}
