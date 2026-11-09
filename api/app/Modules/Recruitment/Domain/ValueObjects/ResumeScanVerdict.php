<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\ValueObjects;

/**
 * BC-10 (#9217) — verdict d'analyse antivirus d'un CV.
 *
 * Trois états, et non deux. La distinction entre `clean` et `unavailable` est
 * le cœur de ce value object : un scanner en panne ou non configuré ne doit
 * JAMAIS produire un « sain » implicite. C'est exactement le faux sentiment de
 * sécurité qu'on cherche à éviter — un CV non analysé qui serait marqué sain
 * serait pire que pas de scan du tout, parce qu'on croirait le fichier vérifié.
 *
 * `unavailable` est donc un état de premier ordre, persisté tel quel
 * (`resume_scan_status`), et visible dans l'écran recruteur.
 */
final readonly class ResumeScanVerdict
{
    public const CLEAN = 'clean';

    public const INFECTED = 'infected';

    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public string $status,
        public ?string $signature = null,
        public ?string $engine = null,
        public ?string $reason = null,
    ) {}

    public static function clean(string $engine): self
    {
        return new self(self::CLEAN, engine: $engine);
    }

    /**
     * @param  string  $signature  nom de la signature détectée (ex. `Eicar-Test-Signature`)
     */
    public static function infected(string $engine, string $signature): self
    {
        return new self(self::INFECTED, signature: $signature, engine: $engine);
    }

    /**
     * Le fichier n'a PAS pu être analysé. Ce n'est pas un « sain ».
     *
     * @param  string  $reason  cause lisible, journalisée et affichée au recruteur
     */
    public static function unavailable(string $engine, string $reason): self
    {
        return new self(self::UNAVAILABLE, engine: $engine, reason: $reason);
    }

    public function isClean(): bool
    {
        return $this->status === self::CLEAN;
    }

    public function isInfected(): bool
    {
        return $this->status === self::INFECTED;
    }

    public function isUnavailable(): bool
    {
        return $this->status === self::UNAVAILABLE;
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'signature' => $this->signature,
            'engine' => $this->engine,
            'reason' => $this->reason,
        ];
    }
}
