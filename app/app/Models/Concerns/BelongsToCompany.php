<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isola o model por empresa: todo SELECT é filtrado pela empresa ativa e todo INSERT recebe seu company_id.
 * Em requisições web sem empresa ativa o escopo bloqueia tudo (falha fechada); no console (seeders, jobs) fica aberto.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query): void {
            $id = app(CompanyContext::class)->id();

            if ($id !== null) {
                $query->where($query->getModel()->getTable().'.company_id', $id);
            } elseif (! app()->runningInConsole()) {
                $query->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model): void {
            $model->company_id ??= app(CompanyContext::class)->id();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
