<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

final class ClientIpResolver
{
    public function resolve(Request $request): ?string
    {
        $remoteAddress = trim((string) $request->server('REMOTE_ADDR', ''));
        $cloudflareAddress = trim((string) $request->headers->get('CF-Connecting-IP', ''));
        $proxies = (array) config('tbwim.intake.cloudflare_proxies', []);

        if ($remoteAddress !== '' && $cloudflareAddress !== '' && filter_var($cloudflareAddress, FILTER_VALIDATE_IP)
            && IpUtils::checkIp($remoteAddress, $proxies)) {
            return $cloudflareAddress;
        }

        $resolved = trim((string) $request->ip());

        return filter_var($resolved, FILTER_VALIDATE_IP) ? $resolved : null;
    }
}
