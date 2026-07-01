<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Modules\Billing\Interfaces\Api\V1\Requests\StoreWebhookEndpointRequest
 *
 * ⚠️  DO NOT add logic here.
 * ✅  Once all usages reference App\Modules\Billing\Interfaces\Api\V1\Requests\StoreWebhookEndpointRequest, delete this file.
 */

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Webhook;

class_alias(\App\Modules\Billing\Interfaces\Api\V1\Requests\StoreWebhookEndpointRequest::class, __NAMESPACE__ . '\\StoreWebhookEndpointRequest');
