// Route handler pour le share_target déclaré dans la route dynamique /manifest
// (action: "/share", method: POST, enctype: multipart/form-data).
// Le navigateur (Web Share Target PWA) envoie title/text/url en FormData ;
// on consomme le body puis on redirige vers l'inscription.
export async function POST(request: Request): Promise<Response> {
  await request.formData();
  return Response.redirect(new URL("/signup?source=pwa_share", request.url), 303);
}
