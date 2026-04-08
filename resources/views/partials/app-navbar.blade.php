@php
    $navUser = auth()->user();
    $currentBudget = $navUser ? app(\App\Services\CurrentBudget::class)->current() : null;
    $switcherBudgets = $navUser && $currentBudget
        ? $navUser->budgets()->with('owner')->get()->sortBy(function ($b) use ($navUser) {
            $isOwn = (int) $b->owner_user_id === (int) $navUser->id;

            return [$isOwn ? 0 : 1, $b->displayNameFor($navUser)];
        })->values()
        : collect();
@endphp
<div class="navbar sticky top-0 z-40 min-h-14 flex-wrap gap-y-2 border-b border-base-300/40 bg-base-100/95 px-2 shadow-sm backdrop-blur-sm sm:px-4">
    <div class="navbar-start flex min-w-0 shrink-0 flex-col items-stretch gap-1 sm:flex-row sm:items-center sm:gap-2">
        <div class="flex items-center gap-1 sm:gap-2">
            <label
                for="bb-mobile-nav"
                class="btn btn-ghost btn-square btn-sm shrink-0 lg:hidden"
                aria-label="{{ __('Open menu') }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </label>
            <a class="btn btn-ghost h-auto min-h-11 max-w-full shrink gap-2 px-2 text-base font-semibold tracking-tight sm:text-lg" href="{{ route('dashboard') }}" wire:navigate>
                <img
                    src="{{ asset('images/budget-buddy-logo.png') }}"
                    alt="{{ config('app.name') }}"
                    class="h-9 w-auto max-w-[min(100%,11rem)] object-contain object-left sm:h-10 sm:max-w-[13rem]"
                    width="208"
                    height="80"
                    loading="eager"
                    decoding="async"
                />
            </a>
        </div>
        @auth
            @if ($navUser->budgets()->count() > 1)
                <div class="dropdown dropdown-end sm:dropdown-bottom">
                    <button tabindex="0" type="button" class="btn btn-ghost btn-sm max-w-full truncate font-normal sm:max-w-[14rem]" aria-label="{{ __('Budget') }}">
                        {{ $currentBudget->displayNameFor($navUser) }}
                    </button>
                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-50 mt-2 w-56 border border-base-300/60 p-2 shadow">
                        @foreach ($switcherBudgets as $budget)
                            <li>
                                @if ($budget->id === $currentBudget->id)
                                    <span class="menu-active pointer-events-none">{{ $budget->displayNameFor($navUser) }}</span>
                                @else
                                    <form method="POST" action="{{ route('budget.switch', $budget) }}" class="w-full">
                                        @csrf
                                        <button type="submit" class="w-full text-left">{{ $budget->displayNameFor($navUser) }}</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @elseif ($currentBudget)
                <span class="text-base-content/70 hidden px-2 text-xs sm:inline sm:text-sm">{{ $currentBudget->displayNameFor($navUser) }}</span>
            @endif
        @endauth
    </div>
    <div class="navbar-end shrink-0 gap-1 sm:gap-2">
        <livewire:notification-bell />
        @include('partials.theme-switcher')
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm min-h-11 px-2 sm:px-3">{{ __('Log out') }}</button>
        </form>
    </div>
</div>
