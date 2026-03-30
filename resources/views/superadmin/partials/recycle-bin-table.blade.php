@if (empty($recycleEntries))
    <p class="setting-alert error">No recycle records found for selected filters.</p>
@else
    @php
        $startNo = (int) ($recyclePagination['from'] ?? 0);
        if ($startNo < 1) {
            $startNo = 1;
        }
    @endphp
    <div class="admin-user-table-wrap">
        <table class="admin-user-table recycle-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Archived At</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>IP Address</th>
                    <th>Service</th>
                    <th>Detail</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recycleEntries as $index => $entry)
                    @php
                        $service = is_array($entry['service'] ?? null) ? $entry['service'] : [];
                        $actor = is_array($entry['actor'] ?? null) ? $entry['actor'] : [];
                        $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
                        $action = strtolower((string) ($entry['action'] ?? 'unknown'));
                        $actionLabel = $action === 'delete' ? 'DELETE' : ($action === 'update' ? 'EDIT' : strtoupper($action));
                        $username = trim((string) ($actor['username'] ?? ''));
                        $level = trim((string) ($actor['levelname'] ?? ''));
                        $detailText = trim((string) ($entry['detail_text'] ?? ''));
                    @endphp

                    <tr>
                        <td>{{ $startNo + $index }}</td>
                        <td>{{ (string) ($entry['archived_at'] ?? '-') }}</td>
                        <td>
                            <div>{{ $username !== '' ? $username : '-' }}</div>
                            <small>{{ $level !== '' ? $level : '-' }}</small>
                        </td>
                        <td>{{ $actionLabel }}</td>
                        <td>{{ (string) ($entry['ip_address'] ?? '-') }}</td>
                        <td>
                            <div><strong>{{ (string) ($service['name'] ?? '-') }}</strong></div>
                            <small>ID: {{ (int) ($service['serviceid'] ?? 0) }}</small>
                        </td>
                        <td>
                            <div>{{ $detailText !== '' ? $detailText : '-' }}</div>
                            @if (!empty($changes))
                                <details class="recycle-detail-box">
                                    <summary>View change details ({{ count($changes) }})</summary>
                                    <div class="recycle-detail-list">
                                        @foreach ($changes as $change)
                                            @php
                                                $field = trim((string) ($change['field'] ?? '-'));
                                                $from = (string) ($change['from'] ?? '');
                                                $to = (string) ($change['to'] ?? '');
                                                $type = strtolower((string) ($change['type'] ?? 'updated'));
                                            @endphp
                                            <div class="recycle-detail-item">
                                                <p><strong>{{ $field }}</strong> ({{ strtoupper($type) }})</p>
                                                <p><span>From:</span> {{ $from !== '' ? $from : '-' }}</p>
                                                <p><span>To:</span> {{ $to !== '' ? $to : '-' }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </td>
                        <td>
                            <div class="recycle-actions-cell">
                                <form method="post" action="{{ route('superadmin.recyclebin.restore', ['recycleId' => (string) ($entry['recycle_id'] ?? '')]) }}">
                                    @csrf
                                    <button type="submit" class="btn">Restore</button>
                                </form>
                                <form method="post" action="{{ route('superadmin.recyclebin.delete_permanent', ['recycleId' => (string) ($entry['recycle_id'] ?? '')]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline recycle-delete-permanent-btn">Delete Permanent</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="admin-user-pagination" data-recycle-pagination>
    <p class="admin-user-pagination-meta">
        Showing {{ $recyclePagination['from'] ?? 0 }}-{{ $recyclePagination['to'] ?? 0 }} of {{ $recyclePagination['total'] ?? 0 }}
    </p>
    <div class="admin-user-pagination-actions">
        @php
            $currentPage = (int) ($recyclePagination['page'] ?? 1);
            $lastPage = (int) ($recyclePagination['last_page'] ?? 1);
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
        @endphp
        <button type="button" class="btn btn-outline" data-recycle-page="{{ max(1, $currentPage - 1) }}" {{ $currentPage <= 1 ? 'disabled' : '' }}>Prev</button>
        @for ($cursor = $start; $cursor <= $end; $cursor++)
            <button type="button" class="btn btn-outline {{ $cursor === $currentPage ? 'is-active' : '' }}" data-recycle-page="{{ $cursor }}" {{ $cursor === $currentPage ? 'disabled' : '' }}>{{ $cursor }}</button>
        @endfor
        <button type="button" class="btn btn-outline" data-recycle-page="{{ min($lastPage, $currentPage + 1) }}" {{ $currentPage >= $lastPage ? 'disabled' : '' }}>Next</button>
        <span class="admin-user-pagination-page">Page {{ $currentPage }} / {{ $lastPage }}</span>
    </div>
</div>
