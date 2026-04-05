<section class="section backup-page">
    <div class="container">
        <div class="setting-wrap backup-wrap">
            <div class="section-head">
                <h1>{{ __('ui.superadmin.backup.title') }}</h1>
                <p>{{ __('ui.superadmin.backup.description') }}</p>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <div class="admin-service-page-list backup-card-list">
                <div class="admin-service-page-card backup-card">
                    <h3>{{ __('ui.superadmin.backup.export_title') }}</h3>
                    <p>{{ __('ui.superadmin.backup.export_description') }}</p>
                    <form method="post" action="{{ route('superadmin.backup.export_sql') }}" class="setting-form">
                        @csrf
                        <div class="setting-actions">
                            <button type="submit" class="btn">{{ __('ui.superadmin.backup.export_button') }}</button>
                        </div>
                    </form>
                </div>

                <div class="admin-service-page-card backup-card">
                    <h3>{{ __('ui.superadmin.backup.restore_title') }}</h3>
                    <p>{{ __('ui.superadmin.backup.restore_description') }}</p>
                    <form method="post" action="{{ route('superadmin.backup.import_sql') }}" enctype="multipart/form-data" class="setting-form">
                        @csrf
                        <label for="backup_sql">{{ __('ui.superadmin.backup.sql_file') }}</label>
                        <input type="file" id="backup_sql" name="backup_sql" accept=".sql,.txt" required>

                        <div class="setting-actions">
                            <button type="submit" class="btn">{{ __('ui.superadmin.backup.upload_restore') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
</main>
</div>
