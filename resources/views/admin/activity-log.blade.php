<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head">
                <p class="eyebrow">{{ __('ui.admin.activity_log.eyebrow') }}</p>
                <h1>{{ __('ui.admin.activity_log.title') }}</h1>
                <p>{{ __('ui.admin.activity_log.description') }}</p>
            </div>

            <p class="setting-alert" data-activitylog-feedback hidden></p>

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
