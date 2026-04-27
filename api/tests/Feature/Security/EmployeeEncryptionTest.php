<?php

namespace Tests\Feature\Security;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeEncryptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_encrypts_sensitive_data_in_database(): void
    {
        // 1. Créer un employé avec des données sensibles
        $iban = 'DZ1234567890123456789012';
        $bankAccount = '1234567890';
        $nationalId = '9876543210';

        $employee = Employee::factory()->create([
            'iban' => $iban,
            'bank_account' => $bankAccount,
            'national_id' => $nationalId,
        ]);

        // 2. Vérifier que les données sont lisibles via le modèle (déchiffrement transparent)
        $this->assertEquals($iban, $employee->iban);
        $this->assertEquals($bankAccount, $employee->bank_account);
        $this->assertEquals($nationalId, $employee->national_id);

        // 3. Vérifier que les données sont CHIFFRÉES directement en base de données
        $rawEmployee = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertNotEquals($iban, $rawEmployee->iban);
        $this->assertNotEquals($bankAccount, $rawEmployee->bank_account);
        $this->assertNotEquals($nationalId, $rawEmployee->national_id);
        
        // Vérifier que ce n'est pas vide non plus
        $this->assertNotEmpty($rawEmployee->iban);
    }
}
