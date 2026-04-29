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
            if (app()->bound('current_company')) {
                /** @var Company $company */
                $company = app('current_company');

                $builder->where(
                    $builder->getModel()->qualifyColumn('company_id'),
                    $company->id
                );
            }
        });

        static::creating(function (Model $model): void {
            if (! app()->bound('current_company')) {
                return;
            }

            /** @var Company $company */
            $company = app('current_company');

            if (empty($model->company_id)) {
                $model->company_id = $company->id;
            }
        });
    }
}
