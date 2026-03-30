<section class="section">
    <div class="container">
        <div class="setting-wrap service-page-shell">
            <div class="section-head admin-service-hero">
                <div>
                    <p class="eyebrow">Service Management</p>
                    <h1>Service List</h1>
                    <p>Manage studio packages with a polished, enterprise-grade workspace.</p>
                </div>
                <div class="admin-service-hero-actions">
                    <a href="{{ route('admin.service.export_excel') }}" class="btn btn-outline">Export Excel (.xlsx)</a>
                    <button type="button" class="btn btn-outline" data-open-import-service-modal>Import Excel (.xlsx)</button>

                    <button type="button" class="btn" data-open-add-service-modal>Add Service</button>
                </div>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <div class="service-content-shell" data-service-list-root data-fetch-url="{{ route('admin.service') }}">
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
    <div class="crop-modal-dialog service-modal-dialog" role="dialog" aria-modal="true" aria-label="Edit service">
        <div class="crop-modal-head">
            <h2>Edit Service</h2>
            <button type="button" class="crop-close" data-close-service-modal aria-label="Close service modal">x</button>
        </div>

        <form method="post" class="service-modal-form" id="serviceSaveForm" data-service-save-form>
            @csrf

            <label for="service_name">Service Name</label>
            <input type="text" id="service_name" name="name" required>

            <label for="service_detail">Service Detail</label>
            <input type="text" id="service_detail" name="detail" required>

            <label for="service_duration">Duration</label>
            <input type="text" id="service_duration" name="duration" required>

            <label for="service_price">Price</label>
            <input type="text" id="service_price" name="price" required>

            <label for="service_descriptions">Descriptions (one line per item)</label>
            <textarea id="service_descriptions" name="descriptions_text" rows="6"></textarea>
        </form>

        <div class="service-modal-actions">
            <form method="post" id="serviceDeleteForm" data-service-delete-form>
                @csrf
                <button type="submit" class="btn btn-outline service-delete-btn">Delete</button>
            </form>

            <div class="service-modal-actions-right">
                <button type="button" class="btn btn-outline" data-close-service-modal>Cancel</button>
                <button type="submit" class="btn" form="serviceSaveForm">Save</button>
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
    <div class="crop-modal-dialog service-modal-dialog" role="dialog" aria-modal="true" aria-label="Add service">
        <div class="crop-modal-head">
            <h2>Add Service</h2>
            <button type="button" class="crop-close" data-close-add-service-modal aria-label="Close add service modal">x</button>
        </div>

        <form method="post" class="service-modal-form" data-add-service-form>
            @csrf
            <p class="setting-alert" data-add-service-feedback hidden></p>

            <label for="add_service_name">Service Name</label>
            <input type="text" id="add_service_name" name="name" required>

            <label for="add_service_detail">Service Detail</label>
            <input type="text" id="add_service_detail" name="detail" required>

            <label for="add_service_duration">Duration</label>
            <input type="text" id="add_service_duration" name="duration" required>

            <label for="add_service_price">Price</label>
            <input type="text" id="add_service_price" name="price" required>

            <label for="add_service_descriptions">Descriptions (one line per item)</label>
            <textarea id="add_service_descriptions" name="descriptions_text" rows="6"></textarea>

            <div class="service-modal-actions service-modal-actions-right">
                <button type="button" class="btn btn-outline" data-close-add-service-modal>Cancel</button>
                <button type="submit" class="btn" data-add-service-save>Add</button>
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
    <div class="crop-modal-dialog service-modal-dialog" role="dialog" aria-modal="true" aria-label="Import service Excel">
        <div class="crop-modal-head">
            <h2>Import Service Excel</h2>
            <button type="button" class="crop-close" data-close-import-service-modal aria-label="Close import service modal">x</button>
        </div>

        <form method="post" action="{{ route('admin.service.import_excel') }}" enctype="multipart/form-data" class="service-modal-form" data-import-service-form>
            @csrf
            <label for="import_service_excel">Excel File (.xlsx)</label>
            <input type="file" id="import_service_excel" name="service_excel" accept=".xlsx" required>

            <div class="service-modal-actions service-modal-actions-right">
                <button type="button" class="btn btn-outline" data-close-import-service-modal>Cancel</button>
                <button type="submit" class="btn">Import</button>
            </div>
        </form>
    </div>
</div>
</main>
</div>
