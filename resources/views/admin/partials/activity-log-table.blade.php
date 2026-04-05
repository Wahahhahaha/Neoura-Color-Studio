<div class="admin-user-table-wrap user-data-table-shell">
    <table class="admin-user-table user-data-table activity-log-table">
        <thead>
            <tr>
                <th>{{ __('ui.common.name') }}</th>
                <th>{{ __('ui.common.ip_address') }}</th>
                <th>{{ __('ui.admin.activity_log.longitude') }}</th>
                <th>{{ __('ui.admin.activity_log.latitude') }}</th>
                <th>{{ __('ui.common.action') }}</th>
                <th>{{ __('ui.admin.activity_log.date_time') }}</th>
                <th>{{ __('ui.common.detail') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($activityEntries ?? []) as $entry)
                <tr>
                    <td>{{ $entry['name'] ?? '-' }}</td>
                    <td>{{ $entry['ip_address'] ?? '-' }}</td>
                    <td>{{ $entry['longitude'] ?? '-' }}</td>
                    <td>{{ $entry['latitude'] ?? '-' }}</td>
                    <td><span class="activity-log-action">{{ $entry['action'] ?? '-' }}</span></td>
                    <td><span class="activity-log-datetime">{{ $entry['datetime'] ?? '-' }}</span></td>
                    <td><pre class="activity-detail-pre">{{ $entry['detail'] ?? '-' }}</pre></td>
                </tr>
            @empty
                <tr class="activity-log-empty">
                    <td colspan="7">{{ __('ui.admin.activity_log.no_data') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="admin-user-pagination user-data-pagination" data-activitylog-pagination>
    <p class="admin-user-pagination-meta" data-activitylog-pagination-meta>
        {{ __('ui.common.showing_range_of_total', ['from' => $activityPagination['from'] ?? 0, 'to' => $activityPagination['to'] ?? 0, 'total' => $activityPagination['total'] ?? 0]) }}
    </p>
    <div class="admin-user-pagination-actions" data-activitylog-pagination-actions>
        @php
            $currentPage = (int) ($activityPagination['page'] ?? 1);
            $lastPage = (int) ($activityPagination['last_page'] ?? 1);
        @endphp

        <button type="button" class="btn btn-outline" data-activitylog-page="{{ max(1, $currentPage - 1) }}" {{ $currentPage <= 1 ? 'disabled' : '' }}>{{ __('ui.common.prev') }}</button>
        <span class="admin-user-pagination-page">{{ __('ui.common.page_of', ['page' => $currentPage, 'last_page' => $lastPage]) }}</span>
        <button type="button" class="btn btn-outline" data-activitylog-page="{{ min($lastPage, $currentPage + 1) }}" {{ $currentPage >= $lastPage ? 'disabled' : '' }}>{{ __('ui.common.next') }}</button>
    </div>
</div>
