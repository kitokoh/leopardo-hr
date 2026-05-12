<?php

use App\Models\Company;

if (! function_exists('currentCompany')) {
    /**
     * Retrieve the current tenant company from the container.
     */
    function currentCompany(): Company
    {
        /** @var Company $company */
        $company = app('current_company');

        return $company;
    }
}
