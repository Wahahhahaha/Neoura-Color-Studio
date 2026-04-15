<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AdminActivityLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $adminAuth = $request->session()->get('admin_auth');
            if (!$this->canSeeAdminMenu($adminAuth) || $this->shouldSkipAutoActivityLog($request)) {
                return $response;
            }

            $this->appendActivityLog($request, is_array($adminAuth) ? $adminAuth : null, $response->getStatusCode());
        } catch (\Throwable $error) {
            Log::warning('Failed to append activity log', [
                'message' => $error->getMessage(),
                'path' => (string) $request->path(),
            ]);
        }

        return $response;
    }

    private function canSeeAdminMenu(mixed $adminAuth): bool
    {
        if (!is_array($adminAuth) || empty($adminAuth['levelname'])) {
            return false;
        }

        $level = strtolower(trim((string) ($adminAuth['levelname'] ?? '')));
        return in_array($level, ['admin', 'owner', 'manager', 'superadmin'], true);
    }

    private function shouldSkipAutoActivityLog(Request $request): bool
    {
        $routeName = (string) optional($request->route())->getName();
        $method = strtoupper((string) $request->method());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        return in_array($routeName, ['admin.logo.click', 'admin.activitylog.location'], true);
    }

    private function activityLogFile(): string
    {
        return storage_path('app/activity-log.json');
    }

    private function activityLogEntries(): array
    {
        $file = $this->activityLogFile();
        if (!is_file($file)) {
            return [];
        }

        $raw = file_get_contents($file);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn($entry) => is_array($entry)));
    }

    private function saveActivityLogEntries(array $entries): void
    {
        $normalized = array_values(array_filter($entries, fn($entry) => is_array($entry)));
        if (count($normalized) > 5000) {
            $normalized = array_slice($normalized, 0, 5000);
        }

        file_put_contents(
            $this->activityLogFile(),
            json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function sanitizeActivityPayload(array $payload): array
    {
        $sensitiveKeys = [
            'password',
            'new_password',
            'new_password_confirmation',
            'current_password',
            'otp_code',
            'login_form_token',
            '_token',
            'systemlogo',
        ];

        $result = [];
        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));
            if (in_array($normalizedKey, $sensitiveKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $result[$key] = array_map(function ($item) {
                    if (is_scalar($item) || $item === null) {
                        return (string) $item;
                    }
                    return '[complex]';
                }, $value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }

    private function resolveActivityCoordinates(Request $request): array
    {
        $sessionCoords = $request->session()->get('admin_activity_coords');
        $sessionLat = is_array($sessionCoords) ? trim((string) ($sessionCoords['latitude'] ?? '')) : '';
        $sessionLng = is_array($sessionCoords) ? trim((string) ($sessionCoords['longitude'] ?? '')) : '';

        $lat = trim((string) (
            $request->input('latitude')
                ?? $request->query('latitude')
                ?? $request->header('X-Latitude')
                ?? $sessionLat
                ?? ''
        ));
        $lng = trim((string) (
            $request->input('longitude')
                ?? $request->query('longitude')
                ?? $request->header('X-Longitude')
                ?? $sessionLng
                ?? ''
        ));

        return [
            'latitude' => $lat !== '' ? $lat : '-',
            'longitude' => $lng !== '' ? $lng : '-',
        ];
    }

    private function resolveActivityActionLabel(Request $request): string
    {
        $overrideAction = trim((string) $request->attributes->get('activity_action_override', ''));
        if ($overrideAction !== '') {
            return $overrideAction;
        }

        $routeName = (string) optional($request->route())->getName();
        $method = strtoupper((string) $request->method());
        $map = [
            'login.submit' => 'Login',
            'logout' => 'Logout',
            'admin.service.store' => 'Create Service',
            'admin.service.update' => 'Update Service',
            'admin.service.delete' => 'Delete Service',
            'admin.userdata.store' => 'Create User',
            'admin.userdata.reset_password' => 'Reset User Password',
            'admin.userdata.delete' => 'Delete User',
            'admin.payment.update' => 'Update Payment Validation',
            'admin.timeslot.store' => 'Create Time Slot Block',
            'admin.timeslot.delete' => 'Delete Time Slot Block',
            'account.update' => 'Update Account',
            'superadmin.recyclebin.restore' => 'Restore Recycle Item',
            'superadmin.recyclebin.delete_permanent' => 'Delete Recycle Item Permanently',
            'superadmin.setting.update' => 'Update Setting',
            'superadmin.permission.update' => 'Update Sidebar Permission',
            'carousel.update' => 'Update Home Carousel',
            'about.update' => 'Update About Content',
            'about.images.update' => 'Update About Image Switcher',
        ];

        if ($routeName !== '' && isset($map[$routeName])) {
            return $map[$routeName];
        }

        if ($routeName !== '') {
            return $method . ' ' . $routeName;
        }

        return $method . ' ' . trim((string) $request->path());
    }

    private function resolveActivityDetail(Request $request, int $statusCode): string
    {
        $overrideDetail = trim((string) $request->attributes->get('activity_detail_override', ''));
        if ($overrideDetail !== '') {
            return $overrideDetail;
        }

        $routeName = (string) optional($request->route())->getName();
        $payload = $request->isMethod('get')
            ? $this->sanitizeActivityPayload((array) $request->query())
            : $this->sanitizeActivityPayload((array) $request->except(['_token']));

        $detail = [
            'route' => $routeName !== '' ? $routeName : trim((string) $request->path()),
            'status_code' => $statusCode,
        ];

        if (!empty($payload)) {
            $detail['payload'] = $payload;
        }

        $json = json_encode($detail, JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || trim($json) === '') {
            return '-';
        }

        return $json;
    }

    private function appendActivityLog(Request $request, ?array $adminAuth, int $statusCode = 200): void
    {
        $coordinates = $this->resolveActivityCoordinates($request);
        $entries = $this->activityLogEntries();

        array_unshift($entries, [
            'activity_id' => (string) Str::uuid(),
            'name' => trim((string) ($adminAuth['username'] ?? '')) !== ''
                ? trim((string) ($adminAuth['username'] ?? ''))
                : (trim((string) ($adminAuth['employer_name'] ?? '')) !== '' ? trim((string) ($adminAuth['employer_name'] ?? '')) : 'Unknown User'),
            'ip_address' => trim((string) $request->ip()) !== '' ? trim((string) $request->ip()) : '-',
            'longitude' => (string) ($coordinates['longitude'] ?? '-'),
            'latitude' => (string) ($coordinates['latitude'] ?? '-'),
            'action' => $this->resolveActivityActionLabel($request),
            'datetime' => now()->toDateTimeString(),
            'detail' => $this->resolveActivityDetail($request, $statusCode),
            'actor' => [
                'userid' => (int) ($adminAuth['userid'] ?? 0),
                'levelname' => (string) ($adminAuth['levelname'] ?? ''),
            ],
        ]);

        $this->saveActivityLogEntries($entries);
    }
}
