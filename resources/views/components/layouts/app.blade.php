<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name') }}</title>
        <link rel="icon" href="{{ $brandLogoUrl ?: asset('favicon.ico') }}">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        @php
            $openAiConnected = filled(config('services.openai.key'));
            $navGroups = [
                [
                    'section' => 'Operations',
                    'items' => [
                        ['label' => 'Command Dashboard', 'route' => route('dashboard'), 'icon' => 'bi-grid-1x2-fill', 'active' => ['dashboard']],
                        ['label' => 'Message Records', 'route' => route('messages.index'), 'icon' => 'bi-chat-square-text-fill', 'active' => ['messages.*']],
                    ],
                ],
                [
                    'section' => 'AI Tools',
                    'items' => [
                        ['label' => 'New Analysis', 'route' => route('dashboard').'#analysis-form', 'icon' => 'bi-activity', 'active' => []],
                    ],
                ],
            ];
        @endphp

        <div class="cp-shell">
            <aside class="cp-sidebar" aria-label="Primary navigation">
                <div class="cp-sidebar-scroll">
                    <a href="{{ route('dashboard') }}" class="cp-brand">
                        @if ($brandLogoUrl)
                            <span class="cp-brand-logo"><img src="{{ $brandLogoUrl }}" alt="{{ config('app.name') }} logo"></span>
                        @else
                            <span class="cp-brand-mark">CP</span>
                        @endif
                        <span>
                            <small>CrisisPulse</small>
                            <strong>AI Monitor</strong>
                        </span>
                    </a>

                    <div class="cp-profile">
                        <span class="cp-profile-avatar"><i class="bi bi-hospital"></i></span>
                        <div>
                            <div class="cp-profile-role">{{ auth()->user()->roleLabel() }}</div>
                            <div class="cp-profile-name">{{ auth()->user()->name }}</div>
                            <div class="cp-profile-email">{{ auth()->user()->email }}</div>
                        </div>
                    </div>

                    <div class="cp-utility">
                        <i class="bi bi-lightning-charge-fill"></i>
                        <span>{{ $openAiConnected ? 'AI analysis online' : 'Local fallback active' }}</span>
                    </div>

                    <nav class="cp-nav">
                        @foreach ($navGroups as $group)
                            <div class="cp-menu-caption">{{ $group['section'] }}</div>
                            @foreach ($group['items'] as $item)
                                @php($isActive = ! empty($item['active']) && request()->routeIs(...$item['active']))
                                <a class="cp-nav-link {{ $isActive ? 'active' : '' }}" href="{{ $item['route'] }}">
                                    <span class="cp-nav-icon"><i class="bi {{ $item['icon'] }}"></i></span>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        @endforeach
                        @if (auth()->user()->isAdmin())
                            <div class="cp-menu-caption">Administration</div>
                            <a class="cp-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                                <span class="cp-nav-icon"><i class="bi bi-people-fill"></i></span>
                                <span>User Accounts</span>
                            </a>
                            <a class="cp-nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}">
                                <span class="cp-nav-icon"><i class="bi bi-palette-fill"></i></span>
                                <span>Brand Settings</span>
                            </a>
                        @endif
                    </nav>

                    <div class="cp-sidebar-footer">
                        <h6>Monitoring Focus</h6>
                        <div class="cp-announcement">
                            <strong>Patient trust signals</strong>
                            <span>Track complaints, misinformation, praise, urgency, and reputational risk from public communication.</span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="cp-logout-button"><i class="bi bi-box-arrow-right"></i> Sign out</button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="cp-sidebar-backdrop" data-close-sidebar></div>

            <main class="cp-main">
                <header class="cp-topbar">
                    <div class="cp-header-side">
                        <button type="button" class="cp-mobile-toggle" data-open-sidebar aria-label="Open navigation">
                            <i class="bi bi-list"></i>
                        </button>
                        <div>
                            <div class="cp-header-kicker">AI-based crisis communication monitoring</div>
                            <h1 class="cp-header-title">Korle Bu Teaching Hospital</h1>
                            <p class="cp-header-subtitle">Accra public feedback and response intelligence</p>
                        </div>
                    </div>
                    <div class="cp-header-actions">
                        <span class="cp-status-chip {{ $openAiConnected ? 'online' : 'fallback' }}">
                            <i class="bi {{ $openAiConnected ? 'bi-check-circle-fill' : 'bi-shield-exclamation' }}"></i>
                            {{ $openAiConnected ? 'AI Connected' : 'Fallback Mode' }}
                        </span>
                        <a href="{{ route('messages.index') }}" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-archive me-1"></i> Records
                        </a>
                    </div>
                </header>

                <section class="cp-content">
                    @if (session('status'))
                        <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger border-0 shadow-sm">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    {{ $slot }}
                </section>
            </main>
        </div>

        <x-crisis-assistant />

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            (() => {
                let navigating = false;

                async function navigate(url, push = true) {
                    if (navigating) return;

                    const target = new URL(url, window.location.href);
                    if (target.origin !== window.location.origin) {
                        window.location.assign(target.href);
                        return;
                    }

                    navigating = true;
                    document.body.classList.add('cp-page-loading');

                    try {
                        const response = await fetch(target.href, {
                            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
                            credentials: 'same-origin',
                        });
                        if (!response.ok) throw new Error('Page navigation failed.');

                        const documentText = await response.text();
                        const nextDocument = new DOMParser().parseFromString(documentText, 'text/html');
                        const nextContent = nextDocument.querySelector('.cp-content');
                        const currentContent = document.querySelector('.cp-content');
                        if (!nextContent || !currentContent) throw new Error('Page content was not found.');

                        currentContent.replaceWith(nextContent);
                        document.title = nextDocument.title;
                        if (push) window.history.pushState({}, '', target.href);
                        window.scrollTo({top: 0, behavior: 'instant'});
                        window.dispatchEvent(new CustomEvent('crisispulse:navigated', {detail: {content: nextContent}}));

                        if (target.hash) {
                            window.setTimeout(() => document.querySelector(target.hash)?.scrollIntoView({behavior: 'smooth'}), 50);
                        }
                    } catch (_) {
                        window.location.assign(target.href);
                    } finally {
                        navigating = false;
                        document.body.classList.remove('cp-page-loading');
                    }
                }

                window.CrisisPulseNavigate = navigate;

                document.addEventListener('click', (event) => {
                    const link = event.target.closest('a[href]');
                    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank' || link.hasAttribute('download')) return;

                    const target = new URL(link.href, window.location.href);
                    if (target.origin !== window.location.origin || target.pathname === '/login' || link.getAttribute('href')?.startsWith('#')) return;

                    event.preventDefault();
                    navigate(target.href);
                });

                window.addEventListener('popstate', () => navigate(window.location.href, false));
            })();

            document.addEventListener('DOMContentLoaded', () => {
                document.querySelector('[data-open-sidebar]')?.addEventListener('click', () => {
                    document.body.classList.add('cp-sidebar-open');
                });

                document.querySelector('[data-close-sidebar]')?.addEventListener('click', () => {
                    document.body.classList.remove('cp-sidebar-open');
                });

                document.addEventListener('click', (event) => {
                    if (window.innerWidth >= 992) {
                        return;
                    }

                    if (event.target.closest('.cp-nav-link')) {
                        document.body.classList.remove('cp-sidebar-open');
                    }
                });
            });
        </script>
    </body>
</html>
