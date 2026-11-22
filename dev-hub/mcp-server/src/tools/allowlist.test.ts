import { describe, expect, it } from "vitest";
import { loadAllTools, loadManifest } from "../manifest.js";

/**
 * Périmètre de l'allowlist OpenAPI (issue #9751).
 *
 * Ces tests verrouillent un CHOIX PRODUIT, pas un détail d'implémentation :
 * la surface MCP est un sous-ensemble stable et relisible de `api/openapi.yaml`,
 * pas un miroir de ses ~1 950 opérations. Ajouter une verticale est une
 * décision explicite — la retirer par inadvertance doit faire échouer la CI.
 */

const TRAVEL = "/travel/";
const RESTAURANT = "/restaurant/";

describe("allowlist OpenAPI — verticales Travel et Restaurant (#9751)", () => {
  it("expose des outils Travel et Restaurant", () => {
    const generated = loadAllTools().filter((tool) => tool.route !== undefined);

    const travel = generated.filter((tool) => tool.route!.path.includes(TRAVEL));
    const restaurant = generated.filter((tool) => tool.route!.path.includes(RESTAURANT));

    expect(travel.length, "aucun outil Travel exposé").toBeGreaterThan(0);
    expect(restaurant.length, "aucun outil Restaurant exposé").toBeGreaterThan(0);
  });

  it("n'expose que des routes /api/v1/ pour les deux verticales", () => {
    const generated = loadAllTools().filter(
      (tool) =>
        tool.route !== undefined &&
        (tool.route.path.includes(TRAVEL) || tool.route.path.includes(RESTAURANT)),
    );

    for (const tool of generated) {
      expect(tool.route!.path.startsWith("/api/v1/"), `${tool.name} hors /api/v1`).toBe(true);
    }
  });

  it("n'expose aucun endpoint /public/* ni /webhooks/*", () => {
    for (const tool of loadAllTools()) {
      if (tool.route === undefined) {
        continue;
      }
      expect(tool.route.path.includes("/public/"), `${tool.name} expose /public/`).toBe(false);
      expect(tool.route.path.includes("/webhooks/"), `${tool.name} expose /webhooks/`).toBe(false);
    }
  });

  it("ne crée aucune collision de nom avec le contrat A3", () => {
    const a3Names = new Set(loadManifest().tools.map((tool) => tool.name));
    const all = loadAllTools();

    // `loadAllTools` déduplique par nom : si une collision existait, le nombre
    // d'outils serait inférieur à la somme des deux sources.
    const generatedCount = all.filter((tool) => !a3Names.has(tool.name)).length;
    expect(all.length).toBe(a3Names.size + generatedCount);

    // Et chaque outil A3 reste exactement la définition A3 (précédence).
    const byName = new Map(all.map((tool) => [tool.name, tool]));
    for (const tool of loadManifest().tools) {
      expect(byName.get(tool.name)).toEqual(tool);
    }
  });

  it("garde les endpoints d'action hors de la surface CRUD par défaut", () => {
    // Les actions (`/approve`, `/publish`, `/rotate`…) relèvent du lot #9753 :
    // tant qu'elles ne sont pas générées explicitement, elles ne doivent pas
    // apparaître comme outils CRUD exécutables.
    const actionSegments = ["/approve", "/reject", "/publish", "/rotate", "/revoke", "/lock"];
    const generated = loadAllTools().filter((tool) => tool.route !== undefined);

    for (const tool of generated) {
      const path = tool.route!.path;
      const isAction = actionSegments.some((segment) => path.endsWith(segment));
      if (isAction) {
        // Une action ne peut être exposée que marquée comme telle (lot #9753).
        expect(tool.route!.action, `${tool.name} est une action non marquée`).toBe(true);
      }
    }
  });
});
