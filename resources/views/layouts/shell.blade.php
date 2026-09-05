{{--
    Coquille commune aux deux seules pages servies par le backend (accueil et
    documentation). Les styles sont inline plutôt que compilés par Vite : ce
    backend est une API, lui ajouter une chaîne de build front pour deux pages
    statiques coûterait plus qu'il ne rapporte.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ERP BTP - API de rapprochement')</title>
    <style>
        :root {
            --bg: #f6f7f9;
            --surface: #ffffff;
            --border: #e2e5ea;
            --text: #1c2430;
            --muted: #5d6b7f;
            --accent: #1d4ed8;
            --accent-hover: #1a43b8;
            --ok: #0f7b56;
            --warn: #b45309;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #10141b;
                --surface: #171d27;
                --border: #2a323f;
                --text: #e6eaf0;
                --muted: #9aa7b8;
                --accent: #7aa2ff;
                --accent-hover: #96b6ff;
                --ok: #4ade80;
                --warn: #fbbf24;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .wrap { max-width: 960px; margin: 0 auto; padding: 48px 24px 72px; }

        header.masthead { border-bottom: 1px solid var(--border); padding-bottom: 28px; margin-bottom: 32px; }

        .eyebrow {
            font-size: 12px; font-weight: 600; letter-spacing: .09em;
            text-transform: uppercase; color: var(--muted); margin: 0 0 10px;
        }

        h1 { font-size: 30px; line-height: 1.25; margin: 0 0 12px; letter-spacing: -0.02em; }
        h2 { font-size: 18px; margin: 36px 0 12px; letter-spacing: -0.01em; }

        p.lead { font-size: 16px; color: var(--muted); margin: 0; max-width: 62ch; }

        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin: 28px 0 0; }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 18px; border-radius: 8px; font-weight: 600; font-size: 14px;
            text-decoration: none; border: 1px solid transparent; transition: background-color .15s, border-color .15s;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-secondary { background: var(--surface); color: var(--text); border-color: var(--border); }
        .btn-secondary:hover { border-color: var(--accent); }

        .grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }

        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 18px 20px;
        }
        .card h3 { margin: 0 0 6px; font-size: 14px; letter-spacing: -0.01em; }
        .card p { margin: 0; font-size: 13.5px; color: var(--muted); }

        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        th, td { text-align: left; padding: 9px 12px; border-bottom: 1px solid var(--border); }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 600; }
        td code { font-size: 12.5px; }

        code, .mono {
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
            font-size: 13px;
            background: color-mix(in srgb, var(--accent) 10%, transparent);
            padding: 2px 6px; border-radius: 4px;
        }

        .table-scroll { overflow-x: auto; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); }
        .table-scroll table { min-width: 520px; }
        .table-scroll th, .table-scroll td { border-bottom-color: var(--border); }
        .table-scroll tr:last-child td { border-bottom: none; }

        footer.foot { margin-top: 48px; padding-top: 20px; border-top: 1px solid var(--border); color: var(--muted); font-size: 13px; }
        a { color: var(--accent); }
    </style>
    @stack('head')
</head>
<body>
@yield('body')
</body>
</html>
