<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            if (app()->bound('current_company')) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('company_id'),
                    app('current_company')->id
                );
            }
        });

        static::creating(function ($model): void {
            if (! app()->bound('current_company')) {
                return;
            }

            if (empty($model->company_id)) {
                $model->company_id = app('current_company')->id;
            }
        });
    }
}
