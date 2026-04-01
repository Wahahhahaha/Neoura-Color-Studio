<section class="section">
    <div class="container">
        <div class="setting-wrap user-data-shell">
            <div class="section-head user-data-head">
                <div class="user-data-head-copy">
                    <p class="eyebrow">{{ __('ui.admin.user_data.eyebrow') }}</p>
                    <h1>{{ __('ui.admin.user_data.title') }}</h1>
                    <p>{{ __('ui.admin.user_data.description') }}</p>
                    <p class="user-data-meta">{{ __('ui.admin.user_data.total_users', ['total' => $userPagination['total'] ?? 0]) }}</p>
                </div>
                <div class="admin-service-card-actions user-data-actions">
                    <a href="{{ route('admin.userdata.export_excel') }}" class="btn btn-outline">{{ __('ui.common.export_excel') }}</a>
                    <button type="button" class="btn btn-outline" data-open-import-userdata-modal>{{ __('ui.common.import_excel') }}</button>
                    <button type="button" class="btn" data-open-add-user-modal>{{ __('ui.admin.user_data.add_user') }}</button>
                </div>
            </div>

            <p class="setting-alert" data-userdata-feedback hidden></p>

            <div class="admin-user-controls user-data-controls">
                <div class="admin-user-search-box">
                    <label for="userdata_search">{{ __('ui.common.search') }}</label>
                    <input
                        type="search"
                        id="userdata_search"
                        placeholder="{{ __('ui.admin.user_data.search_placeholder') }}"
                        value="{{ $userFilters['q'] ?? '' }}"
                        data-userdata-search
                    >
                </div>
                <div class="admin-user-level-filter">
                    <label for="userdata_level_filter">{{ __('ui.common.level') }}</label>
                    <select id="userdata_level_filter" data-userdata-level-filter>
                        <option value="0" {{ (int) ($userFilters['levelid'] ?? 0) === 0 ? 'selected' : '' }}>{{ __('ui.common.all_levels') }}</option>
                        @foreach (($levelOptions ?? []) as $level)
                            <option value="{{ $level['levelid'] }}" {{ (int) ($userFilters['levelid'] ?? 0) === (int) $level['levelid'] ? 'selected' : '' }}>
                                {{ $level['levelname'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div
                class="admin-user-table-wrap user-data-table-shell"
                data-userdata-table-wrap
                data-fetch-url="{{ route('admin.userdata') }}"
                data-reset-url-template="{{ route('admin.userdata.reset_password', ['userid' => '__USER_ID__']) }}"
                data-delete-url-template="{{ route('admin.userdata.delete', ['userid' => '__USER_ID__']) }}"
                data-text-action-failed="{{ __('ui.common.action_failed') }}"
                data-text-load-failed="{{ __('ui.admin.user_data.load_failed') }}"
                data-text-no-data="{{ __('ui.admin.user_data.no_data') }}"
                data-text-showing="{{ __('ui.common.showing') }}"
                data-text-of="{{ __('ui.common.of') }}"
                data-text-page="{{ __('ui.common.page') }}"
                data-text-prev="{{ __('ui.common.prev') }}"
                data-text-next="{{ __('ui.common.next') }}"
                data-text-user-added="{{ __('ui.admin.user_data.user_added') }}"
                data-text-add-failed="{{ __('ui.admin.user_data.add_failed') }}"
                data-text-invalid-user-id="{{ __('ui.admin.user_data.invalid_user_id') }}"
                data-text-reset-password="{{ __('ui.admin.user_data.reset_password') }}"
                data-text-delete-user="{{ __('ui.admin.user_data.delete_user') }}"
                data-text-password-reset-success="{{ __('ui.admin.user_data.password_reset_success') }}"
                data-text-delete-confirm="{{ __('ui.admin.user_data.delete_confirm') }}"
                data-text-user-deleted="{{ __('ui.admin.user_data.user_deleted') }}"
            >
                <table class="admin-user-table user-data-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.common.username') }}</th>
                            <th>{{ __('ui.common.name') }}</th>
                            <th>{{ __('ui.common.email') }}</th>
                            <th>{{ __('ui.common.phone_number') }}</th>
                            <th>{{ __('ui.common.level') }}</th>
                        </tr>
                    </thead>
                    <tbody data-userdata-tbody>
                        @foreach ($userRows as $row)
                            <tr data-userdata-row data-userid="{{ $row['userid'] }}">
                                <td>{{ $row['username'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['email'] }}</td>
                                <td>{{ $row['phonenumber'] }}</td>
                                <td>
                                    <div class="admin-user-level-cell">
                                        <span class="user-level-badge">{{ $row['level'] }}</span>
                                        <div class="admin-user-actions">
                                            <button
                                                type="button"
                                                class="admin-user-action-btn"
                                                title="{{ __('ui.admin.user_data.reset_password') }}"
                                                aria-label="{{ __('ui.admin.user_data.reset_password') }}"
                                                data-user-reset
                                            >
                                                <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true"><path d="M20 11a8 8 0 1 0 2 5.5M20 4v7h-7"/></svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="admin-user-action-btn admin-user-action-delete"
                                                title="{{ __('ui.admin.user_data.delete_user') }}"
                                                aria-label="{{ __('ui.admin.user_data.delete_user') }}"
                                                data-user-delete
                                            >
                                                <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true"><path d="M6 7h12m-9 0V5h6v2m-7 0 1 12h8l1-12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="admin-user-pagination user-data-pagination" data-userdata-pagination>
                <p class="admin-user-pagination-meta" data-userdata-pagination-meta>
                    {{ __('ui.common.showing_range_of_total', ['from' => $userPagination['from'] ?? 0, 'to' => $userPagination['to'] ?? 0, 'total' => $userPagination['total'] ?? 0]) }}
                </p>
                <div class="admin-user-pagination-actions" data-userdata-pagination-actions>
                    @php
                        $currentPage = (int) ($userPagination['page'] ?? 1);
                        $lastPage = (int) ($userPagination['last_page'] ?? 1);
                    @endphp
                    <button type="button" class="btn btn-outline" data-userdata-page="{{ max(1, $currentPage - 1) }}" {{ $currentPage <= 1 ? 'disabled' : '' }}>{{ __('ui.common.prev') }}</button>
                    <span class="admin-user-pagination-page">{{ __('ui.common.page_of', ['page' => $currentPage, 'last_page' => $lastPage]) }}</span>
                    <button type="button" class="btn btn-outline" data-userdata-page="{{ min($lastPage, $currentPage + 1) }}" {{ $currentPage >= $lastPage ? 'disabled' : '' }}>{{ __('ui.common.next') }}</button>
                </div>
            </div>
        </div>
    </div>
</section>

<div
    class="crop-modal service-fade-modal import-modal-center"
    data-import-userdata-modal
    hidden
>
    <div class="crop-modal-backdrop" data-close-import-userdata-modal></div>
    <div class="crop-modal-dialog service-modal-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.admin.user_data.import_modal_aria') }}">
        <div class="crop-modal-head">
            <h2>{{ __('ui.admin.user_data.import_title') }}</h2>
            <button type="button" class="crop-close" data-close-import-userdata-modal aria-label="{{ __('ui.admin.user_data.close_import_modal') }}">x</button>
        </div>

        <form method="post" action="{{ route('admin.userdata.import_excel') }}" enctype="multipart/form-data" class="service-modal-form" data-import-userdata-form>
            @csrf
            <label for="import_userdata_excel">{{ __('ui.common.excel_file') }}</label>
            <input type="file" id="import_userdata_excel" name="userdata_excel" accept=".xlsx" required>

            <div class="service-modal-actions service-modal-actions-right">
                <button type="button" class="btn btn-outline" data-close-import-userdata-modal>{{ __('ui.common.cancel') }}</button>
                <button type="submit" class="btn">{{ __('ui.common.import') }}</button>
            </div>
        </form>
    </div>
</div>

<div
    class="crop-modal"
    data-add-user-modal
    data-store-url="{{ route('admin.userdata.store') }}"
    hidden
>
    <div class="crop-modal-backdrop" data-close-add-user-modal></div>
    <div class="crop-modal-dialog service-modal-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.admin.user_data.add_modal_aria') }}">
        <div class="crop-modal-head">
            <h2>{{ __('ui.admin.user_data.add_user') }}</h2>
            <button type="button" class="crop-close" data-close-add-user-modal aria-label="{{ __('ui.admin.user_data.close_add_modal') }}">x</button>
        </div>

        <form method="post" class="service-modal-form" data-add-user-form>
            @csrf
            <p class="setting-alert" data-add-user-feedback hidden></p>

            <label for="add_user_username">{{ __('ui.common.username') }}</label>
            <input type="text" id="add_user_username" name="username" required>

            <label for="add_user_name">{{ __('ui.common.name') }}</label>
            <input type="text" id="add_user_name" name="name" required>

            <label for="add_user_email">{{ __('ui.common.email') }}</label>
            <input type="email" id="add_user_email" name="email" required>

            <label for="add_user_phonenumber">{{ __('ui.common.phone_number') }}</label>
            <input type="text" id="add_user_phonenumber" name="phonenumber" required>

            <label for="add_user_levelid">{{ __('ui.admin.user_data.level_name') }}</label>
            <select id="add_user_levelid" name="levelid" required>
                <option value="">{{ __('ui.admin.user_data.select_level') }}</option>
                @foreach (($levelOptions ?? []) as $level)
                    <option value="{{ $level['levelid'] }}">{{ $level['levelname'] }}</option>
                @endforeach
            </select>

            <div class="service-modal-actions service-modal-actions-right">
                <button type="button" class="btn btn-outline" data-close-add-user-modal>{{ __('ui.common.cancel') }}</button>
                <button type="submit" class="btn" data-add-user-save>{{ __('ui.common.add') }}</button>
            </div>
        </form>
    </div>
</div>
</main>
</div>
