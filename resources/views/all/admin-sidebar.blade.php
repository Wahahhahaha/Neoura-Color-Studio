<aside class="admin-sidebar" aria-label="Admin Navigation" data-admin-sidebar>
    @php
        $permissionMap = is_array($sidebarPermissionMap ?? null) ? $sidebarPermissionMap : [];
        $canSeeMenu = function (string $menuKey) use ($permissionMap): bool {
            return (bool) ($permissionMap[$menuKey] ?? true);
        };

        $menuItems = [
            [
                'key' => 'home',
                'label' => 'Home',
                'title' => 'Home',
                'route_name' => 'home',
                'href' => route('home'),
                'icon' => 'M3 10.5 12 3l9 7.5M6 9.5V20h12V9.5',
                'always' => true,
                'require_permission' => false,
            ],
            [
                'key' => 'service',
                'label' => 'Service',
                'title' => 'Service',
                'route_name' => 'admin.service',
                'href' => route('admin.service'),
                'icon' => 'M4 6h16M4 12h16M4 18h10',
                'always' => false,
                'require_permission' => true,
            ],
            [
                'key' => 'payment',
                'label' => 'Payment Validation',
                'title' => 'Payment Validation',
                'route_name' => 'admin.payment',
                'href' => route('admin.payment'),
                'icon' => 'M3 7h18v10H3zM3 11h18',
                'always' => false,
                'require_permission' => true,
            ],
            [
                'key' => 'user',
                'label' => 'User Data',
                'title' => 'User Data',
                'route_name' => 'admin.userdata',
                'href' => route('admin.userdata'),
                'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm-7 8a7 7 0 0 1 14 0',
                'always' => false,
                'require_permission' => true,
            ],
            [
                'key' => 'activity',
                'label' => 'Activity Log',
                'title' => 'Activity Log',
                'route_name' => 'admin.activitylog',
                'href' => route('admin.activitylog'),
                'icon' => 'M4 18h16M7 15V9m5 6V6m5 9v-4',
                'always' => false,
                'require_permission' => true,
            ],
            [
                'key' => 'financial',
                'label' => 'Financial Report',
                'title' => 'Financial Report',

                'route_name' => 'admin.financialreport',
                'href' => route('admin.financial'),
                'icon' => 'M5 19h14M7 16V8m5 8V5m5 11v-6',

                'route_name' => 'admin.financial',
                'href' => route('admin.financial'),
                'icon' => 'M4 18h16M7 14v4m5-8v8m5-5v5M5 6h14',

                'always' => false,
                'require_permission' => true,
            ],
            [
                'key' => 'backup',
                'label' => 'Backup Database',
                'title' => 'Backup Database',
                'route_name' => 'superadmin.backup',
                'href' => route('superadmin.backup'),
                'icon' => 'M12 3v8m0 0 3-3m-3 3-3-3M4 14h16v5H4z',
                'always' => false,
                'require_permission' => true,
            ],
            [
                'key' => 'recycle',
                'label' => 'Recyclebin',
                'title' => 'Recyclebin',
                'route_name' => 'superadmin.recyclebin',
                'href' => route('superadmin.recyclebin'),
                'icon' => 'M6 7h12m-9 0V5h6v2m-7 0 1 12h8l1-12',
                'always' => false,
                'require_permission' => true,
            ],
            [
                'key' => 'permission',
                'label' => 'Permission',
                'title' => 'Permission',
                'route_name' => 'superadmin.permission',
                'href' => route('superadmin.permission'),
                'icon' => 'M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3zm-3.5 9.5 2.2 2.2 4.8-4.8',
                'always' => false,
                'require_permission' => true,
            ],
            [
                'key' => 'setting',
                'label' => 'Setting',
                'title' => 'Setting',
                'route_name' => 'superadmin.setting',
                'href' => route('superadmin.setting'),
                'icon' => 'M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zm8 3.5-2 .6a6.9 6.9 0 0 1-.6 1.5l1.2 1.8-1.8 1.8-1.8-1.2c-.5.3-1 .5-1.5.6l-.6 2h-2.6l-.6-2c-.5-.1-1-.3-1.5-.6l-1.8 1.2-1.8-1.8 1.2-1.8c-.3-.5-.5-1-.6-1.5l-2-.6v-2.6l2-.6c.1-.5.3-1 .6-1.5L3 6.7 4.8 5l1.8 1.2c.5-.3 1-.5 1.5-.6l.6-2h2.6l.6 2c.5.1 1 .3 1.5.6L15.2 5 17 6.7l-1.2 1.8c.3.5.5 1 .6 1.5l2 .6v2.4z',
                'always' => false,
                'require_permission' => true,
            ],
        ];

        $visibleMenuItems = collect($menuItems)
            ->filter(function ($item) use ($canSeeMenu) {
                $always = (bool) ($item['always'] ?? false);
                if ($always) {
                    return true;
                }

                $requiresPermission = (bool) ($item['require_permission'] ?? false);
                if ($requiresPermission && !$canSeeMenu((string) ($item['key'] ?? ''))) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();
    @endphp
    <div class="admin-sidebar-head">
        <button type="button" class="admin-sidebar-toggle" data-sidebar-toggle aria-label="Toggle sidebar" aria-expanded="true">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div class="admin-sidebar-dual">
        <nav class="admin-tier-one" aria-label="Sidebar icons">
            @foreach ($visibleMenuItems as $menuItem)
                @php
                    $routeName = $menuItem['route_name'] ?? null;
                    $isActive = is_string($routeName) && $routeName !== '' ? request()->routeIs($routeName) : false;
                @endphp
                <a href="{{ $menuItem['href'] ?? '#' }}" data-menu-key="{{ $menuItem['key'] ?? '' }}" class="{{ $isActive ? 'is-active' : '' }}" title="{{ $menuItem['title'] ?? '' }}">
                    <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true"><path d="{{ $menuItem['icon'] ?? '' }}"/></svg>
                </a>
            @endforeach
        </nav>

        <nav class="admin-tier-two" aria-label="Sidebar menu">
            @foreach ($visibleMenuItems as $menuItem)
                @php
                    $routeName = $menuItem['route_name'] ?? null;
                    $isActive = is_string($routeName) && $routeName !== '' ? request()->routeIs($routeName) : false;
                @endphp
                <a href="{{ $menuItem['href'] ?? '#' }}" data-menu-key="{{ $menuItem['key'] ?? '' }}" class="{{ $isActive ? 'is-active' : '' }}"><span>{{ $menuItem['label'] ?? '' }}</span></a>
            @endforeach
        </nav>
    </div>

    <div class="admin-sidebar-footer">
        <a href="{{ route('account') }}" class="admin-bottom-link {{ request()->routeIs('account') ? 'is-active' : '' }}" data-menu-key="account" title="Account">
            <span class="admin-bottom-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="admin-icon"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm-7 8a7 7 0 0 1 14 0"/></svg>
            </span>
            <span>Account</span>
        </a>

        <form method="post" action="{{ route('logout') }}" class="admin-logout-form">
            @csrf
            <button type="submit" class="admin-bottom-link admin-logout-btn" title="Logout">
                <span class="admin-bottom-icon admin-logout-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="admin-icon"><path d="M15 17l5-5-5-5M20 12H9M12 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6"/></svg>
                </span>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
