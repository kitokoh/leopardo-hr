import nextConfig from "../../next.config";

type RedirectRule = {
  source: string;
  destination: string;
  permanent?: boolean;
};

async function getRedirects(): Promise<RedirectRule[]> {
  const redirects = nextConfig.redirects;
  if (typeof redirects !== "function") {
    return [];
  }

  return (await redirects()) as RedirectRule[];
}

/**
 * #7259 — le lien d'activation envoyé par e-mail est construit côté API en
 * `{FRONTEND_URL}/activate/{token}` alors que la page servie par le portail est
 * `/auth/activate/{token}`. Sans redirection, le lien répond 404 (constaté en
 * dev et en prod) et aucun employé invité ne peut activer son compte.
 */
describe("next.config — redirection d'activation d'invitation (#7259)", () => {
  it("redirige /activate/:token vers /auth/activate/:token", async () => {
    const redirects = await getRedirects();
    const rule = redirects.find((entry) => entry.source === "/activate/:token");

    expect(rule).toBeDefined();
    expect(rule?.destination).toBe("/auth/activate/:token");
  });

  it("reste une redirection non permanente (compatibilité, pas règle définitive)", async () => {
    const redirects = await getRedirects();
    const rule = redirects.find((entry) => entry.source === "/activate/:token");

    expect(rule?.permanent).toBe(false);
  });
});
