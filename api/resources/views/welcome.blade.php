<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Leopardo RH API</title>
        <style>
            :root {
                color-scheme: light;
                --bg: #f5f7fb;
                --card: #ffffff;
                --text: #0f172a;
                --muted: #475569;
                --primary: #0f766e;
                --border: #dbe2ea;
            }

            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: radial-gradient(circle at top right, #dcfce7 0, transparent 45%), var(--bg);
                color: var(--text);
                font: 16px/1.45 "Segoe UI", system-ui, -apple-system, sans-serif;
                padding: 24px;
            }

            .card {
                width: min(760px, 100%);
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 28px;
                box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            }

            h1 { margin: 0 0 8px; font-size: 1.8rem; }
            p { margin: 0; color: var(--muted); }
            .grid {
                margin-top: 20px;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 12px;
            }
            .tile {
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 14px;
                text-decoration: none;
                color: inherit;
                background: #fff;
            }
            .tile strong { display: block; margin-bottom: 4px; color: var(--primary); }
            .badge {
                display: inline-block;
                margin-top: 16px;
                background: #dcfce7;
                color: #166534;
                border-radius: 999px;
                padding: 6px 10px;
                font-size: 0.82rem;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <main class="card">
            <h1>Leopardo RH API</h1>
            <p>Backend Laravel en ligne. Utiliser les points d'entrée ci-dessous pour la validation Beta.</p>

            <div class="grid">
                <a class="tile" href="/api/v1/health">
                    <strong>Health Check</strong>
                    <span>Vérifier l'état de l'API</span>
                </a>
                <a class="tile" href="/login">
                    <strong>Espace Manager</strong>
                    <span>Connexion web et dashboard</span>
                </a>
            </div>

            <span class="badge">MVP Beta - Laravel 11 + PostgreSQL</span>
        </main>
    </body>
</html>
