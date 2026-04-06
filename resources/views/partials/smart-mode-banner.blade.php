@auth
    @if ($smartMode !== \App\Enums\SmartMode::Standard)
        <div
            role="status"
            class="border-b border-base-300/80 bg-base-100/90 px-3 py-2 text-center text-xs text-base-content/90 sm:px-4 sm:text-sm"
        >
            <span class="font-medium">{{ __('Smart mode: :mode', ['mode' => $smartMode->label()]) }}</span>
            <span class="text-base-content/75 max-sm:block max-sm:mt-1 sm:ms-1"> — {{ $smartMode->description() }}</span>
        </div>
    @endif
@endauth
