<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head">
                <h1>User Data</h1>
                <p>List of users.</p>
                <div class="admin-service-card-actions">
                    <a href="{{ route('admin.userdata.export_excel') }}" class="btn btn-outline">Export Excel (.xlsx)</a>
                    <button type="button" class="btn btn-outline" data-open-import-userdata-modal>Import Excel (.xlsx)</button>
                    <button type="button" class="btn" data-open-add-user-modal>Add User</button>
                </div>
            </div>

            <p class="setting-alert" data-userdata-feedback hidden></p>

            <div class="admin-user-controls">
                <div class="admin-user-search-box">
                    <label for="userdata_search">Search</label>
                    <input
                        type="search"
                        id="userdata_search"
                        placeholder="Username, name, email, phone, level"
                        value="{{ $userFilters['q'] ?? '' }}"
                        data-userdata-search
                    >
                </div>
                <div class="admin-user-level-filter">
                    <label for="userdata_level_filter">Level</label>
                    <select id="userdata_level_filter" data-userdata-level-filter>
                        <option value="0" {{ (int) ($userFilters['levelid'] ?? 0) === 0 ? 'selected' : '' }}>All Levels</option>
                        @foreach (($levelOptions ?? []) as $level)
                            <option value="{{ $level['levelid'] }}" {{ (int) ($userFilters['levelid'] ?? 0) === (int) $level['levelid'] ? 'selected' : '' }}>
                                {{ $level['levelname'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div
                class="admin-user-table-wrap"
                data-userdata-table-wrap
                data-fetch-url="{{ route('admin.userdata') }}"
                data-reset-url-template="{{ route('admin.userdata.reset_password', ['userid' => '__USER_ID__']) }}"
                data-delete-url-template="{{ route('admin.userdata.delete', ['userid' => '__USER_ID__']) }}"
            >
                <table class="admin-user-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>Level</th>
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
                                        <span>{{ $row['level'] }}</span>
                                        <div class="admin-user-actions">
                                            <button
                                                type="button"
                                                class="admin-user-action-btn"
                                                title="Reset Password"
                                                aria-label="Reset Password"
                                                data-user-reset
                                            >
                                                <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true"><path d="M20 11a8 8 0 1 0 2 5.5M20 4v7h-7"/></svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="admin-user-action-btn admin-user-action-delete"
                                                title="Delete User"
                                                aria-label="Delete User"
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

            <div class="admin-user-pagination" data-userdata-pagination>
                <p class="admin-user-pagination-meta" data-userdata-pagination-meta>
                    Showing {{ $userPagination['from'] ?? 0 }}-{{ $userPagination['to'] ?? 0 }} of {{ $userPagination['total'] ?? 0 }}
                </p>
                <div class="admin-user-pagination-actions" data-userdata-pagination-actions>
                    @php
                        $currentPage = (int) ($userPagination['page'] ?? 1);
                        $lastPage = (int) ($userPagination['last_page'] ?? 1);
                    @endphp
                    <button type="button" class="btn btn-outline" data-userdata-page="{{ max(1, $currentPage - 1) }}" {{ $currentPage <= 1 ? 'disabled' : '' }}>Prev</button>
                    <span class="admin-user-pagination-page">Page {{ $currentPage }} / {{ $lastPage }}</span>
                    <button type="button" class="btn btn-outline" data-userdata-page="{{ min($lastPage, $currentPage + 1) }}" {{ $currentPage >= $lastPage ? 'disabled' : '' }}>Next</button>
                </div>
            </div>
        </div>
    </div>
</section>

<div
    class="crop-modal service-fade-modal"
    data-import-userdata-modal
    hidden
>
    <div class="crop-modal-backdrop" data-close-import-userdata-modal></div>
    <div class="crop-modal-dialog service-modal-dialog" role="dialog" aria-modal="true" aria-label="Import user data Excel">
        <div class="crop-modal-head">
            <h2>Import User Data Excel</h2>
            <button type="button" class="crop-close" data-close-import-userdata-modal aria-label="Close import user data modal">x</button>
        </div>

        <form method="post" action="{{ route('admin.userdata.import_excel') }}" enctype="multipart/form-data" class="service-modal-form" data-import-userdata-form>
            @csrf
            <label for="import_userdata_excel">Excel File (.xlsx)</label>
            <input type="file" id="import_userdata_excel" name="userdata_excel" accept=".xlsx" required>

            <div class="service-modal-actions service-modal-actions-right">
                <button type="button" class="btn btn-outline" data-close-import-userdata-modal>Cancel</button>
                <button type="submit" class="btn">Import</button>
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
    <div class="crop-modal-dialog service-modal-dialog" role="dialog" aria-modal="true" aria-label="Add user">
        <div class="crop-modal-head">
            <h2>Add User</h2>
            <button type="button" class="crop-close" data-close-add-user-modal aria-label="Close add user modal">x</button>
        </div>

        <form method="post" class="service-modal-form" data-add-user-form>
            @csrf
            <p class="setting-alert" data-add-user-feedback hidden></p>

            <label for="add_user_username">Username</label>
            <input type="text" id="add_user_username" name="username" required>

            <label for="add_user_name">Name</label>
            <input type="text" id="add_user_name" name="name" required>

            <label for="add_user_email">Email</label>
            <input type="email" id="add_user_email" name="email" required>

            <label for="add_user_phonenumber">Phone Number</label>
            <input type="text" id="add_user_phonenumber" name="phonenumber" required>

            <label for="add_user_levelid">Level Name</label>
            <select id="add_user_levelid" name="levelid" required>
                <option value="">Select level</option>
                @foreach (($levelOptions ?? []) as $level)
                    <option value="{{ $level['levelid'] }}">{{ $level['levelname'] }}</option>
                @endforeach
            </select>

            <div class="service-modal-actions service-modal-actions-right">
                <button type="button" class="btn btn-outline" data-close-add-user-modal>Cancel</button>
                <button type="submit" class="btn" data-add-user-save>Add</button>
            </div>
        </form>
    </div>
</div>
</main>
</div>
