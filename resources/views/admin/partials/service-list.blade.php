@if (empty($serviceRows))
    <p class="setting-alert error">No service data found.</p>
@else
    <div class="admin-service-page-list">
        @foreach ($serviceRows as $service)
            <article class="admin-service-page-card" id="service-{{ $service['serviceid'] }}">
                <div class="admin-service-page-head">
                    <h3>{{ $service['name'] }}</h3>
                    <p>{{ $service['detail'] ?: '-' }}</p>
                    <p class="service-meta">Duration: {{ $service['duration'] ?: '-' }}</p>
                    <p class="service-price">Price: {{ $service['price'] ?: '-' }}</p>
                </div>

                <div>
                    <p class="setting-label">Description</p>
                    @if (!empty($service['descriptions']))
                        <ul class="detail-list">
                            @foreach ($service['descriptions'] as $description)
                                <li>{{ $description }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="service-meta">No description yet.</p>
                    @endif
                </div>

                <div class="admin-service-card-actions">
                    <button
                        type="button"
                        class="btn btn-outline"
                        data-open-service-modal
                        data-service-id="{{ $service['serviceid'] }}"
                        data-service-name="{{ $service['name'] }}"
                        data-service-detail="{{ $service['detail'] }}"
                        data-service-duration="{{ $service['duration'] }}"
                        data-service-price="{{ $service['price'] }}"
                        data-service-descriptions='@json($service["descriptions"])'
                    >
                        Edit
                    </button>
                </div>
            </article>
        @endforeach
    </div>
@endif

<div class="admin-user-pagination" data-service-pagination>
    <p class="admin-user-pagination-meta">
        Showing {{ $servicePagination['from'] ?? 0 }}-{{ $servicePagination['to'] ?? 0 }} of {{ $servicePagination['total'] ?? 0 }}
    </p>
    <div class="admin-user-pagination-actions">
        @php
            $currentPage = (int) ($servicePagination['page'] ?? 1);
            $lastPage = (int) ($servicePagination['last_page'] ?? 1);
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
        @endphp

        <button type="button" class="btn btn-outline" data-service-page="{{ max(1, $currentPage - 1) }}" {{ $currentPage <= 1 ? 'disabled' : '' }}>Prev</button>
        @for ($cursor = $start; $cursor <= $end; $cursor++)
            <button type="button" class="btn btn-outline {{ $cursor === $currentPage ? 'is-active' : '' }}" data-service-page="{{ $cursor }}" {{ $cursor === $currentPage ? 'disabled' : '' }}>{{ $cursor }}</button>
        @endfor
        <button type="button" class="btn btn-outline" data-service-page="{{ min($lastPage, $currentPage + 1) }}" {{ $currentPage >= $lastPage ? 'disabled' : '' }}>Next</button>
        <span class="admin-user-pagination-page">Page {{ $currentPage }} / {{ $lastPage }}</span>
    </div>
</div>
