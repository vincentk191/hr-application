@php
    use App\Filament\Resources\Companies\CompanyResource;
    use App\Filament\Resources\Roles\RoleResource;
    use App\Filament\Resources\Users\UserResource;
    use Filament\Support\Enums\IconSize;
    use Filament\Support\Icons\Heroicon;

    /*
     * The phone's navigation, the same shape as the dashboard's: a bar along the bottom with the places used most, a
     * raised button back to the dashboard in the middle, and "Lainnya" opening a sheet with everything else. It is built
     * from the panel's own navigation, so a page this person may not open is not offered. From tablet width up the
     * side menu is used and this is hidden (admin-mobile styles).
     */
    $dashboardUrl = route('dashboard');
    $items = collect(filament()->getNavigation())
        ->flatMap(fn ($group) => collect($group->getItems())->map(fn ($item) => ['item' => $item, 'group' => $group->getLabel()]))
        ->reject(fn (array $entry): bool => $entry['item']->getUrl() === $dashboardUrl)
        ->values();

    $sideUrls = [UserResource::getUrl(), CompanyResource::getUrl(), RoleResource::getUrl()];
    $sideTabs = collect($sideUrls)
        ->map(fn (string $url) => $items->first(fn (array $entry): bool => $entry['item']->getUrl() === $url))
        ->filter();
    $more = $items->reject(fn (array $entry): bool => $sideTabs->contains(fn (array $tab): bool => $tab['item'] === $entry['item']));

    // The middle stays in the middle of five columns whatever this person may open: a missing tab leaves a gap.
    $leftCells = $sideTabs->take(2)->values()->pad(2, null)->all();
    $rightCells = $sideTabs->slice(2)->values()->pad(1, null)->all();

    $moreActive = $more->contains(fn (array $entry): bool => $entry['item']->isActive());
@endphp

<nav
    aria-label="Menu utama"
    class="fi-mobile-bar"
    data-test="admin-mobile-tab-bar"
    x-data="{ more: false }"
    x-on:keydown.escape.window="more = false"
>
    <ul class="fi-mobile-bar-list">
        @foreach ([$leftCells, $rightCells] as $position => $cells)
            @if ($position === 1)
                <li>
                    <a href="{{ $dashboardUrl }}" class="fi-mobile-tab fi-mobile-tab-home" data-test="admin-tab-dashboard">
                        <span class="fi-mobile-tab-home-button">
                            {{ \Filament\Support\generate_icon_html(Heroicon::OutlinedHome, size: IconSize::Large) }}
                        </span>
                        <span class="fi-mobile-tab-home-label">Beranda</span>
                    </a>
                </li>
            @endif

            @foreach ($cells as $entry)
                @if ($entry === null)
                    <li aria-hidden="true"></li>
                @else
                    @php
                        $item = $entry['item'];
                        $active = $item->isActive();
                        $icon = ($active ? $item->getActiveIcon() : null) ?? $item->getIcon();
                    @endphp

                    <li>
                        <a
                            href="{{ $item->getUrl() }}"
                            @class(['fi-mobile-tab', 'fi-active' => $active])
                            @if ($active) aria-current="page" @endif
                        >
                            <span class="fi-mobile-tab-pill">
                                @if (filled($icon))
                                    {{ \Filament\Support\generate_icon_html($icon, size: IconSize::Large) }}
                                @endif

                                @if (filled($item->getBadge()))
                                    <span class="fi-mobile-tab-badge">{{ $item->getBadge() }}</span>
                                @endif
                            </span>
                            {{ $item->getLabel() }}
                        </a>
                    </li>
                @endif
            @endforeach
        @endforeach

        <li>
            <button
                type="button"
                @class(['fi-mobile-tab', 'fi-active' => $moreActive])
                x-on:click="more = true"
                aria-haspopup="dialog"
                data-test="admin-open-more"
            >
                <span class="fi-mobile-tab-pill">
                    {{ \Filament\Support\generate_icon_html(Heroicon::OutlinedBars3, size: IconSize::Large) }}
                </span>
                Lainnya
            </button>
        </li>
    </ul>

    <div
        x-cloak
        x-show="more"
        class="fi-mobile-sheet-ctn"
        role="dialog"
        aria-modal="true"
        aria-labelledby="fi-mobile-sheet-title"
    >
        <div
            x-show="more"
            x-transition.opacity.200ms
            x-on:click="more = false"
            class="fi-mobile-sheet-overlay"
        ></div>

        <div
            x-show="more"
            x-transition:enter="fi-mobile-sheet-enter"
            x-transition:enter-start="fi-mobile-sheet-enter-start"
            x-transition:leave="fi-mobile-sheet-enter"
            x-transition:leave-end="fi-mobile-sheet-enter-start"
            class="fi-mobile-sheet"
        >
            <div class="fi-mobile-sheet-header">
                <h2 id="fi-mobile-sheet-title">Lainnya</h2>
                <button type="button" x-on:click="more = false" class="fi-mobile-sheet-close" aria-label="Tutup">
                    {{ \Filament\Support\generate_icon_html(Heroicon::OutlinedXMark, size: IconSize::Large) }}
                </button>
            </div>

            <div class="fi-mobile-sheet-body">
                @foreach ($more->groupBy(fn (array $entry): string => (string) $entry['group']) as $groupLabel => $entries)
                    @if (filled($groupLabel))
                        <p class="fi-mobile-sheet-group">{{ $groupLabel }}</p>
                    @endif

                    <ul>
                        @foreach ($entries as $entry)
                            @php
                                $item = $entry['item'];
                                $active = $item->isActive();
                                $icon = ($active ? $item->getActiveIcon() : null) ?? $item->getIcon();
                            @endphp

                            <li>
                                <a
                                    href="{{ $item->getUrl() }}"
                                    @class(['fi-mobile-sheet-link', 'fi-active' => $active])
                                    @if ($active) aria-current="page" @endif
                                    @if ($item->shouldOpenUrlInNewTab()) target="_blank" rel="noopener" @endif
                                >
                                    @if (filled($icon))
                                        {{ \Filament\Support\generate_icon_html($icon, size: IconSize::Large) }}
                                    @endif
                                    <span>{{ $item->getLabel() }}</span>
                                    @if (filled($item->getBadge()))
                                        <span class="fi-mobile-sheet-badge">{{ $item->getBadge() }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>
    </div>
</nav>
