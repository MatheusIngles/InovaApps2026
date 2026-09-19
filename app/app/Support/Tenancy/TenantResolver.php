<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Illuminate\Http\Request;

/** Identifica a empresa pela URL: subdomínio (TENANT_BASE_DOMAIN) ou ?empresa=slug. */
class TenantResolver
{
    public static function slug(Request $request): ?string
    {
        $base = config('tenancy.base_domain');
        $host = $request->getHost();

        if ($base && str_ends_with($host, '.'.$base)) {
            return explode('.', substr($host, 0, -strlen($base) - 1))[0] ?: null;
        }

        return $request->query('empresa') ?: null;
    }

    public static function company(Request $request): ?Company
    {
        $slug = self::slug($request);

        return $slug ? Company::firstWhere('slug', $slug) : null;
    }
}
