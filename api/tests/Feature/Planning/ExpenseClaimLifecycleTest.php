<?php

declare(strict_types=1);

namespace Tests\Feature\Planning;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-022 (issue #8146) — zone 3/5 : notes de frais, modèle possédé par le
 * module Planning (ExpenseClaim / ExpenseItem), servi par le contrôleur du
 * module Expense.
 *
 * Complète tests/Feature/Expense/* (workflow d'approbation déjà couvert) :
 * cycle brouillon → soumission → resoumission après rejet (#4933), mise à
 * jour et suppression réservées au propriétaire, total calculé (golden).
 */
class ExpenseClaimLifecycleTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->employee);
    }

    /** @param list<array<string, mixed>> $items */
    private function storeClaim(array $items, string $title = 'Déplacement client'): ExpenseClaim
    {
        $response = $this->postJson('/api/v1/expense-claims', [
            'title' => $title,
            'items' => $items,
        ]);
        $response->assertCreated();

        /** @var ExpenseClaim $claim */
        $claim = ExpenseClaim::query()->findOrFail($response->json('data.id'));

        return $claim;
    }

    /** @return list<array<string, mixed>> */
    private function validItems(): array
    {
        return [
            [
                'category' => 'transport',
                'description' => 'Taxi aéroport',
                'amount' => 2500.0,
                'date' => '2026-04-06',
            ],
            [
                'category' => 'meals',
                'description' => 'Déjeuner client',
                'amount' => 1500.0,
                'date' => '2026-04-06',
            ],
        ];
    }

    public function test_store_creates_draft_with_items_and_computed_total(): void
    {
        // Golden : total = 2500,00 + 1500,00 = 4000,00 — somme des lignes,
        // jamais saisi par le client.
        $claim = $this->storeClaim($this->validItems());

        $this->assertSame('draft', $claim->status);
        $this->assertSame(4000.0, (float) $claim->total_amount);
        $this->assertSame(2, $claim->items()->count());
        $this->assertSame($this->company->id, $claim->company_id);
        $this->assertSame($this->employee->id, $claim->employee_id);
    }

    public function test_store_validates_items_required(): void
    {
        $this->postJson('/api/v1/expense-claims', ['title' => 'Sans lignes'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_store_rejects_invalid_category(): void
    {
        $this->postJson('/api/v1/expense-claims', [
            'title' => 'Catégorie inconnue',
            'items' => [[
                'category' => 'luxury',
                'description' => 'Montre',
                'amount' => 99000,
                'date' => '2026-04-06',
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.category']);
    }

    public function test_owner_can_update_draft_and_items_are_fully_replaced(): void
    {
        $claim = $this->storeClaim($this->validItems());

        // Golden : remplacement intégral des lignes — total recalculé sur les
        // nouvelles lignes (800 + 1200 + 500 = 2500), anciennes supprimées.
        $this->putJson("/api/v1/expense-claims/{$claim->id}", [
            'title' => 'Déplacement client (corrigé)',
            'items' => [
                ['category' => 'transport', 'description' => 'Train', 'amount' => 800, 'date' => '2026-04-07'],
                ['category' => 'accommodation', 'description' => 'Hôtel', 'amount' => 1200, 'date' => '2026-04-07'],
                ['category' => 'communication', 'description' => 'SIM data', 'amount' => 500, 'date' => '2026-04-07'],
            ],
        ])->assertOk();

        $claim->refresh();
        $this->assertSame('Déplacement client (corrigé)', $claim->title);
        $this->assertSame(2500.0, (float) $claim->total_amount);
        $this->assertSame(3, $claim->items()->count());
        $this->assertSame(0, $claim->items()->where('description', 'Taxi aéroport')->count());
    }

    public function test_update_submitted_claim_returns_422(): void
    {
        $claim = $this->storeClaim($this->validItems());
        $this->putJson("/api/v1/expense-claims/{$claim->id}/submit")->assertOk();

        $this->putJson("/api/v1/expense-claims/{$claim->id}", [
            'title' => 'Tentative de modification',
            'items' => $this->validItems(),
        ])->assertStatus(422)
            ->assertJsonPath('message', 'EXPENSE_CLAIM_NOT_EDITABLE');
    }

    public function test_update_rejected_claim_returns_it_to_draft_for_resubmission(): void
    {
        $claim = $this->storeClaim($this->validItems());
        $claim->update(['status' => 'rejected', 'rejection_reason' => 'Justificatif illisible']);

        // #4933 : une demande rejetée corrigée repasse en brouillon —
        // resoumission possible (avant : état terminal, recréation forcée).
        $this->putJson("/api/v1/expense-claims/{$claim->id}", [
            'title' => 'Note corrigée',
            'items' => $this->validItems(),
        ])->assertOk();

        $claim->refresh();
        $this->assertSame('draft', $claim->status);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/submit")->assertOk();
        $claim->refresh();
        $this->assertSame('submitted', $claim->status);
    }

    public function test_owner_can_delete_draft_and_items_are_purged(): void
    {
        $claim = $this->storeClaim($this->validItems());
        $itemIds = $claim->items()->pluck('id');

        $this->deleteJson("/api/v1/expense-claims/{$claim->id}")->assertNoContent();

        $this->assertNull(ExpenseClaim::query()->find($claim->id));
        $this->assertSame(0, \App\Modules\Planning\Domain\Models\ExpenseItem::query()->whereIn('id', $itemIds)->count());
    }

    public function test_delete_submitted_claim_returns_422(): void
    {
        $claim = $this->storeClaim($this->validItems());
        $this->putJson("/api/v1/expense-claims/{$claim->id}/submit")->assertOk();

        $this->deleteJson("/api/v1/expense-claims/{$claim->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'EXPENSE_CLAIM_NOT_DELETABLE');
    }

    public function test_submit_marks_claim_submitted_with_timestamp(): void
    {
        $claim = $this->storeClaim($this->validItems());
        $this->assertNull($claim->submitted_at);

        $this->putJson("/api/v1/expense-claims/{$claim->id}/submit")->assertOk();

        $claim->refresh();
        $this->assertSame('submitted', $claim->status);
        $this->assertNotNull($claim->submitted_at);
    }

    public function test_other_employee_cannot_update_nor_delete_claim(): void
    {
        $claim = $this->storeClaim($this->validItems());

        /** @var Employee $intruder */
        $intruder = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($intruder);

        // Garde stricte propriétaire (même tenant) : 403 sur update ET delete.
        $this->putJson("/api/v1/expense-claims/{$claim->id}", [
            'title' => 'Détournement',
            'items' => $this->validItems(),
        ])->assertForbidden();

        $this->deleteJson("/api/v1/expense-claims/{$claim->id}")->assertForbidden();
    }
}
