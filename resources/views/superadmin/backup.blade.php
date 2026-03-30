<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head">
                <p class="eyebrow">Superadmin</p>
                <h1>Backup Database</h1>
                <p>Export current database to SQL file or restore database from SQL backup file.</p>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <div class="admin-service-page-list">
                <div class="admin-service-page-card">
                    <h3>Export Database (.sql)</h3>
                    <p>Download full SQL backup of the current database.</p>
                    <form method="post" action="{{ route('superadmin.backup.export_sql') }}" class="setting-form">
                        @csrf
                        <div class="setting-actions">
                            <button type="submit" class="btn">Export SQL Backup</button>
                        </div>
                    </form>
                </div>

                <div class="admin-service-page-card">
                    <h3>Restore Database (.sql)</h3>
                    <p>Upload SQL file to restore database structure and data.</p>
                    <form method="post" action="{{ route('superadmin.backup.import_sql') }}" enctype="multipart/form-data" class="setting-form">
                        @csrf
                        <label for="backup_sql">SQL File</label>
                        <input type="file" id="backup_sql" name="backup_sql" accept=".sql,.txt" required>

                        <div class="setting-actions">
                            <button type="submit" class="btn">Upload & Restore</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
</main>
</div>
