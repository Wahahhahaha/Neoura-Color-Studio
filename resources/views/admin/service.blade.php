<section class="section">
    <div class="container">
        @php
            $serviceTotal = (int) ($servicePagination['total'] ?? 0);
        @endphp
        <div class="setting-wrap service-page-shell">
            <div class="section-head admin-service-hero">
                <div class="admin-service-hero-copy">
<!--                     <p class="eyebrow">{{ __('ui.admin.service.eyebrow') }}</p>
 -->                    <h1>{{ __('ui.admin.service.title') }}</h1>
                    <p>{{ __('ui.admin.service.description') }}</p>
                </div>
                <div class="admin-service-hero-actions">
                    <span class="admin-service-stat-pill">
                        {{ $serviceTotal }} {{ $serviceTotal === 1 ? 'Service' : 'Services' }}
                    </span>
                    <a href="{{ route('admin.service.export_excel') }}" class="btn btn-outline">{{ __('ui.common.export_excel') }}</a>
                    <button type="button" class="btn btn-outline" data-open-import-service-modal>{{ __('ui.common.import_excel') }}</button>
                    <button type="button" class="btn" data-open-add-service-modal>{{ __('ui.admin.service.add_service') }}</button>
                </div>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <div
                class="service-content-shell admin-service-list-surface"
                data-service-list-root
                data-fetch-url="{{ route('admin.service') }}"
                data-load-failed="{{ __('ui.admin.service.load_failed') }}"
                data-add-failed="{{ __('ui.admin.service.add_failed') }}"
                data-add-network-error="{{ __('ui.admin.service.add_network_error') }}"
                data-service-added="{{ __('ui.admin.service.service_added') }}"
            >
                @include('admin.partials.service-list', [
                    'serviceRows' => $serviceRows,
                    'servicePagination' => $servicePagination,
                ])
            </div>
        </div>
    </div>
</section>

<div
    class="crop-modal service-fade-modal"
    data-service-modal
    data-update-url-template="{{ route('admin.service.update', ['serviceid' => '__SERVICE_ID__']) }}"
    data-delete-url-template="{{ route('admin.service.delete', ['serviceid' => '__SERVICE_ID__']) }}"
    hidden
>
    <div class="crop-modal-backdrop" data-close-service-modal></div>
    <div class="crop-modal-dialog service-modal-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.admin.service.edit_service_aria') }}">
        <div class="crop-modal-head">
            <h2>{{ __('ui.admin.service.edit_service') }}</h2>
            <button type="button" class="crop-close" data-close-service-modal aria-label="{{ __('ui.admin.service.close_service_modal') }}">x</button>
        </div>

        <form method="post" class="service-modal-form" id="serviceSaveForm" data-service-save-form>
            @csrf

            <label for="service_name">{{ __('ui.admin.service.service_name') }}</label>
            <input type="text" id="service_name" name="name" required>

            <label for="service_detail">{{ __('ui.admin.service.service_detail') }}</label>
            <input type="text" id="service_detail" name="detail" required>

            <label for="service_duration">{{ __('ui.common.duration') }}</label>
            <input type="text" id="service_duration" name="duration" required>

            <label for="service_price">{{ __('ui.common.price') }}</label>
            <input type="text" id="service_price" name="price" required>

            <label for="service_descriptions">{{ __('ui.admin.service.descriptions_one_line') }}</label>
            <textarea id="service_descriptions" name="descriptions_text" rows="6"></textarea>
        </form>

        <div class="service-modal-actions">
            <form method="post" id="serviceDeleteForm" data-service-delete-form>
                @csrf
                <button type="submit" class="btn btn-outline service-delete-btn">{{ __('ui.common.delete') }}</button>
            </form>

            <div class="service-modal-actions-right">
                <button type="button" class="btn btn-outline" data-close-service-modal>{{ __('ui.common.cancel') }}</button>
                <button type="submit" class="btn" form="serviceSaveForm">{{ __('ui.common.save') }}</button>
            </div>
        </div>
    </div>
</div>

<div
    class="crop-modal service-fade-modal"
    data-add-service-modal
    data-store-url="{{ route('admin.service.store') }}"
    hidden
>
    <div class="crop-modal-backdrop" data-close-add-service-modal></div>
    <div class="crop-modal-dialog service-modal-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.admin.service.add_service_aria') }}">
        <div class="crop-modal-head">
            <h2>{{ __('ui.admin.service.add_service') }}</h2>
            <button type="button" class="crop-close" data-close-add-service-modal aria-label="{{ __('ui.admin.service.close_add_service_modal') }}">x</button>
        </div>

        <form method="post" class="service-modal-form" data-add-service-form>
            @csrf
            <p class="setting-alert" data-add-service-feedback hidden></p>

            <label for="add_service_name">{{ __('ui.admin.service.service_name') }}</label>
            <input type="text" id="add_service_name" name="name" required>

            <label for="add_service_detail">{{ __('ui.admin.service.service_detail') }}</label>
            <input type="text" id="add_service_detail" name="detail" required>

            <label for="add_service_duration">{{ __('ui.common.duration') }}</label>
            <input type="text" id="add_service_duration" name="duration" required>

            <label for="add_service_price">{{ __('ui.common.price') }}</label>
            <input type="text" id="add_service_price" name="price" required>

            <label for="add_service_descriptions">{{ __('ui.admin.service.descriptions_one_line') }}</label>
            <textarea id="add_service_descriptions" name="descriptions_text" rows="6"></textarea>

            <div class="service-modal-actions service-modal-actions-right">
                <button type="button" class="btn btn-outline" data-close-add-service-modal>{{ __('ui.common.cancel') }}</button>
                <button type="submit" class="btn" data-add-service-save>{{ __('ui.common.add') }}</button>
            </div>
        </form>
    </div>
</div>

<div
    class="crop-modal service-fade-modal import-modal-center"
    data-import-service-modal
    hidden
>
    <div class="crop-modal-backdrop" data-close-import-service-modal></div>
    <div class="crop-modal-dialog service-modal-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.admin.service.import_service_aria') }}">
        <div class="crop-modal-head">
            <h2>{{ __('ui.admin.service.import_service') }}</h2>
            <button type="button" class="crop-close" data-close-import-service-modal aria-label="{{ __('ui.admin.service.close_import_service_modal') }}">x</button>
        </div>

        <form method="post" action="{{ route('admin.service.import_excel') }}" enctype="multipart/form-data" class="service-modal-form" data-import-service-form>
            @csrf
            <label for="import_service_excel">{{ __('ui.common.excel_file') }}</label>
            <input type="file" id="import_service_excel" name="service_excel" accept=".xlsx" required>

            <div class="service-modal-actions service-modal-actions-right">
                <button type="button" class="btn btn-outline" data-close-import-service-modal>{{ __('ui.common.cancel') }}</button>
                <button type="submit" class="btn">{{ __('ui.common.import') }}</button>
            </div>
        </form>
    </div>
</div>
</main>
</div>
