<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SGCM API - Estado del servicio</title>
    <style>
        :root {
            --primary: #1d72b5;
            --primary-shade: #0f4d7e;
            --dark: #071a2f;
            --text: #132a45;
            --text-soft: #5a6b7f;
            --surface: #ffffff;
            --bg: #f2f5f9;
            --border: #dbe3ec;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .hero {
            background: linear-gradient(160deg, var(--primary-shade) 0%, var(--dark) 60%);
            color: #fff;
            padding: 70px 24px 96px;
        }
        .wrap { max-width: 980px; margin: 0 auto; width: 100%; }
        .brand { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
        .pill {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 8px 14px; border-radius: 999px;
            font-size: 0.85rem; font-weight: 600; letter-spacing: .05em;
        }
        .pill .dot { width: 9px; height: 9px; border-radius: 50%; background: #3fc89f; box-shadow: 0 0 0 4px rgba(63, 200, 159, .25); }
        .kicker { margin-top: 36px; font-size: .8rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: rgba(255, 255, 255, .72); }
        h1 { margin: 8px 0 12px; font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; letter-spacing: -0.02em; }
        .sub { max-width: 640px; font-size: 1.05rem; line-height: 1.5; color: rgba(255, 255, 255, .85); }
        .grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px; margin-top: -52px; padding-bottom: 64px;
        }
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; padding: 22px;
            box-shadow: 0 10px 28px rgba(7, 26, 47, .08);
            display: flex; flex-direction: column; gap: 8px;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .card:hover { transform: translateY(-3px); box-shadow: 0 18px 40px rgba(7, 26, 47, .12); }
        .card.primary { border-top: 4px solid var(--primary); }
        h2 { font-size: 1.05rem; font-weight: 700; color: var(--dark); }
        .card p { font-size: .88rem; line-height: 1.5; color: var(--text-soft); }
        .card .value { font-size: 1.8rem; font-weight: 800; color: var(--primary); }
        a.btn {
            margin-top: auto; display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; padding: 11px 16px; border-radius: 12px; text-decoration: none;
            font-weight: 700; font-size: .9rem; background: var(--primary); color: #fff;
            transition: background .16s ease;
        }
        a.btn:hover { background: var(--primary-shade); }
        a.btn.ghost { background: transparent; color: var(--primary); border: 1.5px solid var(--primary); }
        a.btn.ghost:hover { background: rgba(29, 114, 181, .06); }
        pre {
            background: var(--dark); color: #cfe3f5; padding: 12px 14px; border-radius: 10px;
            font-size: .82rem; overflow-x: auto; margin-top: 4px;
        }
        ul.meta { list-style: none; display: flex; flex-direction: column; gap: 6px; font-size: .86rem; color: var(--text-soft); }
        ul.meta b { color: var(--dark); font-weight: 700; }
        footer { margin-top: auto; padding: 22px; text-align: center; font-size: .8rem; color: var(--text-soft); border-top: 1px solid var(--border); }
        @media (max-width: 640px) {
            .hero { padding: 44px 18px 80px; }
            .grid { padding-bottom: 44px; }
        }
    </style>
</head>
<body>
    <header class="hero">
        <div class="wrap">
            <div class="brand">
                <strong class="pill">SGCM Platform</strong>
                <span class="pill"><span class="dot"></span> Servicio activo</span>
            </div>
            <p class="kicker">Sistema de Gestion y Control Migratorio Raizal · San Andres Islas, Colombia</p>
            <h1>SGCM API</h1>
            <p class="sub">Backend oficial de la aplicacion movil: autenticacion JWT, tramites, radicados, citas, notificaciones, tarjeta de turismo, constancias PDF+QR y asistente IA.</p>
        </div>
    </header>

    <main class="wrap grid">
        <section class="card primary">
            <h2>Documentacion de la API</h2>
            <p>Explora y prueba todos los endpoints interactivamente con Swagger UI.</p>
            <a class="btn" href="{{ url('api/documentation') }}">Abrir Swagger UI &rarr;</a>
        </section>

        <section class="card">
            <h2>Estado del sistema</h2>
            <p>Dashboard en vivo: salud del servidor, base de datos y asistente IA.</p>
            <a class="btn ghost" href="{{ url('/status') }}">Ver dashboard &rarr;</a>
        </section>

        <section class="card">
            <h2>Health check</h2>
            <p>Respuesta JSON para la aplicacion movil:</p>
            <pre>GET /api/health</pre>
            <a class="btn ghost" href="{{ url('api/health') }}">Abrir &rarr;</a>
        </section>

        <section class="card">
            <h2>Version</h2>
            <div class="value">{{ $phpVersion }}</div>
            <p>{{ $framework }}</p>
            <ul class="meta">
                <li><b>Entorno:</b> {{ $appEnv }}</li>
                <li><b>API base:</b> <code>/api</code></li>
            </ul>
        </section>

        <section class="card">
            <h2>Endpoints</h2>
            <div class="value">{{ $endpointCount }}</div>
            <p>rutas de API registradas ({{ $controllerCount }} controladores).</p>
        </section>
    </main>

    <footer>
        {{ $appName }} &middot; {{ $year }} &middot; Pagina generada: {{ $generatedAt }}
    </footer>
</body>
</html>