<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SGCM API · Estado del sistema</title>
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
            --ok: #0f8a6d;
            --ok-soft: #e6f6f1;
            --warn: #b46a13;
            --warn-soft: #fdf2e0;
            --err: #c02626;
            --err-soft: #fdeaea;
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
        a { color: var(--primary); }

        .nav {
            background: var(--dark);
            padding: 14px 24px;
        }
        .nav-inner {
            max-width: 1080px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #fff;
            text-decoration: none;
        }
        .brand .logo {
            width: 30px;
            height: 30px;
            border-radius: 9px;
            background: linear-gradient(135deg, var(--primary), #4da3e0);
            display: grid;
            place-items: center;
            font-size: 14px;
        }
        .nav-links { display: flex; gap: 6px; flex-wrap: wrap; }
        .nav-links a {
            color: rgba(255, 255, 255, 0.78);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 9px;
            transition: background 0.16s ease, color 0.16s ease;
        }
        .nav-links a:hover { background: rgba(255, 255, 255, 0.1); color: #fff; }

        .hero {
            background: linear-gradient(160deg, var(--primary-shade) 0%, var(--dark) 65%);
            color: #fff;
            padding: 52px 24px 66px;
        }
        .wrap { max-width: 1080px; margin: 0 auto; width: 100%; }
        .hero-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }
        .hero h1 { font-size: clamp(1.7rem, 4vw, 2.4rem); font-weight: 800; letter-spacing: -0.02em; }
        .hero .sub { margin-top: 6px; max-width: 560px; font-size: 0.98rem; line-height: 1.5; color: rgba(255, 255, 255, 0.78); }
        .hero .updated { margin-top: 14px; font-size: 0.82rem; color: rgba(255, 255, 255, 0.6); }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 10px 18px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            font-size: 0.9rem;
            font-weight: 700;
        }
        .pill .dot { width: 12px; height: 12px; border-radius: 50%; }
        .pill.ok .dot { background: #3fc89f; box-shadow: 0 0 0 5px rgba(63, 200, 159, 0.28); }
        .pill.degraded .dot { background: #f5b95e; box-shadow: 0 0 0 5px rgba(245, 185, 94, 0.28); }
        .pill.down .dot { background: #ff6b6b; box-shadow: 0 0 0 5px rgba(255, 107, 107, 0.28); }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-top: -34px;
            padding-bottom: 44px;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(7, 26, 47, 0.07);
        }
        .card h2 {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-soft);
            margin-bottom: 14px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: none;
        }
        .chip .dot { width: 8px; height: 8px; border-radius: 50%; }
        .chip.ok { background: var(--ok-soft); color: var(--ok); }
        .chip.ok .dot { background: var(--ok); }
        .chip.warn { background: var(--warn-soft); color: var(--warn); }
        .chip.warn .dot { background: var(--warn); }
        .chip.err { background: var(--err-soft); color: var(--err); }
        .chip.err .dot { background: var(--err); }

        .big { font-size: 1.35rem; font-weight: 800; color: var(--dark); }
        .card dl { display: grid; gap: 8px; }
        .card dl div { display: flex; gap: 8px; }
        dt { min-width: 96px; font-size: 0.82rem; color: var(--text-soft); font-weight: 600; }
        dd { font-size: 0.87rem; color: var(--text); font-weight: 600; word-break: break-all; }
        code {
            font-family: Consolas, 'Cascadia Mono', monospace;
            background: rgba(29, 114, 181, 0.08);
            color: var(--primary-shade);
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 0.82rem;
        }
        .msg { margin-top: 10px; font-size: 0.85rem; line-height: 1.45; color: var(--text-soft); }
        .msg.err { color: var(--err); }

        .section-title {
            max-width: 1080px;
            margin: 8px auto 14px;
            padding: 0 24px;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-soft);
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 14px;
            padding-bottom: 44px;
        }
        .stat {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .stat:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(7, 26, 47, 0.1); }
        .stat .num { font-size: 1.9rem; font-weight: 800; color: var(--primary); line-height: 1.1; }
        .stat .lbl { margin-top: 4px; font-size: 0.82rem; font-weight: 600; color: var(--text-soft); }
        .stat .bar { margin-top: 10px; height: 5px; border-radius: 999px; background: rgba(29, 114, 181, 0.12); overflow: hidden; }
        .stat .bar span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--primary), #4da3e0); }

        .banner {
            max-width: 1080px;
            margin: 0 auto 44px;
            padding: 0 24px;
        }
        .banner-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-shade) 100%);
            color: #fff;
            border-radius: 16px;
            padding: 22px 26px;
            box-shadow: 0 12px 30px rgba(29, 114, 181, 0.3);
        }
        .banner-inner h3 { font-size: 1.05rem; font-weight: 800; }
        .banner-inner p { font-size: 0.88rem; color: rgba(255, 255, 255, 0.85); margin-top: 4px; }
        a.btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            background: #fff;
            color: var(--primary-shade);
            transition: transform 0.16s ease, box-shadow 0.16s ease;
        }
        a.btn:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(0, 0, 0, 0.2); }

        footer {
            margin-top: auto;
            padding: 22px;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-soft);
            border-top: 1px solid var(--border);
        }

        @media (max-width: 640px) {
            .hero { padding: 36px 18px 56px; }
            .section-title, .banner { padding-inline: 18px; }
        }

        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.55; } }
        .pill.pulse .dot { animation: pulse 2s ease-in-out infinite; }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a class="brand" href="/">
                <span class="logo">SG</span>
                SGCM Platform
            </a>
            <div class="nav-links">
                <a href="/">Inicio</a>
                <a href="{{ $docsUrl }}">Documentacion API</a>
                <a href="{{ url('/status') }}">Estado del sistema</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="wrap hero-top">
            <div>
                <h1>Estado del sistema</h1>
                <p class="sub">Backend oficial del Sistema de Gestion y Control Migratorio Raizal. Chequeo de salud del servidor, base de datos y asistente IA.</p>
                <p class="updated">Actualizado: {{ $generatedAt }} · Hora del servidor: {{ now()->format('H:i:s') }}</p>
            </div>
            <span class="pill {{ $level }} @if($level === 'ok') pulse @endif">
                <span class="dot"></span>
                @if ($level === 'ok')
                    Operativo
                @elseif ($level === 'degraded')
                    Degradado
                @else
                    Caido
                @endif
            </span>
        </div>
    </header>

    <main class="wrap">
        <div class="grid">
            <section class="card">
                <h2>Base de datos @if($db['ok'])
                    <span class="chip ok"><span class="dot"></span>Operativo</span>
                @else
                    <span class="chip err"><span class="dot"></span>Caido</span>
                @endif</h2>
                <dl>
                    <div><dt>Motor</dt><dd>{{ $db['label'] }}</dd></div>
                    <div><dt>Servidor</dt><dd><code>{{ $db['host'] }}:{{ $db['port'] }}</code></dd></div>
                    <div><dt>Base de datos</dt><dd><code>{{ $db['database'] }}</code></dd></div>
                    @if ($db['ok'])
                        <div><dt>Latencia</dt><dd>{{ $db['latencyMs'] }} ms</dd></div>
                    @endif
                </dl>
                @if (! $db['ok'])
                    <p class="msg err">{{ $db['message'] }}</p>
                @endif
            </section>

            <section class="card">
                <h2>Asistente IA · Gemini @if($gemini['ok'])
                    <span class="chip ok"><span class="dot"></span>{{ $gemini['label'] }}</span>
                @elseif($gemini['configured'])
                    <span class="chip err"><span class="dot"></span>{{ $gemini['label'] }}</span>
                @else
                    <span class="chip warn"><span class="dot"></span>{{ $gemini['label'] }}</span>
                @endif</h2>
                <dl>
                    <div><dt>Modelo</dt><dd><code>{{ $gemini['model'] }}</code></dd></div>
                    <div><dt>Proveedor</dt><dd>Google Gemini API</dd></div>
                    <div><dt>Asistente</dt><dd>Miss Lorna</dd></div>
                </dl>
                @if (isset($gemini['message']))
                    <p class="msg err">{{ $gemini['message'] }}</p>
                @else
                    <p class="msg">El chatbot esta conectado con Google Gemini y responde en vivo.</p>
                @endif
            </section>

            <section class="card">
                <h2>Versiones</h2>
                <dl>
                    <div><dt>PHP</dt><dd>{{ $phpFull }}</dd></div>
                    <div><dt>Laravel</dt><dd>{{ $framework }}</dd></div>
                    <div><dt>API base</dt><dd><code>{{ $apiBase }}</code></dd></div>
                    <div><dt>Docs</dt><dd><a href="{{ $docsUrl }}">/api/documentation</a></dd></div>
                </dl>
            </section>

            <section class="card">
                <h2>Configuracion</h2>
                <dl>
                    <div><dt>Entorno</dt><dd><code>{{ $appEnv }}</code></dd></div>
                    <div><dt>Depuracion</dt><dd>{{ $debug ? 'Activada' : 'Desactivada' }}</dd></div>
                    <div><dt>Zona horaria</dt><dd>{{ $timezone }}</dd></div>
                    <div><dt>Servicio</dt><dd>SGCM API</dd></div>
                </dl>
            </section>
        </div>

        <p class="section-title">Actividad en la plataforma</p>
        <div class="stats">
            @forelse ($counts as $stat)
                <div class="stat">
                    <div class="num">{{ number_format($stat['count']) }}</div>
                    <div class="lbl">{{ $stat['label'] }}</div>
                    <div class="bar"><span style="width: {{ min(100, max(4, $stat['count'] * 4)) }}%"></span></div>
                </div>
            @empty
                <div class="stat">
                    <div class="lbl">Sin datos disponibles</div>
                    <div class="bar"></div>
                </div>
            @endforelse
        </div>

        <div class="banner">
            <div class="banner-inner">
                <div>
                    <h3>Documentacion interactiva</h3>
                    <p>Explora y prueba los {{ count($counts) ? 'principales' : '' }} endpoints de la API con Swagger UI.</p>
                </div>
                <a class="btn" href="{{ $docsUrl }}">Abrir Swagger UI &rarr;</a>
            </div>
        </div>
    </main>

    <footer>
        SGCM API &middot; {{ $framework }} &middot; {{ $appEnv }} &middot; Pagina generada: {{ $generatedAt }}
    </footer>
</body>
</html>