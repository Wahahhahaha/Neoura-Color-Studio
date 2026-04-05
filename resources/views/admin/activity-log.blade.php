<section class="section">
    <div class="container">
        <div class="setting-wrap activity-log-shell">
            <div class="section-head">
<!--                 <p class="eyebrow">{{ __('ui.admin.activity_log.eyebrow') }}</p>
 -->                <h1>{{ __('ui.admin.activity_log.title') }}</h1>
                <p>{{ __('ui.admin.activity_log.description') }}</p>
            </div>

            <p class="setting-alert" data-activitylog-feedback hidden></p>

            <div class="admin-user-controls activity-log-controls">
                <div class="activity-log-level-filter admin-user-level-filter">
                    <label for="activitylog_level_filter">{{ __('ui.common.level') }}</label>
                    <select id="activitylog_level_filter" data-activitylog-level-filter>
                        <option value="0" {{ (string) ($activitySelectedLevelFilter ?? '0') === '0' ? 'selected' : '' }}>{{ __('ui.common.all_levels') }}</option>
                        @foreach (($activityLevelFilterOptions ?? []) as $option)
                            @php
                                $optionValue = (string) ($option['value'] ?? '');
                            @endphp
                            <option value="{{ $optionValue }}" {{ (string) ($activitySelectedLevelFilter ?? '0') === $optionValue ? 'selected' : '' }}>
                                {{ $option['label'] ?? ucfirst($optionValue) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div
                data-activitylog-table-wrap
                data-fetch-url="{{ route('admin.activitylog') }}"
                data-load-failed="{{ __('ui.admin.activity_log.load_failed') }}"
            >
                @include('admin.partials.activity-log-table', [
                    'activityEntries' => $activityEntries,
                    'activityPagination' => $activityPagination,
                ])
            </div>
        </div>
    </div>
</section>
</main>
</div>
