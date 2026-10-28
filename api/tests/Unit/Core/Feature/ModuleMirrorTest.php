<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Feature;

use App\Core\Feature\Domain\ModuleConsolidationDiff;
use App\Core\Feature\Domain\ModuleMirror;
use App\Core\Feature\Domain\ModuleRegistry;
use PHPUnit\Framework\TestCase;

/**
 * BOS-012 (#8199) — la dérivation de la vue `metadata.modules`.
 *
 * Test PUR (aucune base) : la dérivation est une fonction, elle se teste comme
 * telle. Les cas choisis visent les pièges réels de la consolidation, pas la
 * couverture de lignes.
 *
 * ⚠️ Le test le plus important du fichier est
 * {@see test_diagnostic_and_writer_agree_on_every_registry_mirror()} : c'est
 * lui qui interdit au diagnostic et à l'écriture de diverger. Sans lui, un
 * rapport pourrait annoncer un écart que `--apply` ne corrige pas — et la
 * commande bouclerait sans converger, ce qui est le pire des deux mondes (elle
 * écrit ET ne résout rien).
 */
class ModuleMirrorTest extends TestCase
{
    private ModuleRegistry $registry;

    /** @var array<string, string> */
    private array $mirrors;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ModuleRegistry;
        $this->mirrors = $this->registry->horizontalMirrors();
    }

    private function defaultFor(string $key): bool
    {
        return $this->registry->defaultFor($key);
    }

    /**
     * @param  array<array-key, mixed>  $modules
     * @param  array<array-key, mixed>  $features
     * @return array<array-key, mixed>
     */
    private function derive(array $modules, array $features): array
    {
        return ModuleMirror::derived($modules, $features, $this->mirrors, fn (string $k): bool => $this->defaultFor($k));
    }

    /**
     * @param  array<array-key, mixed>  $modules
     * @param  array<array-key, mixed>  $features
     * @return list<array<string, mixed>>
     */
    private function diff(array $modules, array $features): array
    {
        return ModuleConsolidationDiff::forCompany('c1', $modules, $features, $this->mirrors, fn (string $k): bool => $this->defaultFor($k));
    }

    public function test_the_platform_flag_wins_over_a_stale_client_selection(): void
    {
        // Le motif des incidents #7235 / #7432 : le client a `crm => true`, la
        // console plateforme a coupé le flag. La cible de #8199 est que la vue
        // suive la source — sinon un kill switch d'entitlement reste sans effet
        // côté interface.
        $derived = $this->derive(['crm' => true], ['crm' => false]);

        $this->assertFalse($derived['crm'], 'features est la source, metadata.modules la vue.');
    }

    public function test_non_mirror_client_keys_are_copied_untouched(): void
    {
        // `employees` / `attendance` n'ont AUCUN flag plateforme : ce ne sont
        // pas des dérivations. Les recalculer inventerait une source ; les
        // supprimer éteindrait des outils réels.
        $derived = $this->derive(
            ['employees' => true, 'attendance' => false, 'payroll' => true, 'crm' => false],
            ['crm' => true],
        );

        $this->assertTrue($derived['employees']);
        $this->assertFalse($derived['attendance']);
        $this->assertTrue($derived['payroll']);
        $this->assertTrue($derived['crm']);
    }

    public function test_an_undeclared_mirror_key_is_not_materialised(): void
    {
        // Personne n'a rien déclaré pour `crm`. `Company::moduleSelection()`
        // documente qu'une absence de sélection signifie « aucun verrouillage
        // rétroactif » : écrire `crm => false` transformerait une absence en
        // interdiction et masquerait le module pour tout tenant historique.
        $derived = $this->derive(['employees' => true], []);

        $this->assertArrayNotHasKey('crm', $derived);
        $this->assertArrayNotHasKey('accounting', $derived);
    }

    public function test_a_declared_client_key_with_no_platform_flag_still_converges(): void
    {
        // ⚠️ Cas qui décide de l'idempotence. Le client déclare `crm => true`
        // mais `features` n'a aucune clé `crm` : la valeur EFFECTIVE de la
        // source est alors le défaut du registre. Si le client s'accorde déjà
        // avec ce défaut, il n'y a RIEN à consolider — et le diagnostic doit le
        // dire, car l'écriture ne pose que la vue, jamais la source.
        $clientValue = true;
        $default = $this->defaultFor('crm');

        $drifts = $this->diff(['crm' => $clientValue], []);

        if ($clientValue === $default) {
            $this->assertSame(
                [],
                $drifts,
                'Un écart que l\'écriture ne peut pas résoudre ne doit pas être rapporté : --apply ne convergerait jamais.'
            );

            return;
        }

        $this->assertCount(1, $drifts);
        $this->assertSame(ModuleConsolidationDiff::KIND_MISSING_MIRROR, $drifts[0]['kind']);
    }

    public function test_a_legacy_non_boolean_value_is_not_a_declaration(): void
    {
        // Une carte JSON legacy peut porter `1` ou `"true"`. Seul un booléen PHP
        // est une déclaration : convertir `(bool) "false"` donnerait `true` et
        // fabriquerait un écart fantôme qui ferait écrire une valeur fausse.
        //
        // Conséquence assumée et cohérente : `crm => 1` est traité comme NON
        // déclaré, donc le flag plateforme (true) fait autorité et la vue est
        // normalisée en booléen. Le diagnostic le rapporte comme
        // `missing_client_key` — et c'est exactement ce que la dérivation
        // corrige, donc `--apply` converge bien.
        $derived = $this->derive(['crm' => 1], ['crm' => true]);

        $this->assertTrue($derived['crm'], 'La valeur legacy non booléenne est normalisée en booléen.');
        $this->assertSame(true, $derived['crm']);

        $drifts = $this->diff(['crm' => 1], ['crm' => true]);

        $this->assertCount(1, $drifts);
        $this->assertSame(ModuleConsolidationDiff::KIND_MISSING_CLIENT_KEY, $drifts[0]['kind']);
        $this->assertNull($drifts[0]['client_value'], 'Un entier legacy n\'est pas une déclaration.');
    }

    public function test_a_legacy_false_looking_string_never_becomes_true(): void
    {
        // Le piège précis : `(bool) "false"` vaut `true`. Si la dérivation
        // convertissait au lieu d'ignorer, la vue porterait `true` là où le
        // client avait écrit `"false"` — une activation de module inventée.
        $derived = $this->derive(['crm' => 'false'], ['crm' => false]);

        $this->assertFalse($derived['crm'], 'La chaîne "false" ne doit jamais produire une activation.');
    }

    public function test_the_derivation_is_idempotent(): void
    {
        // Deuxième passage = aucun changement. C'est la propriété qui rend
        // `--apply` rejouable : une écriture qui ne converge pas est
        // indistinguable d'un échec.
        $modules = ['crm' => true, 'employees' => true, 'training' => false];
        $features = ['crm' => false, 'training' => true];

        $once = $this->derive($modules, $features);
        $twice = $this->derive($once, $features);

        $this->assertSame($once, $twice);
        $this->assertTrue(ModuleMirror::isConverged($once, $twice));
    }

    public function test_key_order_does_not_fake_a_divergence(): void
    {
        // `derived()` range les clés non-miroir d'abord ; `metadata.modules`
        // peut les porter entrelacées. Une comparaison `===` directe conclurait
        // « non convergé » et `--apply` réécrirait la ligne à chaque exécution.
        $this->assertTrue(ModuleMirror::isSameMap(
            ['employees' => true, 'crm' => false],
            ['crm' => false, 'employees' => true],
        ));
    }

    public function test_the_comparison_stays_strict_on_values(): void
    {
        // Insensible à l'ORDRE, pas aux valeurs : `1` et `true` ne sont pas
        // égaux, donc une carte legacy non booléenne est bien normalisée.
        $this->assertFalse(ModuleMirror::isSameMap(['crm' => 1], ['crm' => true]));
        $this->assertFalse(ModuleMirror::isSameMap(['crm' => true], ['crm' => false]));
    }

    /**
     * ⚠️ LE test de cohérence : pour CHAQUE clé miroir du registre, ce que le
     * diagnostic rapporte et ce que l'écrivain corrige doivent coïncider.
     *
     * On exerce la matrice {déclaré/non déclaré} × {true/false} sur les deux
     * cartes, et on exige : soit le diff est vide, soit la dérivation change
     * réellement la vue. Un écart non résolu par la dérivation = `--apply` qui
     * ne converge pas. C'est exactement le défaut qu'une règle dupliquée entre
     * diagnostic et écriture finit toujours par produire.
     */
    public function test_diagnostic_and_writer_agree_on_every_registry_mirror(): void
    {
        $this->assertNotSame([], $this->mirrors, 'Le registre doit exposer des clés miroir, sinon ce test ne prouve rien.');

        $states = ['absent', true, false];
        $checked = 0;

        foreach (array_keys($this->mirrors) as $clientKey) {
            foreach ($states as $clientState) {
                foreach ($states as $flagState) {
                    $modules = $clientState === 'absent' ? [] : [$clientKey => $clientState];
                    $features = $flagState === 'absent' ? [] : [$this->mirrors[$clientKey] => $flagState];

                    $drifts = $this->diff($modules, $features);
                    $derived = $this->derive($modules, $features);

                    $changed = ! ModuleMirror::isConverged($modules, $derived);

                    if ($drifts === []) {
                        $this->assertFalse(
                            $changed,
                            "Aucun écart rapporté pour {$clientKey}, mais la dérivation modifie la vue : le diagnostic et l'écriture divergent."
                        );
                    } else {
                        $this->assertTrue(
                            $changed,
                            "Écart rapporté pour {$clientKey} que la dérivation ne corrige pas : --apply ne convergerait jamais."
                        );
                    }

                    // La convergence doit tenir au second passage, sans quoi la
                    // commande n'est pas rejouable.
                    $this->assertTrue(
                        ModuleMirror::isConverged($derived, $this->derive($derived, $features)),
                        "La dérivation de {$clientKey} n'est pas idempotente."
                    );

                    $checked++;
                }
            }
        }

        $this->assertGreaterThan(0, $checked);
    }

    public function test_the_derivation_never_returns_a_mirror_key_absent_from_the_view_when_declared(): void
    {
        // Contrepartie du test d'absence : dès qu'UN des deux côtés déclare la
        // clé, la vue la porte (elle est la projection de la source).
        $derived = $this->derive([], ['crm' => true]);

        $this->assertArrayHasKey('crm', $derived);
        $this->assertTrue($derived['crm']);
    }
}
