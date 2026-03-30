<div class="admin-user-table-wrap">
    <table class="admin-user-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>IP Address</th>
                <th>Longitude</th>
                <th>Latitude</th>
                <th>Action</th>
                <th>Date Time</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($activityEntries ?? []) as $entry)
                <tr>
                    <td>{{ $entry['name'] ?? '-' }}</td>
                    <td>{{ $entry['ip_address'] ?? '-' }}</td>
                    <td>{{ $entry['longitude'] ?? '-' }}</td>
                    <td>{{ $entry['latitude'] ?? '-' }}</td>
                    <td>{{ $entry['action'] ?? '-' }}</td>
                    <td>{{ $entry['datetime'] ?? '-' }}</td>
                    <td><pre class="activity-detail-pre">{{ $entry['detail'] ?? '-' }}</pre></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No activity log data available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="admin-user-pagination" data-activitylog-pagination>
    <p class="admin-user-pagination-meta" data-activitylog-pagination-meta>
        Showing {{ $activityPagination['from'] ?? 0 }}-{{ $activityPagination['to'] ?? 0 }} of {{ $activityPagination['total'] ?? 0 }}
    </p>
    <div class="admin-user-pagination-actions" data-activitylog-pagination-actions>
        @php
            $currentPage = (int) ($activityPagination['page'] ?? 1);
            $lastPage = (int) ($activityPagination['last_page'] ?? 1);
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
        @endphp

        <button type="button" class="btn btn-outline" data-activitylog-page="{{ max(1, $currentPage - 1) }}" {{ $currentPage <= 1 ? 'disabled' : '' }}>Prev</button>
        @for ($cursor = $start; $cursor <= $end; $cursor++)
            <button type="button" class="btn btn-outline {{ $cursor === $currentPage ? 'is-active' : '' }}" data-activitylog-page="{{ $cursor }}" {{ $cursor === $currentPage ? 'disabled' : '' }}>{{ $cursor }}</button>
        @endfor
        <button type="button" class="btn btn-outline" data-activitylog-page="{{ min($lastPage, $currentPage + 1) }}" {{ $currentPage >= $lastPage ? 'disabled' : '' }}>Next</button>
        <span class="admin-user-pagination-page">Page {{ $currentPage }} / {{ $lastPage }}</span>
    </div>
</div>
