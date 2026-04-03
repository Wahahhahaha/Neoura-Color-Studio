@if (empty($serviceRows))
    <div class="admin-service-empty">
        <h3>{{ __('ui.admin.service.no_services_yet') }}</h3>
        <p>{{ __('ui.admin.service.create_first_service') }}</p>
    </div>
@else
    <div class="admin-service-stack-list">
        @foreach ($serviceRows as $service)
            <article class="admin-service-row" id="service-{{ $service['serviceid'] }}">
                <div class="admin-service-row-main">
                    <div class="admin-service-row-head">
                        <h3>{{ $service['name'] }}</h3>
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
                            {{ __('ui.common.edit') }}
                        </button>
                    </div>
                    <p class="admin-service-detail">{{ $service['detail'] ?: '-' }}</p>

                    <div class="admin-service-inline-meta">
                        <div class="admin-service-inline-meta-item">
                            <span>{{ __('ui.common.duration') }}</span>
                            <strong>{{ $service['duration'] ?: '-' }}</strong>
                        </div>
                        <div class="admin-service-inline-meta-item">
                            <span>{{ __('ui.common.price') }}</span>
                            <strong>{{ $service['price_display'] ?? ($service['price'] ?: '-') }}</strong>
                        </div>
                    </div>
                </div>

                @if (!empty($service['descriptions']))
                    <ul class="admin-service-desc-chips">
                        @foreach ($service['descriptions'] as $description)
                            <li>{{ $description }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="service-meta">{{ __('ui.admin.service.no_description_yet') }}</p>
                @endif
            </article>
        @endforeach
    </div>
@endif

<div class="admin-user-pagination" data-service-pagination>
    <p class="admin-user-pagination-meta">
        {{ __('ui.common.showing_range_of_total', ['from' => $servicePagination['from'] ?? 0, 'to' => $servicePagination['to'] ?? 0, 'total' => $servicePagination['total'] ?? 0]) }}
    </p>
    <div class="admin-user-pagination-actions">
        @php
            $currentPage = (int) ($servicePagination['page'] ?? 1);
            $lastPage = (int) ($servicePagination['last_page'] ?? 1);
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
        @endphp

        <button type="button" class="btn btn-outline" data-service-page="{{ max(1, $currentPage - 1) }}" {{ $currentPage <= 1 ? 'disabled' : '' }}>{{ __('ui.common.prev') }}</button>
        @for ($cursor = $start; $cursor <= $end; $cursor++)
            <button type="button" class="btn btn-outline {{ $cursor === $currentPage ? 'is-active' : '' }}" data-service-page="{{ $cursor }}" {{ $cursor === $currentPage ? 'disabled' : '' }}>{{ $cursor }}</button>
        @endfor
        <button type="button" class="btn btn-outline" data-service-page="{{ min($lastPage, $currentPage + 1) }}" {{ $currentPage >= $lastPage ? 'disabled' : '' }}>{{ __('ui.common.next') }}</button>
        <span class="admin-user-pagination-page">{{ __('ui.common.page_of', ['page' => $currentPage, 'last_page' => $lastPage]) }}</span>
    </div>
</div>
