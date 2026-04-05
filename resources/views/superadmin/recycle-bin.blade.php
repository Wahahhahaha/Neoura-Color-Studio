<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head">
<!--                 <p class="eyebrow">{{ __('ui.superadmin.recycle_bin.eyebrow') }}</p>
 -->                <h1>{{ __('ui.superadmin.recycle_bin.title') }}</h1>
                <p>{{ __('ui.superadmin.recycle_bin.description') }}</p>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <p class="setting-alert" data-recycle-feedback hidden></p>

            <div
                class="recycle-filter-row"
                data-recycle-filter-wrap
                data-fetch-url="{{ route('superadmin.recyclebin') }}"
                data-load-failed="{{ __('ui.superadmin.recycle_bin.load_failed') }}"
            >
                <div class="recycle-filter-item">
                    <label for="recycle_level_filter">{{ __('ui.superadmin.recycle_bin.filter_by_level') }}</label>
                    <select id="recycle_level_filter" data-recycle-level-filter>
                        <option value="all" {{ ($selectedRecycleLevel ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('ui.common.all_levels') }}</option>
                        @foreach (($recycleLevelOptions ?? []) as $level)
                            <option value="{{ $level }}" {{ ($selectedRecycleLevel ?? 'all') === $level ? 'selected' : '' }}>{{ strtoupper($level) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="recycle-filter-item">
                    <label for="recycle_action_filter">{{ __('ui.superadmin.recycle_bin.filter_by_action') }}</label>
                    <select id="recycle_action_filter" data-recycle-action-filter>
                        <option value="all" {{ ($selectedRecycleAction ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('ui.common.all_actions') }}</option>
                        <option value="update" {{ ($selectedRecycleAction ?? 'all') === 'update' ? 'selected' : '' }}>{{ __('ui.common.edit_upper') }}</option>
                        <option value="delete" {{ ($selectedRecycleAction ?? 'all') === 'delete' ? 'selected' : '' }}>{{ __('ui.common.delete_upper') }}</option>
                    </select>
                </div>
            </div>

            <div data-recycle-table-container>
                @include('superadmin.partials.recycle-bin-table', [
                    'recycleEntries' => $recycleEntries,
                    'recyclePagination' => $recyclePagination,
                ])
            </div>
        </div>
    </div>
</section>
</main>
</div>
