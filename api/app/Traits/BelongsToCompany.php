<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $currentCompany = app()->bound('current_company') ? app('current_company') : null;

            if (! $currentCompany instanceof Company) {
                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('company_id'),
                $currentCompany->id
            );
        });

        static::creating(function (Model $model): void {
            $currentCompany = app()->bound('current_company') ? app('current_company') : null;

            if (! $currentCompany instanceof Company) {
                return;
            }

            if (empty($model->getAttribute('company_id'))) {
                $model->setAttribute('company_id', $currentCompany->id);
            }
        });
    }
}
