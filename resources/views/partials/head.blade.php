<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="theme-color" content="#0b1220" />

<title>{{ isset($title) ? $title . ' · ' : '' }}{{ config('app.name') }}</title>

<link rel="icon" href="/favicon.svg" type="image/svg+xml">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:500" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
