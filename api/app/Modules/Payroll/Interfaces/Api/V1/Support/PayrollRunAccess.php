<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Support;

use App\Core\Auth\Domain\Models\Employee;
use App\Exceptions\DomainException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * #7962 — gardes d'autorisation et traduction d'exceptions d'un run de paie.
 *
 * `PayrollRunController` répétait, dans **onze** méthodes, le même préambule :
 *
 *     if ($payrollRun->company_id !== $actor->company_id) { abort(404); }
 *     if ($actor->isManager() === false) { abort(403); }
 *
 * et, dans **cinq** d'entre elles, le même bloc `catch` qui traduit une
 * exception métier en `{error, message, localized_message}` avec le code
 * stable de l'exception (#3810 / #4310).
 *
 * Ce n'est pas de la logique métier — c'est de la plomberie HTTP répétée, et
 * c'est exactement ce que l'issue #7962 demande de sortir du contrôleur. La
 * répétition avait un coût réel : `lock` et `unlock` exigeaient
 * `hasManagerRole('principal', 'comptable')` alors que `cancel` et
 * `regularize` se contentaient de `isManager()`, et rien dans le code ne
 * disait si c'était voulu. Ici, chaque niveau d'autorisation est nommé.
 *
 * Les gardes restent des `abort()` : le rendu 404/403 est celui du framework,
 * identique à avant. Les convertir en réponses JSON changerait le contrat.
 */
final class PayrollRunAccess
{
    /**
     * Lecture/écriture courante : manager du tenant propriétaire du run.
     *
     * Le 404 (et non 403) sur un run d'un autre tenant est volontaire : il ne
     * révèle pas l'existence d'un run auquel l'appelant n'a pas droit.
     */
    public function authorizeManager(PayrollRun $payrollRun, Employee $actor): void
    {
        $this->authorizeTenant($payrollRun, $actor);

        if ($actor->isManager() === false) {
            abort(403);
        }
    }

    /**
     * Décision sensible (validation, clôture, réversion de clôture) : réservée
     * aux managers `principal`/`comptable` — séparation des tâches #5246. Un
     * `rh` peut préparer (calculer) mais pas approuver sa propre préparation.
     */
    public function authorizeApprover(PayrollRun $payrollRun, Employee $actor): void
    {
        $this->authorizeTenant($payrollRun, $actor);

        if ($actor->hasManagerRole('principal', 'comptable') === false) {
            abort(403, 'INSUFFICIENT_ROLE');
        }
    }

    private function authorizeTenant(PayrollRun $payrollRun, Employee $actor): void
    {
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
    }

    /**
     * Traduit une exception métier portant un code stable en réponse localisée.
     *
     * Le message brut de l'exception n'est JAMAIS exposé : il est en français
     * codé en dur et non traduit (#3810 / #4310). Le client reçoit le code
     * stable + sa traduction depuis le catalogue `errors.*`.
     */
    public function businessError(DomainException $exception): JsonResponse
    {
        $code = $exception->errorCode();

        return response()->json([
            'error' => $code,
            'message' => $code,
            'localized_message' => __('errors.'.$code),
        ], $exception->statusCode());
    }

    /**
     * Traduit un échec inattendu en réponse 422 générique, en journalisant le
     * détail côté serveur uniquement.
     */
    public function unexpectedError(string $logEvent, PayrollRun $payrollRun, RuntimeException $exception, string $code): JsonResponse
    {
        Log::error($logEvent, [
            'run_id' => $payrollRun->id,
            'error' => $exception->getMessage(),
        ]);

        return response()->json([
            'error' => $code,
            'message' => $code,
            'localized_message' => __('errors.'.$code),
        ], 422);
    }
}
