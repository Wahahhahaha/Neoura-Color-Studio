@if (empty($serviceRows))
    <div class="admin-service-empty">
        <h3>No Services Yet</h3>
        <p>Create your first service to start managing packages.</p>
    </div>
@else
    <div class="admin-service-page-list">
        @foreach ($serviceRows as $service)
            <article class="admin-service-page-card service-card" id="service-{{ $service['serviceid'] }}">
                <div class="admin-service-page-head">
                    <div class="admin-service-title-row">
                        <h3>{{ $service['name'] }}</h3>
                    </div>
                    <p class="admin-service-detail">{{ $service['detail'] ?: '-' }}</p>
                </div>

                <div class="admin-service-meta-grid">
                    <div class="admin-service-meta-card">
                        <span>Duration</span>
                        <strong>{{ $service['duration'] ?: '-' }}</strong>
                    </div>
                    <div class="admin-service-meta-card">
                        <span>Price</span>
                        <strong>{{ $service['price'] ?: '-' }}</strong>
                    </div>
                </div>

                <div class="admin-service-desc-wrap">
                    <p class="setting-label admin-service-desc-title">Descriptions</p>
                    @if (!empty($service['descriptions']))
                        <ul class="detail-list admin-service-desc-list">
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
                        class="btn btn-outline service-edit-btn"
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
