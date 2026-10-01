<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141b2b">
    <title>@yield('title', $storeSettings['store_name'])</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="announcement">Authentic products <span>&middot;</span> Nationwide delivery across Pakistan</div>
    <header class="site-header">
        <div class="page-width header-main account-header-main">
            <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><span class="material-symbols-outlined">bolt</span></span>{{ $storeSettings['store_name'] }}</a>
            <nav class="account-header-nav">
                <a href="{{ route('home') }}">Store</a>
                <a href="{{ route('shop') }}">Shop</a>
                @auth('admin')
                    <a href="{{ route('admin.dashboard') }}">Admin dashboard</a>
                    <form action="{{ route('admin.logout') }}" method="post">@csrf<button type="submit" class="text-link">Admin log out</button></form>
                @else
                    @auth('web')
                        <a href="{{ route('account.index') }}">My account</a>
                        <form action="{{ route('logout') }}" method="post">@csrf<button type="submit" class="text-link">Log out</button></form>
                    @else
                        <a href="{{ route('login') }}">Log in</a>
                        <a href="{{ route('register') }}">Create account</a>
                    @endauth
                @endauth
            </nav>
        </div>
    </header>
    <main class="page-width account-main">
        @if (session('status'))<div class="status-message" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="error-message" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
    <footer class="site-footer">
        <div class="page-width footer-bottom"><span>Copyright {{ date('Y') }} {{ $storeSettings['store_name'] }}</span><a href="{{ route('shop') }}">Continue shopping</a><span>Prices shown in {{ $storeSettings['currency'] }}</span></div>
    </footer>
</body>
</html>
