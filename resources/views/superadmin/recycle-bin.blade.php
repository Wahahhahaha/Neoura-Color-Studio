<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head">
                <p class="eyebrow">Superadmin</p>
                <h1>Recycle Bin</h1>
                <p>Service audit log for edit/delete actions, including actor, action type, IP address, and field-level changes.</p>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <p class="setting-alert" data-recycle-feedback hidden></p>

            <div class="recycle-filter-row" data-recycle-filter-wrap data-fetch-url="{{ route('superadmin.recyclebin') }}">
                <div class="recycle-filter-item">
                    <label for="recycle_level_filter">Filter by Level</label>
                    <select id="recycle_level_filter" data-recycle-level-filter>
                        <option value="all" {{ ($selectedRecycleLevel ?? 'all') === 'all' ? 'selected' : '' }}>All Levels</option>
                        @foreach (($recycleLevelOptions ?? []) as $level)
                            <option value="{{ $level }}" {{ ($selectedRecycleLevel ?? 'all') === $level ? 'selected' : '' }}>{{ strtoupper($level) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="recycle-filter-item">
                    <label for="recycle_action_filter">Filter by Action</label>
                    <select id="recycle_action_filter" data-recycle-action-filter>
                        <option value="all" {{ ($selectedRecycleAction ?? 'all') === 'all' ? 'selected' : '' }}>All Actions</option>
                        <option value="update" {{ ($selectedRecycleAction ?? 'all') === 'update' ? 'selected' : '' }}>EDIT</option>
                        <option value="delete" {{ ($selectedRecycleAction ?? 'all') === 'delete' ? 'selected' : '' }}>DELETE</option>
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
