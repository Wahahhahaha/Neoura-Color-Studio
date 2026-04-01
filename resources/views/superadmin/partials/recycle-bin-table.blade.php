@if (empty($recycleEntries))
    <p class="setting-alert error">{{ __('ui.superadmin.recycle_bin.no_records') }}</p>
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
                    <th>{{ __('ui.superadmin.recycle_bin.archived_at') }}</th>
                    <th>{{ __('ui.superadmin.recycle_bin.actor') }}</th>
                    <th>{{ __('ui.common.action') }}</th>
                    <th>{{ __('ui.common.ip_address') }}</th>
                    <th>{{ __('ui.common.service') }}</th>
                    <th>{{ __('ui.common.detail') }}</th>
                    <th>{{ __('ui.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recycleEntries as $index => $entry)
                    @php
                        $service = is_array($entry['service'] ?? null) ? $entry['service'] : [];
                        $actor = is_array($entry['actor'] ?? null) ? $entry['actor'] : [];
                        $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
                        $action = strtolower((string) ($entry['action'] ?? 'unknown'));
                        $actionLabel = $action === 'delete'
                            ? __('ui.common.delete_upper')
                            : ($action === 'update' ? __('ui.common.edit_upper') : strtoupper($action));
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
                            <small>{{ __('ui.common.id') }}: {{ (int) ($service['serviceid'] ?? 0) }}</small>
                        </td>
                        <td>
                            <div>{{ $detailText !== '' ? $detailText : '-' }}</div>
                            @if (!empty($changes))
                                <details class="recycle-detail-box">
                                    <summary>{{ __('ui.superadmin.recycle_bin.view_change_details', ['count' => count($changes)]) }}</summary>
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
                                                <p><span>{{ __('ui.common.from') }}:</span> {{ $from !== '' ? $from : '-' }}</p>
                                                <p><span>{{ __('ui.common.to') }}:</span> {{ $to !== '' ? $to : '-' }}</p>
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
                                    <button type="submit" class="admin-user-action-btn recycle-icon-btn recycle-restore-btn" title="{{ __('ui.common.restore') }}" aria-label="{{ __('ui.common.restore') }}">
                                        <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true">
                                            <path d="M20 11a8 8 0 1 0 2 5.5M20 4v7h-7"/>
                                        </svg>
                                    </button>
                                </form>
                                <form method="post" action="{{ route('superadmin.recyclebin.delete_permanent', ['recycleId' => (string) ($entry['recycle_id'] ?? '')]) }}">
                                    @csrf
                                    <button type="submit" class="admin-user-action-btn recycle-icon-btn recycle-delete-permanent-btn" title="{{ __('ui.superadmin.recycle_bin.delete_permanent') }}" aria-label="{{ __('ui.superadmin.recycle_bin.delete_permanent') }}">
                                        <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true">
                                            <path d="M6 7h12m-9 0V5h6v2m-7 0 1 12h8l1-12"/>
                                        </svg>
                                    </button>
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
        {{ __('ui.common.showing_range_of_total', ['from' => $recyclePagination['from'] ?? 0, 'to' => $recyclePagination['to'] ?? 0, 'total' => $recyclePagination['total'] ?? 0]) }}
    </p>
    <div class="admin-user-pagination-actions">
        @php
            $currentPage = (int) ($recyclePagination['page'] ?? 1);
            $lastPage = (int) ($recyclePagination['last_page'] ?? 1);
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
        @endphp
        <button type="button" class="btn btn-outline" data-recycle-page="{{ max(1, $currentPage - 1) }}" {{ $currentPage <= 1 ? 'disabled' : '' }}>{{ __('ui.common.prev') }}</button>
        @for ($cursor = $start; $cursor <= $end; $cursor++)
            <button type="button" class="btn btn-outline {{ $cursor === $currentPage ? 'is-active' : '' }}" data-recycle-page="{{ $cursor }}" {{ $cursor === $currentPage ? 'disabled' : '' }}>{{ $cursor }}</button>
        @endfor
        <button type="button" class="btn btn-outline" data-recycle-page="{{ min($lastPage, $currentPage + 1) }}" {{ $currentPage >= $lastPage ? 'disabled' : '' }}>{{ __('ui.common.next') }}</button>
        <span class="admin-user-pagination-page">{{ __('ui.common.page_of', ['page' => $currentPage, 'last_page' => $lastPage]) }}</span>
    </div>
</div>
