<section class="section">
    <div class="container">
        <div class="setting-wrap permission-shell">
            <div class="section-head permission-head">
<!--                 <p class="eyebrow">{{ __('ui.superadmin.permission.eyebrow') }}</p>
 -->                <h1>{{ __('ui.superadmin.permission.title') }}</h1>
                <p>{{ __('ui.superadmin.permission.description') }}</p>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <form method="post" action="{{ route('superadmin.permission.update') }}" class="setting-form">
                @csrf

                <div class="admin-user-table-wrap">
                    <table class="admin-user-table permission-table">
                        <thead>
                            <tr>
                                <th>{{ __('ui.superadmin.permission.sidebar_menu') }}</th>
                                <th>{{ __('ui.common.description') }}</th>
                                <th>{{ __('ui.common.admin') }}</th>
                                <th>{{ __('ui.common.manager') }}</th>
                                <th>{{ __('ui.common.superadmin') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($permissionRows ?? []) as $row)
                                @php
                                    $key = (string) ($row['key'] ?? '');
                                    $adminChecked = (bool) old("permissions.admin.{$key}", (bool) ($sidebarPermissions['admin'][$key] ?? false));
                                    $managerChecked = (bool) old("permissions.manager.{$key}", (bool) ($sidebarPermissions['manager'][$key] ?? false));
                                    $superadminChecked = (bool) old("permissions.superadmin.{$key}", (bool) ($sidebarPermissions['superadmin'][$key] ?? false));
                                @endphp
                                <tr>
                                    <td><strong>{{ $row['label'] ?? '-' }}</strong></td>
                                    <td>{{ $row['description'] ?? '-' }}</td>
                                    <td class="permission-table-cell">
                                        <label class="permission-switch" for="perm_admin_{{ $key }}">
                                            <input type="hidden" name="permissions[admin][{{ $key }}]" value="0">
                                            <input type="checkbox" id="perm_admin_{{ $key }}" name="permissions[admin][{{ $key }}]" value="1" {{ $adminChecked ? 'checked' : '' }}>
                                            
                                        </label>
                                    </td>
                                    <td class="permission-table-cell">
                                        <label class="permission-switch" for="perm_manager_{{ $key }}">
                                            <input type="hidden" name="permissions[manager][{{ $key }}]" value="0">
                                            <input
                                                type="checkbox"
                                                id="perm_manager_{{ $key }}"
                                                name="permissions[manager][{{ $key }}]"
                                                value="1"
                                                {{ $managerChecked ? 'checked' : '' }}
                                            >
                                            
                                        </label>
                                    </td>
                                    <td class="permission-table-cell">
                                        <label class="permission-switch" for="perm_superadmin_{{ $key }}">
                                            <input type="hidden" name="permissions[superadmin][{{ $key }}]" value="0">
                                            <input
                                                type="checkbox"
                                                id="perm_superadmin_{{ $key }}"
                                                name="permissions[superadmin][{{ $key }}]"
                                                value="1"
                                                {{ $superadminChecked ? 'checked' : '' }}
                                            >
                                            
                                        </label>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="setting-actions">
                    <button type="submit" class="btn">{{ __('ui.superadmin.permission.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</section>
</main>
</div>
