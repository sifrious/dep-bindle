<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bindle</title>
    @yield('head')
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            margin: 0; padding: 2rem; max-width: 1100px; margin-inline: auto;
            color: #1b1b22; background: #fafafa;
        }
        h1 { font-size: 1.4rem; margin: 0 0 .25rem; }
        h2 { font-size: 1.05rem; margin: 2rem 0 .75rem; }
        .muted { color: #6b6b76; }
        a { color: #2952cc; }
        header { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e6e6ea; border-radius: 8px; overflow: hidden; }
        th, td { text-align: left; padding: .55rem .75rem; border-bottom: 1px solid #efeff2; vertical-align: middle; }
        th { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #6b6b76; background: #f4f4f6; }
        tr:last-child td { border-bottom: 0; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85em; }
        form { margin: 0; }
        button {
            font: inherit; cursor: pointer; border: 1px solid #2952cc; background: #2952cc; color: #fff;
            padding: .35rem .7rem; border-radius: 6px;
        }
        button:hover { background: #1f43ab; }
        button.secondary { background: #fff; color: #2952cc; }
        button.secondary:hover { background: #eef2ff; }
        button:disabled { opacity: .45; cursor: not-allowed; }
        .badge { display: inline-block; padding: .1rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
        .badge.method { background: #eef2ff; color: #2952cc; }
        .badge.running { background: #fff4d6; color: #8a6d00; }
        .badge.completed { background: #def7e5; color: #1c7c3c; }
        .badge.failed { background: #fde8e8; color: #8a1f1f; }
        .badge.driver-null { background: #ececf1; color: #55555f; }
        .badge.driver-dusk { background: #def7e5; color: #1c7c3c; }
        .badge.state-empty { background: #ececf1; color: #55555f; }
        .badge.state-loading { background: #fff4d6; color: #8a6d00; }
        .badge.state-error { background: #fde8e8; color: #8a1f1f; }
        .badge.state-populated { background: #def7e5; color: #1c7c3c; }
        .banner { background: #fff4d6; border: 1px solid #f0d98a; color: #6e5600; padding: .75rem 1rem; border-radius: 8px; margin: 1rem 0; }
        .error { background: #fde8e8; border: 1px solid #f5b5b5; color: #8a1f1f; padding: .75rem 1rem; border-radius: 8px; margin: 1rem 0; }
        .notice { background: #def7e5; border: 1px solid #a5dfba; color: #14532d; padding: .75rem 1rem; border-radius: 8px; margin: 1rem 0; }
        .toolbar { margin: 1rem 0; }
        .state-link {
            display: inline-block;
            margin-right: .35rem;
            margin-bottom: .35rem;
            padding: .25rem .6rem;
            border: 1px solid #c9c9d2;
            border-radius: 999px;
            text-decoration: none;
            color: inherit;
            background: #fff;
        }
        .state-link.active {
            border-color: #2952cc;
            background: #eef2ff;
            color: #2952cc;
            font-weight: 600;
        }
        .board-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: .75rem;
        }
        .region-card {
            background: #fff;
            border: 1px solid #e6e6ea;
            border-radius: 8px;
            padding: .75rem;
        }
        .region-card h3 { margin: 0 0 .45rem; font-size: .95rem; }
        .region-card p { margin: .3rem 0; }
        .region-card.state-empty { border-style: dashed; }
        .region-card.state-loading { box-shadow: inset 0 0 0 2px #fff4d6; }
        .region-card.state-error { box-shadow: inset 0 0 0 2px #fde8e8; }
        .region-card.state-populated { box-shadow: inset 0 0 0 2px #def7e5; }
        .driver-form { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
        select { font: inherit; padding: .3rem .4rem; border: 1px solid #c9c9d2; border-radius: 6px; background: #fff; color: inherit; }
        pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85em; }
        pre.log { background: #1b1b22; color: #e6e6ea; padding: .75rem 1rem; border-radius: 8px; overflow-x: auto; }
        .visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    </style>
</head>
<body>
@yield('content')
</body>
</html>
