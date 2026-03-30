<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head">
                <p class="eyebrow">Admin Panel</p>
                <h1>Activity Log</h1>
                <p>All users actions are recorded automatically.</p>
            </div>

            <p class="setting-alert" data-activitylog-feedback hidden></p>

            <div data-activitylog-table-wrap data-fetch-url="{{ route('admin.activitylog') }}">
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
