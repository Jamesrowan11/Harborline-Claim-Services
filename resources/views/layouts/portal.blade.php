<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Dashboard')) — {{ \App\Services\Settings::brand('name') }} {{ __('Portal') }}</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-navy-50 font-sans text-navy-900 antialiased">
<div class="flex min-h-screen" x-data="{ sidebar: true, quickAdd: false }">
    {{-- Left navigation --}}
    <aside class="no-print w-64 shrink-0 bg-navy-900 text-navy-100" :class="{ 'hidden lg:block': !sidebar }">
        <div class="flex items-center gap-2 border-b border-navy-700 px-4 py-4">
            <x-brand-mark class="h-8 w-8 text-white" />
            <span class="font-serif font-bold text-white">{{ \App\Services\Settings::brand('case_prefix') }} {{ __('Portal') }}</span>
        </div>
        <nav class="space-y-1 px-2 py-4 text-sm" aria-label="{{ __('Portal navigation') }}">
            @php
                $nav = [
                    ['portal.dashboard', __('Dashboard'), 'leads.view'],
                    ['portal.leads.index', __('Leads'), 'leads.view'],
                    ['portal.cases.index', __('Cases'), 'cases.view'],
                    ['portal.pipeline', __('Pipeline'), 'cases.view'],
                    ['portal.tasks.index', __('Task Center'), 'tasks.view'],
                    ['portal.calendar', __('Calendar'), 'tasks.view'],
                    ['portal.documents.index', __('Document Center'), 'documents.view'],
                    ['portal.communications.index', __('Communication Center'), 'communications.view'],
                    ['portal.automations.index', __('Automation Center'), 'automations.view'],
                    ['portal.reports.index', __('Reporting Center'), 'reports.view'],
                    ['portal.print.index', __('Print & Export Center'), 'reports.export'],
                    ['portal.payments.index', __('Payments'), 'payments.view'],
                    ['portal.admin.settings', __('Administration'), 'admin.settings'],
                    ['portal.audit.index', __('Audit Log'), 'audit.view'],
                ];
            @endphp
            @foreach ($nav as [$route, $label, $permission])
                @can($permission)
                    <a href="{{ route($route) }}"
                       class="block rounded-md px-3 py-2 transition {{ request()->routeIs(str_replace('.index', '.*', $route)) ? 'bg-navy-700 text-white' : 'hover:bg-navy-800 hover:text-white' }}">
                        {{ $label }}
                    </a>
                @endcan
            @endforeach
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Top bar: global search + quick add --}}
        <header class="no-print flex items-center gap-4 border-b border-navy-100 bg-white px-4 py-3">
            <button @click="sidebar = !sidebar" class="rounded p-2 hover:bg-navy-50 lg:hidden" aria-label="{{ __('Toggle sidebar') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <form action="{{ route('portal.search') }}" method="get" class="max-w-lg flex-1" role="search">
                <label for="global-search" class="sr-only">{{ __('Global search') }}</label>
                <input id="global-search" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search cases, names, addresses, parcels, phones…') }}" class="form-input !py-2">
            </form>
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="btn-gold !px-4 !py-2" :aria-expanded="open">+ {{ __('Quick Add') }}</button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-2 w-48 rounded-md border border-navy-100 bg-white py-2 shadow-lg">
                    @can('leads.create')<a href="{{ route('portal.leads.create') }}" class="block px-4 py-2 text-sm hover:bg-navy-50">{{ __('New Lead') }}</a>@endcan
                    @can('cases.create')<a href="{{ route('portal.cases.create') }}" class="block px-4 py-2 text-sm hover:bg-navy-50">{{ __('New Case') }}</a>@endcan
                    @can('tasks.manage')<a href="{{ route('portal.tasks.create') }}" class="block px-4 py-2 text-sm hover:bg-navy-50">{{ __('New Task') }}</a>@endcan
                </div>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="hidden text-navy-500 sm:inline">{{ auth()->user()->name }}</span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="text-navy-500 underline hover:text-navy-800">{{ __('Sign out') }}</button></form>
            </div>
        </header>

        @if (session('status'))
            <div class="mx-6 mt-4 rounded-md border border-gold-200 bg-gold-50 px-4 py-3 text-sm">{{ session('status') }}</div>
        @endif

        <main class="flex-1 px-4 py-6 sm:px-6">
            @yield('content')
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
