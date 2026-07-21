<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\ValidIban;
use PHPUnit\Framework\TestCase;

class ValidIbanTest extends TestCase
{
    public function test_accepts_valid_fr_iban(): void
    {
        $this->assertTrue(ValidIban::isValid('FR7630006000011234567890189'));
    }

    public function test_accepts_valid_ma_iban(): void
    {
        $this->assertTrue(ValidIban::isValid('MA64011519000001205000534921'));
    }

    public function test_accepts_valid_dz_iban(): void
    {
        $this->assertTrue(ValidIban::isValid('DZ7700123456789012345678'));
    }

    public function test_accepts_valid_tr_iban(): void
    {
        $this->assertTrue(ValidIban::isValid('TR330006100519786457841326'));
    }

    public function test_accepts_valid_de_and_gb_iban(): void
    {
        $this->assertTrue(ValidIban::isValid('DE89370400440532013000'));
        $this->assertTrue(ValidIban::isValid('GB82WEST12345698765432'));
    }

    public function test_rejects_wrong_length_for_country(): void
    {
        // FR IBANs must be 27 chars; this one is one short.
        $this->assertFalse(ValidIban::isValid('FR76300060000112345678901'));
    }

    public function test_rejects_bad_checksum(): void
    {
        // Valid FR IBAN with the last digit flipped -> checksum no longer matches.
        $this->assertFalse(ValidIban::isValid('FR7630006000011234567890180'));
    }

    public function test_rejects_unknown_country_code(): void
    {
        $this->assertFalse(ValidIban::isValid('XX1234567890'));
    }

    public function test_rejects_malformed_structure(): void
    {
        $this->assertFalse(ValidIban::isValid(''));
        $this->assertFalse(ValidIban::isValid('NOT-AN-IBAN'));
        $this->assertFalse(ValidIban::isValid('123456789012345678901234'));
    }

    public function test_normalize_strips_spaces_and_dashes_and_uppercases(): void
    {
        $this->assertSame(
            'FR7630006000011234567890189',
            ValidIban::normalize('fr76 3000 6000 0112-3456-7890 189')
        );
    }

    public function test_validate_fails_closed_with_message_on_invalid_value(): void
    {
        $rule = new ValidIban;
        $failed = null;

        $rule->validate('iban', 'FR76300060000112345678901', function (string $message) use (&$failed) {
            $failed = $message;

            return new class
            {
                public function translate(): void {}
            };
        });

        $this->assertNotNull($failed);
    }

    public function test_validate_passes_silently_on_valid_value(): void
    {
        $rule = new ValidIban;
        $failed = null;

        $rule->validate('iban', 'FR7630006000011234567890189', function (string $message) use (&$failed) {
            $failed = $message;

            return new class
            {
                public function translate(): void {}
            };
        });

        $this->assertNull($failed);
    }

    public function test_validate_ignores_non_string_and_empty_values(): void
    {
        $rule = new ValidIban;
        $calls = 0;

        $fail = function (string $message) use (&$calls) {
            $calls++;

            return new class
            {
                public function translate(): void {}
            };
        };

        $rule->validate('iban', '', $fail);
        $rule->validate('iban', null, $fail);

        $this->assertSame(0, $calls);
    }
}
