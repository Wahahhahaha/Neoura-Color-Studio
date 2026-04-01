<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Ctrl extends Controller
{
    private array $defaultContact = [
        'phone' => '+62 812-3456-7890',
        'instagram' => '@neoracolorstudio',
        'address' => 'Jl. Kemang Raya No. 18, Jakarta Selatan, Indonesia',
        'maps' => 'https://maps.google.com/?q=Kemang+Raya+18+Jakarta',
    ];

    private function renderParts(array $parts, array $data = []): void
    {
        foreach ($parts as $part) {
            echo view($part, $data);
        }
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

        return collect($decoded)
            ->filter(fn($entry) => is_array($entry))
            ->values()
            ->all();
    }

    private function saveActivityLogEntries(array $entries): void
    {
        $normalized = array_values(
            collect($entries)
                ->filter(fn($entry) => is_array($entry))
                ->take(5000)
                ->all()
        );

        file_put_contents(
            $this->activityLogFile(),
            json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function shouldSkipAutoActivityLog(Request $request): bool
    {
        $routeName = (string) optional($request->route())->getName();
        $skipRoutes = [
            'admin.logo.click',
        ];

        return in_array($routeName, $skipRoutes, true);
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
                $result[$key] = collect($value)->map(function ($item) {
                    if (is_scalar($item) || $item === null) {
                        return (string) $item;
                    }

                    return '[complex]';
                })->values()->all();
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
            'account.update' => 'Update Account',
            'superadmin.recyclebin.restore' => 'Restore Recycle Item',
            'superadmin.recyclebin.delete_permanent' => 'Delete Recycle Item Permanently',
            'superadmin.setting.update' => 'Update Setting',
            'superadmin.permission.update' => 'Update Sidebar Permission',
            'carousel.update' => 'Update Home Carousel',
            'about.update' => 'Update About Content',
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

    private function servicePackages(): array
    {
        return [
            'Basic Session' => [
                'name' => 'Basic Session',
                'duration' => '60 minutes',
                'price' => 'IDR 850,000',
                'description' => 'Essential personal color consultation for a clean and confident daily look.',
                'includes' => [
                    '4 Seasons Color',
                    'Hair Color',
                    'Accessories',
                    'Makeup Consultation',
                ],
            ],
            'Exclusive Session' => [
                'name' => 'Exclusive Session',
                'duration' => '90 minutes',
                'price' => 'IDR 1,200,000',
                'description' => 'Expanded analysis for better outfit strategy and silhouette alignment.',
                'includes' => [
                    'All Basic +',
                    'Silhouette Analysis',
                    'Style Guidance',
                ],
            ],
            'Luxe Session' => [
                'name' => 'Luxe Session',
                'duration' => '120 minutes',
                'price' => 'IDR 2,200,000',
                'description' => 'Complete premium service for polished image planning and high-touch guidance.',
                'includes' => [
                    'All Exclusive +',
                    'Bridal Harmony / Shopping Guide',
                ],
            ],
        ];
    }

    private function sidebarServices(): array
    {
        $services = DB::connection('mysql')
            ->table('neoura.service')
            ->select('serviceid', 'name')
            ->orderBy('serviceid')
            ->get();

        if ($services->isEmpty()) {
            return [];
        }

        $descriptionRows = DB::connection('mysql')
            ->table('neoura.description')
            ->select('serviceid', 'name')
            ->orderBy('descriptionid')
            ->get()
            ->groupBy('serviceid');

        return $services->map(function ($service) use ($descriptionRows) {
            $descriptions = $descriptionRows
                ->get($service->serviceid, collect())
                ->pluck('name')
                ->map(fn($value) => trim((string) $value))
                ->filter()
                ->values()
                ->all();

            return [
                'serviceid' => (int) $service->serviceid,
                'name' => trim((string) $service->name),
                'descriptions' => $descriptions,
            ];
        })->all();
    }

    private function servicePageRows(): array
    {
        $services = DB::connection('mysql')
            ->table('neoura.service')
            ->select('serviceid', 'name', 'detail', 'duration', 'price')
            ->orderBy('serviceid')
            ->get();

        if ($services->isEmpty()) {
            return [];
        }

        $descriptionRows = DB::connection('mysql')
            ->table('neoura.description')
            ->select('serviceid', 'name')
            ->orderBy('descriptionid')
            ->get()
            ->groupBy('serviceid');

        return $services->map(function ($service) use ($descriptionRows) {
            $descriptions = $descriptionRows
                ->get($service->serviceid, collect())
                ->pluck('name')
                ->map(fn($value) => trim((string) $value))
                ->filter()
                ->values()
                ->all();

            return [
                'serviceid' => (int) $service->serviceid,
                'name' => trim((string) $service->name),
                'detail' => trim((string) ($service->detail ?? '')),
                'duration' => trim((string) $service->duration),
                'price' => trim((string) $service->price),
                'descriptions' => $descriptions,
            ];
        })->all();
    }

    private function serviceRecycleBinFile(): string
    {
        return storage_path('app/service-recycle-bin.json');
    }

    private function serviceRecycleBinEntries(): array
    {
        $file = $this->serviceRecycleBinFile();
        if (!is_file($file)) {
            return [];
        }

        $raw = file_get_contents($file);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn($entry) => is_array($entry))
            ->values()
            ->all();
    }

    private function saveServiceRecycleBinEntries(array $entries): void
    {
        $normalized = array_values(
            collect($entries)
                ->filter(fn($entry) => is_array($entry))
                ->take(500)
                ->all()
        );

        file_put_contents(
            $this->serviceRecycleBinFile(),
            json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function serviceSnapshotById(int $serviceId): ?array
    {
        if ($serviceId <= 0) {
            return null;
        }

        $service = DB::connection('mysql')
            ->table('neoura.service')
            ->select('serviceid', 'name', 'detail', 'duration', 'price')
            ->where('serviceid', $serviceId)
            ->first();

        if (!$service) {
            return null;
        }

        $descriptions = DB::connection('mysql')
            ->table('neoura.description')
            ->select('name')
            ->where('serviceid', $serviceId)
            ->orderBy('descriptionid')
            ->get()
            ->map(fn($row) => trim((string) ($row->name ?? '')))
            ->filter()
            ->values()
            ->all();

        return [
            'serviceid' => (int) ($service->serviceid ?? 0),
            'name' => trim((string) ($service->name ?? '')),
            'detail' => trim((string) ($service->detail ?? '')),
            'duration' => trim((string) ($service->duration ?? '')),
            'price' => trim((string) ($service->price ?? '')),
            'descriptions' => $descriptions,
        ];
    }

    private function normalizeServiceSnapshotFromInput(int $serviceId, array $validated, array $descriptions): array
    {
        return [
            'serviceid' => $serviceId,
            'name' => trim((string) ($validated['name'] ?? '')),
            'detail' => trim((string) ($validated['detail'] ?? '')),
            'duration' => trim((string) ($validated['duration'] ?? '')),
            'price' => trim((string) ($validated['price'] ?? '')),
            'descriptions' => collect($descriptions)
                ->map(fn($value) => trim((string) $value))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function serviceRecycleChangesForUpdate(array $before, array $after): array
    {
        $changes = [];
        foreach (['name', 'detail', 'duration', 'price'] as $field) {
            $from = (string) ($before[$field] ?? '');
            $to = (string) ($after[$field] ?? '');
            if ($from === $to) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'from' => $from,
                'to' => $to,
                'type' => 'updated',
            ];
        }

        $beforeDescriptions = collect($before['descriptions'] ?? [])
            ->map(fn($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        $afterDescriptions = collect($after['descriptions'] ?? [])
            ->map(fn($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        if (implode("\n", $beforeDescriptions) !== implode("\n", $afterDescriptions)) {
            $changes[] = [
                'field' => 'descriptions',
                'from' => implode("\n", $beforeDescriptions),
                'to' => implode("\n", $afterDescriptions),
                'type' => 'updated',
            ];
        }

        return $changes;
    }

    private function serviceRecycleChangesForDelete(array $before): array
    {
        $changes = [];
        foreach (['name', 'detail', 'duration', 'price'] as $field) {
            $value = (string) ($before[$field] ?? '');
            if ($value === '') {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'from' => $value,
                'to' => '',
                'type' => 'deleted',
            ];
        }

        $descriptions = collect($before['descriptions'] ?? [])
            ->map(fn($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        if (!empty($descriptions)) {
            $changes[] = [
                'field' => 'descriptions',
                'from' => implode("\n", $descriptions),
                'to' => '',
                'type' => 'deleted',
            ];
        }

        return $changes;
    }

    private function serviceActivityDetailFromChanges(array $changes): string
    {
        $parts = [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            $field = trim((string) ($change['field'] ?? ''));
            $from = trim((string) ($change['from'] ?? ''));
            $to = trim((string) ($change['to'] ?? ''));
            if ($field === '') {
                continue;
            }

            $fromText = $from !== '' ? $from : '(empty)';
            $toText = $to !== '' ? $to : '(empty)';
            $parts[] = "{$field} from {$fromText} to {$toText}";
        }

        if (empty($parts)) {
            return 'No field changes detected.';
        }

        return implode('; ', $parts);
    }

    private function archiveServiceRecycleEntry(
        string $action,
        int $serviceId,
        ?array $adminAuth,
        string $ipAddress = '',
        ?array $nextSnapshot = null
    ): void
    {
        $snapshot = $this->serviceSnapshotById($serviceId);
        if (!$snapshot) {
            return;
        }

        $normalizedAction = strtolower(trim($action)) === 'delete' ? 'delete' : 'update';
        $changes = $normalizedAction === 'delete'
            ? $this->serviceRecycleChangesForDelete($snapshot)
            : $this->serviceRecycleChangesForUpdate($snapshot, is_array($nextSnapshot) ? $nextSnapshot : []);

        $detailText = $normalizedAction === 'delete'
            ? 'Service deleted.'
            : (empty($changes) ? 'Service edit submitted (no field changes detected).' : 'Service edited.');

        $entry = [
            'recycle_id' => (string) Str::uuid(),
            'action' => $normalizedAction,
            'archived_at' => now()->toDateTimeString(),
            'ip_address' => trim($ipAddress) !== '' ? trim($ipAddress) : '-',
            'actor' => [
                'userid' => (int) ($adminAuth['userid'] ?? 0),
                'username' => (string) ($adminAuth['username'] ?? ''),
                'levelname' => (string) ($adminAuth['levelname'] ?? ''),
            ],
            'detail_text' => $detailText,
            'changes' => $changes,
            'service' => $snapshot,
        ];

        $entries = $this->serviceRecycleBinEntries();
        array_unshift($entries, $entry);
        $this->saveServiceRecycleBinEntries($entries);
    }

    private function restoreServiceFromSnapshot(array $snapshot): int
    {
        $snapshotServiceId = (int) ($snapshot['serviceid'] ?? 0);
        $targetService = $snapshotServiceId > 0
            ? DB::connection('mysql')->table('neoura.service')->select('serviceid')->where('serviceid', $snapshotServiceId)->first()
            : null;

        $servicePayload = [
            'name' => trim((string) ($snapshot['name'] ?? '')),
            'detail' => trim((string) ($snapshot['detail'] ?? '')),
            'duration' => trim((string) ($snapshot['duration'] ?? '')),
            'price' => trim((string) ($snapshot['price'] ?? '')),
        ];

        foreach (['name', 'detail', 'duration', 'price'] as $requiredField) {
            if (trim((string) ($servicePayload[$requiredField] ?? '')) === '') {
                throw new \RuntimeException('Snapshot service data is incomplete.');
            }
        }

        if ($targetService) {
            $restoreServiceId = (int) ($targetService->serviceid ?? 0);
            DB::connection('mysql')
                ->table('neoura.service')
                ->where('serviceid', $restoreServiceId)
                ->update($servicePayload);
        } else {
            $restoreServiceId = (int) DB::connection('mysql')
                ->table('neoura.service')
                ->insertGetId($servicePayload);
        }

        $descriptions = collect($snapshot['descriptions'] ?? [])
            ->map(fn($value) => trim((string) $value))
            ->filter()
            ->values();

        DB::connection('mysql')
            ->table('neoura.description')
            ->where('serviceid', $restoreServiceId)
            ->delete();

        if ($descriptions->isNotEmpty()) {
            DB::connection('mysql')
                ->table('neoura.description')
                ->insert(
                    $descriptions
                        ->map(fn($description) => [
                            'name' => $description,
                            'serviceid' => $restoreServiceId,
                        ])
                        ->all()
                );
        }

        return $restoreServiceId;
    }

    private function bookingPackagesFromDatabase(): array
    {
        $rows = $this->servicePageRows();
        if (empty($rows)) {
            return $this->servicePackages();
        }

        $packages = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $descriptions = $row['descriptions'] ?? [];
            $packages[$name] = [
                'serviceid' => (int) ($row['serviceid'] ?? 0),
                'name' => $name,
                'duration' => (string) ($row['duration'] ?? ''),
                'price' => (string) ($row['price'] ?? ''),
                'description' => (string) ($row['detail'] ?? (!empty($descriptions) ? (string) $descriptions[0] : 'Service details are available in the description list.')),
                'includes' => array_values($descriptions),
            ];
        }

        return !empty($packages) ? $packages : $this->servicePackages();
    }

    private function resolveServiceId(array $bookingPackage): int
    {
        $fromPayload = (int) ($bookingPackage['serviceid'] ?? 0);
        if ($fromPayload > 0) {
            return $fromPayload;
        }

        $name = trim((string) ($bookingPackage['name'] ?? ''));
        if ($name === '') {
            return 0;
        }

        $service = DB::connection('mysql')
            ->table('neoura.service')
            ->select('serviceid')
            ->where('name', $name)
            ->first();

        return (int) ($service->serviceid ?? 0);
    }

    private function bookedSlots(): array
    {
        return DB::connection('mysql')
            ->table('neoura.timeslot')
            ->select('date', 'start_time', 'end_time')
            ->where('is_booked', 1)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($row) {
                $start = substr((string) ($row->start_time ?? ''), 0, 5);
                $end = substr((string) ($row->end_time ?? ''), 0, 5);

                return [
                    'booking_date' => (string) ($row->date ?? ''),
                    'start_time' => $start,
                    'end_time' => $end,
                ];
            })
            ->all();
    }

    private function serviceDurationMinutes(string $durationLabel): int
    {
        if (preg_match('/(\d+)/', $durationLabel, $match) === 1) {
            $value = (int) ($match[1] ?? 0);
            if ($value > 0) {
                $lower = strtolower($durationLabel);
                if (str_contains($lower, 'hour') || str_contains($lower, 'jam') || str_contains($lower, 'hr')) {
                    return $value * 60;
                }

                return $value;
            }
        }

        return 60;
    }

    private function toMinutes(string $hhmm): int
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hhmm, $match) !== 1) {
            return -1;
        }

        return ((int) $match[1] * 60) + (int) $match[2];
    }

    private function toHm(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    private function toSlotLabel(string $hhmm): string
    {
        $minutes = $this->toMinutes($hhmm);
        if ($minutes < 0) {
            return $hhmm;
        }

        $hours24 = intdiv($minutes, 60);
        $mins = $minutes % 60;
        $ampm = $hours24 >= 12 ? 'PM' : 'AM';
        $hours12 = $hours24 % 12;
        if ($hours12 === 0) {
            $hours12 = 12;
        }

        return sprintf('%02d:%02d %s', $hours12, $mins, $ampm);
    }

    private function isOverlap(int $startA, int $endA, int $startB, int $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }

    private function timeOptions(string $open = '10:00', string $close = '22:00', int $stepMinutes = 30): array
    {
        $openMinutes = $this->toMinutes($open);
        $closeMinutes = $this->toMinutes($close);
        if ($openMinutes < 0 || $closeMinutes <= $openMinutes) {
            return [];
        }

        $options = [];
        for ($slot = $openMinutes; $slot < $closeMinutes; $slot += $stepMinutes) {
            $value = $this->toHm($slot);
            $options[] = [
                'value' => $value,
                'label' => $this->toSlotLabel($value),
            ];
        }

        return $options;
    }

    private function generateBookingCode(): string
    {
        do {
            $code = 'NRA-' . strtoupper(Str::random(3)) . random_int(100, 999);
            $exists = DB::connection('mysql')
                ->table('neoura.booking')
                ->where('bookingcode', $code)
                ->exists();
        } while ($exists);

        return $code;
    }

    private function normalizeWhatsAppNumber(string $phone): string
    {
        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($raw === '' || $digits === '') {
            return '';
        }

        if (Str::startsWith($raw, '+')) {
            return $digits;
        }

        if (Str::startsWith($digits, '00')) {
            return ltrim(substr($digits, 2), '0');
        }

        if (Str::startsWith($digits, '0')) {
            $fallbackCountryCode = trim((string) env('FONNTE_DEFAULT_COUNTRY_CODE', '62'));
            $localPart = ltrim($digits, '0');
            return $fallbackCountryCode . $localPart;
        }

        return $digits;
    }

    private function whatsappMessageText(
        string $customerName,
        string $serviceName,
        string $bookingCode,
        string $bookingDate,
        string $startTime,
        string $endTime,
        string $studioName = 'Neora Color Studio',
        ?string $locale = null
    ): string
    {
        $studio = trim($studioName) !== '' ? trim($studioName) : 'Neora Color Studio';
        $activeLocale = strtolower(trim((string) ($locale ?? app()->getLocale())));
        $isIndonesian = str_starts_with($activeLocale, 'id');

        if ($isIndonesian) {
            return implode("\n", [
                "Halo {$customerName},",
                "",
                "Terima kasih sudah booking di *{$studio}*.",
                "",
                "*Detail Booking Anda:*",
                "- Kode Booking: *{$bookingCode}*",
                "- Layanan: {$serviceName}",
                "- Tanggal: {$bookingDate}",
                "- Waktu: {$startTime} - {$endTime}",
                "- Status: Menunggu Validasi Pembayaran",
                "",
                "Simpan kode booking Anda untuk cek status booking.",
                "",
                "Salam,",
                $studio,
            ]);
        }

        return implode("\n", [
            "Dear {$customerName},",
            "",
            "Thank you for booking with *{$studio}*.",
            "",
            "*Your Booking Details:*",
            "- Booking Code: *{$bookingCode}*",
            "- Service: {$serviceName}",
            "- Date: {$bookingDate}",
            "- Time: {$startTime} - {$endTime}",
            "- Status: Pending Payment Validation",
            "",
            "Please keep your booking code for booking status verification.",
            "",
            "Best regards,",
            $studio,
        ]);
    }

    private function bookingEmailSubject(string $bookingCode, ?string $locale = null): string
    {
        $activeLocale = strtolower(trim((string) ($locale ?? app()->getLocale())));
        if (str_starts_with($activeLocale, 'id')) {
            return "Konfirmasi Booking - {$bookingCode}";
        }

        return "Booking Confirmation - {$bookingCode}";
    }

    private function bookingEmailText(
        string $customerName,
        string $serviceName,
        string $bookingCode,
        string $bookingDate,
        string $startTime,
        string $endTime,
        string $studioName = 'Neora Color Studio',
        ?string $locale = null
    ): string
    {
        $studio = trim($studioName) !== '' ? trim($studioName) : 'Neora Color Studio';
        $activeLocale = strtolower(trim((string) ($locale ?? app()->getLocale())));

        if (str_starts_with($activeLocale, 'id')) {
            return implode("\n", [
                "Halo {$customerName},",
                "",
                "Terima kasih sudah booking di {$studio}.",
                "",
                "Detail booking Anda:",
                "Kode Booking: {$bookingCode}",
                "Layanan: {$serviceName}",
                "Tanggal: {$bookingDate}",
                "Waktu: {$startTime} - {$endTime}",
                "Status: Menunggu Validasi Pembayaran",
                "",
                "Simpan kode booking Anda untuk cek status booking.",
                "",
                "Salam,",
                $studio,
            ]);
        }

        return implode("\n", [
            "Dear {$customerName},",
            "",
            "Thank you for booking with {$studio}.",
            "",
            "Your booking details:",
            "Booking Code: {$bookingCode}",
            "Service: {$serviceName}",
            "Date: {$bookingDate}",
            "Time: {$startTime} - {$endTime}",
            "Status: Pending Payment Validation",
            "",
            "Please keep your booking code for booking status verification.",
            "",
            "Best regards,",
            $studio,
        ]);
    }

    private function sendBookingEmail(string $email, string $subject, string $message): array
    {
        $target = trim($email);
        if ($target === '') {
            return [
                'sent' => false,
                'reason' => 'Email address is empty.',
            ];
        }

        $maxAttempts = 2;
        $lastError = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                Mail::raw($message, function ($mail) use ($target, $subject) {
                    $mail->to($target)->subject($subject);
                });

                return [
                    'sent' => true,
                    'reason' => '',
                ];
            } catch (\Throwable $error) {
                $lastError = $error->getMessage();
                Log::warning('Booking email send failed', [
                    'attempt' => $attempt,
                    'target' => $target,
                    'message' => $lastError,
                ]);

                if ($attempt < $maxAttempts) {
                    usleep(300000);
                }
            }
        }

        Log::error('Booking email send exception', [
            'message' => $lastError,
            'target' => $target,
        ]);

        return [
            'sent' => false,
            'reason' => $lastError !== '' ? $lastError : 'Failed to send booking email.',
        ];
    }

    private function accountEmailChangeRequestsFile(): string
    {
        return storage_path('app/account-email-change-requests.json');
    }

    private function accountEmailChangeRequests(): array
    {
        $file = $this->accountEmailChangeRequestsFile();
        if (!is_file($file)) {
            return [];
        }

        $raw = file_get_contents($file);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn($row) => is_array($row))
            ->values()
            ->all();
    }

    private function saveAccountEmailChangeRequests(array $rows): void
    {
        $normalized = array_values(
            collect($rows)
                ->filter(fn($row) => is_array($row))
                ->take(500)
                ->all()
        );

        file_put_contents(
            $this->accountEmailChangeRequestsFile(),
            json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function pruneAccountEmailChangeRequests(array $rows): array
    {
        $nowTs = time();
        return array_values(array_filter($rows, function ($row) use ($nowTs) {
            if (!is_array($row)) {
                return false;
            }

            return (int) ($row['expires_at_ts'] ?? 0) >= $nowTs;
        }));
    }

    private function queueAccountEmailChangeVerification(int $userId, string $nextEmail): array
    {
        $email = trim($nextEmail);
        if ($userId <= 0 || $email === '') {
            return [
                'sent' => false,
                'reason' => 'Invalid email change request.',
            ];
        }

        $token = (string) Str::random(64);
        $requestedAt = now();
        $expiresAt = $requestedAt->copy()->addHours(24);
        $verifyLink = route('account.email_change.verify', ['token' => $token]);
        $appName = trim((string) config('app.name', 'Neora Color Studio')) ?: 'Neora Color Studio';

        $message = implode("\n", [
            "Hello,",
            "",
            "We received a request to change the email address for your {$appName} account.",
            "",
            "Requested new email: {$email}",
            "Requested at: " . $requestedAt->format('d M Y H:i:s T'),
            "Link expires at: " . $expiresAt->format('d M Y H:i:s T'),
            "",
            "To confirm this change, open the secure link below:",
            $verifyLink,
            "",
            "If you did not request this change, you can safely ignore this email. Your current email will remain unchanged.",
            "",
            "For security reasons, please do not share this message or link.",
            "",
            "Regards,",
            "{$appName} Support Team",
        ]);

        $mailResult = $this->sendBookingEmail($email, 'Email Change Verification', $message);
        if (!(bool) ($mailResult['sent'] ?? false)) {
            return $mailResult;
        }

        $rows = $this->pruneAccountEmailChangeRequests($this->accountEmailChangeRequests());

        // Keep one active token per user and per destination email.
        $rows = array_values(array_filter($rows, function ($row) use ($userId, $email) {
            if (!is_array($row)) {
                return false;
            }

            $sameUser = (int) ($row['userid'] ?? 0) === $userId;
            $sameEmail = strtolower(trim((string) ($row['next_email'] ?? ''))) === strtolower($email);
            return !$sameUser && !$sameEmail;
        }));

        array_unshift($rows, [
            'token' => $token,
            'userid' => $userId,
            'next_email' => $email,
            'created_at' => now()->toDateTimeString(),
            'expires_at' => $expiresAt->toDateTimeString(),
            'expires_at_ts' => $expiresAt->timestamp,
        ]);

        $this->saveAccountEmailChangeRequests($rows);

        return [
            'sent' => true,
            'reason' => '',
        ];
    }

    private function phoneOtpMessageText(string $otp): string
    {
        return implode("\n", [
            "Neora Color Studio - Phone Verification",
            "",
            "Your OTP code is: *{$otp}*",
            "This code is valid for 5 minutes.",
            "",
            "Do not share this code with anyone.",
        ]);
    }

    private function sendWhatsAppMessage(string $phoneNumber, string $message): array
    {
        $token = trim((string) env('FONNTE_TOKEN', ''));
        if ($token === '') {
            return [
                'sent' => false,
                'reason' => 'FONNTE_TOKEN is empty.',
            ];
        }

        $target = $this->normalizeWhatsAppNumber($phoneNumber);
        if ($target === '') {
            return [
                'sent' => false,
                'reason' => 'Phone number is invalid.',
            ];
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->retry(2, 300)
                ->withHeaders(['Authorization' => $token])
                ->post('https://api.fonnte.com/send', [
                    'target' => $target,
                    'message' => $message,
                    'countryCode' => '0',
                    'typing' => 'true',
                    'connectOnly' => 'false',
                ]);

            $body = $response->json();
            $status = is_array($body) ? (bool) ($body['status'] ?? false) : false;
            if (!$response->successful() || !$status) {
                Log::warning('Fonnte send failed', [
                    'http_status' => $response->status(),
                    'response_body' => $response->body(),
                    'target' => $target,
                ]);

                return [
                    'sent' => false,
                    'reason' => is_array($body) ? (string) ($body['reason'] ?? 'Fonnte response status false.') : 'Fonnte response invalid.',
                    'http_status' => $response->status(),
                ];
            }

            return [
                'sent' => true,
                'reason' => '',
            ];
        } catch (\Throwable $error) {
            Log::error('Fonnte send exception', [
                'message' => $error->getMessage(),
            ]);

            return [
                'sent' => false,
                'reason' => $error->getMessage(),
            ];
        }
    }

    private function defaultCarouselSlides(): array
    {
        return [
            [
                'title' => 'Testing Picture 01',
                'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore.',
                'image_path' => '',
                'solid_class' => 'slide-solid-1',
            ],
            [
                'title' => 'Testing Picture 02',
                'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut enim ad minim veniam, quis nostrud exercitation.',
                'image_path' => '',
                'solid_class' => 'slide-solid-2',
            ],
            [
                'title' => 'Testing Picture 03',
                'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis aute irure dolor in reprehenderit in voluptate.',
                'image_path' => '',
                'solid_class' => 'slide-solid-3',
            ],
        ];
    }

    private function defaultAboutContent(?string $locale = null): array
    {
        $activeLocale = strtolower(trim((string) ($locale ?? app()->getLocale())));
        if ($activeLocale === 'id') {
            return [
                'title' => 'Konsultasi personal color profesional dalam suasana yang tenang dan minimalis.',
                'description' => 'Kami fokus pada analisis yang akurat, rekomendasi yang praktis, dan pengalaman konsultasi premium. Setiap sesi disusun agar Anda percaya diri memilih warna untuk pakaian, makeup, dan aksesori.',
            ];
        }

        return [
            'title' => 'Professional personal color consultancy in a calm, minimal setting.',
            'description' => 'We focus on accurate analysis, practical recommendations, and a premium consultation experience. Every session is structured so you can confidently choose colors for clothing, makeup, and accessories.',
        ];
    }

    private function aboutContent(): array
    {
        $locale = strtolower(trim((string) app()->getLocale()));
        $defaults = $this->defaultAboutContent($locale);
        $defaultsEn = $this->defaultAboutContent('en');
        $file = storage_path('app/about-content.json');

        if (!is_file($file)) {
            return $defaults;
        }

        $raw = file_get_contents($file);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        $title = trim((string) (
            ($locale === 'id' ? ($decoded['title_id'] ?? null) : ($decoded['title_en'] ?? null))
                ?? ($decoded['title'] ?? '')
        ));
        $description = trim((string) (
            ($locale === 'id' ? ($decoded['description_id'] ?? null) : ($decoded['description_en'] ?? null))
                ?? ($decoded['description'] ?? '')
        ));

        if (
            $locale === 'id'
            && $title === $defaultsEn['title']
            && $description === $defaultsEn['description']
        ) {
            return $defaults;
        }

        return [
            'title' => $title !== '' ? $title : $defaults['title'],
            'description' => $description !== '' ? $description : $defaults['description'],
        ];
    }

    private function saveAboutContent(array $about): void
    {
        $payload = [
            'title' => trim((string) ($about['title'] ?? '')),
            'description' => trim((string) ($about['description'] ?? '')),
        ];

        $file = storage_path('app/about-content.json');
        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function decorateCarouselSlides(array $slides): array
    {
        $palette = ['slide-solid-1', 'slide-solid-2', 'slide-solid-3'];

        $normalized = [];
        foreach (array_values($slides) as $index => $slide) {
            $path = trim((string) ($slide['image_path'] ?? ''));
            $title = trim((string) ($slide['title'] ?? ''));
            $description = trim((string) ($slide['description'] ?? ''));

            $normalized[] = [
                'title' => $title !== '' ? $title : 'Slide ' . ($index + 1),
                'description' => $description !== '' ? $description : '',
                'image_path' => $path,
                'image_url' => $path !== '' ? asset(ltrim($path, '/')) : '',
                'solid_class' => $palette[$index % count($palette)],
            ];
        }

        return $normalized;
    }

    private function carouselSlides(): array
    {
        $defaults = $this->decorateCarouselSlides($this->defaultCarouselSlides());
        $rows = DB::connection('mysql')
            ->table('neoura.carousel')
            ->select('file', 'title', 'description')
            ->orderBy('caraouselid')
            ->get();
        if ($rows->isEmpty()) {
            $legacyFile = storage_path('app/carousel-slides.json');
            if (is_file($legacyFile)) {
                $raw = file_get_contents($legacyFile);
                $decoded = json_decode((string) $raw, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $legacySlides = array_map(function (array $slide) {
                        return [
                            'title' => trim((string) ($slide['title'] ?? '')),
                            'description' => trim((string) ($slide['description'] ?? '')),
                            'image_path' => trim((string) ($slide['image_path'] ?? '')),
                        ];
                    }, $decoded);

                    $this->saveCarouselSlides($legacySlides);

                    return $this->decorateCarouselSlides($legacySlides);
                }
            }

            return $defaults;
        }
        $slides = $rows->map(function ($row) {
            return [
                'title' => trim((string) ($row->title ?? '')),
                'description' => trim((string) ($row->description ?? '')),
                'image_path' => trim((string) ($row->file ?? '')),
            ];
        })->all();

        return $this->decorateCarouselSlides($slides);
    }

    private function saveCarouselSlides(array $slides): void
    {
        $payload = collect($slides)->map(function (array $slide) {
            return [
                'file' => trim((string) ($slide['image_path'] ?? '')),
                'title' => trim((string) ($slide['title'] ?? '')),
                'description' => trim((string) ($slide['description'] ?? '')),
            ];
        })->values()->all();

        DB::connection('mysql')->transaction(function () use ($payload) {
            DB::connection('mysql')->table('neoura.carousel')->delete();
            if (!empty($payload)) {
                DB::connection('mysql')->table('neoura.carousel')->insert($payload);
            }
        });
    }

    private function defaultCarouselAutoplayMs(): int
    {
        return 5000;
    }

    private function normalizeCarouselAutoplayMs($value): int
    {
        $number = (int) $value;
        if ($number < 500) {
            return 500;
        }
        if ($number > 60000) {
            return 60000;
        }

        return $number;
    }

    private function carouselSettings(): array
    {
        $fallbackMs = $this->defaultCarouselAutoplayMs();
        $file = storage_path('app/carousel-settings.json');
        if (!is_file($file)) {
            return [
                'autoplay_ms' => $fallbackMs,
            ];
        }

        $raw = file_get_contents($file);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                'autoplay_ms' => $fallbackMs,
            ];
        }

        return [
            'autoplay_ms' => $this->normalizeCarouselAutoplayMs($decoded['autoplay_ms'] ?? $fallbackMs),
        ];
    }

    private function saveCarouselSettings(array $settings): void
    {
        $payload = [
            'autoplay_ms' => $this->normalizeCarouselAutoplayMs($settings['autoplay_ms'] ?? $this->defaultCarouselAutoplayMs()),
        ];

        $file = storage_path('app/carousel-settings.json');
        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function defaultBrandNameVisible(): bool
    {
        return true;
    }

    private function normalizeBrandNameVisible($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $text = strtolower(trim((string) $value));
        if ($text === '') {
            return $this->defaultBrandNameVisible();
        }

        if (in_array($text, ['1', 'true', 'yes', 'show', 'visible'], true)) {
            return true;
        }

        if (in_array($text, ['0', 'false', 'no', 'hide', 'hidden'], true)) {
            return false;
        }

        return $this->defaultBrandNameVisible();
    }

    private function brandDisplaySettings(): array
    {
        $fallback = $this->defaultBrandNameVisible();
        $file = storage_path('app/brand-display-settings.json');
        if (!is_file($file)) {
            return [
                'show_name_in_brand' => $fallback,
            ];
        }

        $raw = file_get_contents($file);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                'show_name_in_brand' => $fallback,
            ];
        }

        return [
            'show_name_in_brand' => $this->normalizeBrandNameVisible($decoded['show_name_in_brand'] ?? $fallback),
        ];
    }

    private function saveBrandDisplaySettings(array $settings): void
    {
        $payload = [
            'show_name_in_brand' => $this->normalizeBrandNameVisible(
                $settings['show_name_in_brand'] ?? $this->defaultBrandNameVisible()
            ),
        ];

        $file = storage_path('app/brand-display-settings.json');
        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function sidebarPermissionFile(): string
    {
        return storage_path('app/sidebar-permissions.json');
    }

    private function sidebarPermissionMenuRows(): array
    {
        return [
            [
                'key' => 'service',
                'label' => 'Service',
                'description' => 'Access Service management page.',
            ],
            [
                'key' => 'payment',
                'label' => 'Payment Validation',
                'description' => 'Access Payment Validation page.',
            ],
            [
                'key' => 'user',
                'label' => 'User Data',
                'description' => 'Access User Data page.',
            ],
            [
                'key' => 'activity',
                'label' => 'Activity Log',
                'description' => 'Show Activity Log menu item.',
            ],
            [
                'key' => 'financial',
                'label' => 'Financial Report',
                'description' => 'Access Financial Report page.',
            ],
            [
                'key' => 'backup',
                'label' => 'Backup Database',
                'description' => 'Access Backup Database page.',
            ],
            [
                'key' => 'recycle',
                'label' => 'Recycle Bin',
                'description' => 'Access Recycle Bin page.',
            ],
            [
                'key' => 'permission',
                'label' => 'Permission',
                'description' => 'Access Sidebar Permission page.',
            ],
            [
                'key' => 'setting',
                'label' => 'Setting',
                'description' => 'Access Website Setting page.',
            ],
        ];
    }

    private function sidebarPermissionMenuKeys(): array
    {
        return collect($this->sidebarPermissionMenuRows())
            ->map(fn($row) => (string) ($row['key'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    private function defaultSidebarPermissions(): array
    {
        return [
            'admin' => [
                'service' => true,
                'payment' => true,
                'user' => true,
                'activity' => true,
                'financial' => true,
                'backup' => false,
                'recycle' => false,
                'permission' => false,
                'setting' => false,
            ],
            'manager' => [
                'service' => true,
                'payment' => true,
                'user' => true,
                'user' => false,
                'activity' => true,
                'financial' => true,
                'backup' => false,
                'recycle' => false,
                'permission' => false,
                'setting' => false,
            ],
            'superadmin' => [
                'service' => true,
                'payment' => true,
                'user' => true,
                'activity' => true,
                'financial' => true,
                'backup' => true,
                'recycle' => true,
                'permission' => true,
                'setting' => true,
            ],
        ];
    }

    private function normalizeSidebarPermissionValue(mixed $value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $fallback;
    }

    private function normalizeSidebarPermissions(array $settings): array
    {
        $defaults = $this->defaultSidebarPermissions();
        $keys = $this->sidebarPermissionMenuKeys();
        $normalized = [];

        foreach (['admin', 'manager', 'superadmin'] as $level) {
            $levelSettings = is_array($settings[$level] ?? null) ? $settings[$level] : [];
            $levelDefaults = is_array($defaults[$level] ?? null) ? $defaults[$level] : [];
            $levelResult = [];

            foreach ($keys as $key) {
                $fallback = (bool) ($levelDefaults[$key] ?? false);
                $levelResult[$key] = $this->normalizeSidebarPermissionValue($levelSettings[$key] ?? $fallback, $fallback);
            }

            $normalized[$level] = $levelResult;
        }

        return $normalized;
    }

    private function sidebarPermissions(): array
    {
        $file = $this->sidebarPermissionFile();
        if (!is_file($file)) {
            return $this->defaultSidebarPermissions();
        }

        $raw = file_get_contents($file);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return $this->defaultSidebarPermissions();
        }

        return $this->normalizeSidebarPermissions($decoded);
    }

    private function saveSidebarPermissions(array $settings): void
    {
        $normalized = $this->normalizeSidebarPermissions($settings);
        file_put_contents(
            $this->sidebarPermissionFile(),
            json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function sidebarPermissionMapForAuth(?array $adminAuth): array
    {
        $defaults = $this->defaultSidebarPermissions();
        $keys = $this->sidebarPermissionMenuKeys();
        $level = strtolower(trim((string) ($adminAuth['levelname'] ?? '')));
        $settings = $this->sidebarPermissions();
        $source = is_array($settings[$level] ?? null) ? $settings[$level] : ($defaults[$level] ?? $defaults['admin']);

        $map = [];
        foreach ($keys as $key) {
            $map[$key] = (bool) ($source[$key] ?? false);
        }

        return $map;
    }

    private function canAccessSidebarMenu(?array $adminAuth, string $menuKey): bool
    {
        if (in_array($menuKey, ['home', 'account', 'logout'], true)) {
            return true;
        }

        if (!$this->canSeeAdminMenu($adminAuth)) {
            return false;
        }

        $menu = strtolower(trim($menuKey));
        if (!in_array($menu, $this->sidebarPermissionMenuKeys(), true)) {
            return true;
        }

        $map = $this->sidebarPermissionMapForAuth($adminAuth);
        return (bool) ($map[$menu] ?? false);
    }

    private function sidebarPermissionDenied(Request $request, string $message = 'You do not have access to this menu.')
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], 403);
        }

        return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
    }

    private function canSeeAdminMenu(?array $adminAuth): bool
    {
        if (empty($adminAuth['levelname'])) {
            return false;
        }

        $level = strtolower(trim((string) $adminAuth['levelname']));
        return in_array($level, ['admin', 'owner', 'manager', 'superadmin'], true);
    }

    private function isSuperAdmin(?array $adminAuth): bool
    {
        if (empty($adminAuth['levelname'])) {
            return false;
        }

        return strtolower(trim((string) $adminAuth['levelname'])) === 'superadmin';
    }

    private function resolveLogoUrl(?string $logoPath): string
    {
        $logoPath = trim((string) $logoPath);

        if ($logoPath === '') {
            return asset('images/neora-logo.svg');
        }

        if (Str::startsWith($logoPath, ['http://', 'https://'])) {
            return $logoPath;
        }

        return asset(ltrim($logoPath, '/'));
    }

    private function defaultThemeSoftColor(): string
    {
        return '#F2D5C4';
    }

    private function defaultThemeBoldColor(): string
    {
        return '#C69278';
    }

    private function normalizeHexColor(?string $hex, string $fallback): string
    {
        $value = strtoupper(trim((string) $hex));
        if (preg_match('/^#[0-9A-F]{6}$/', $value) === 1) {
            return $value;
        }

        return strtoupper($fallback);
    }

    private function colorShift(string $hex, int $amount): string
    {
        $value = ltrim($this->normalizeHexColor($hex, $this->defaultThemeSoftColor()), '#');
        $r = max(0, min(255, hexdec(substr($value, 0, 2)) + $amount));
        $g = max(0, min(255, hexdec(substr($value, 2, 2)) + $amount));
        $b = max(0, min(255, hexdec(substr($value, 4, 2)) + $amount));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    private function themeSettings(): array
    {
        $fallbackSoft = $this->defaultThemeSoftColor();
        $fallbackBold = $this->defaultThemeBoldColor();
        $system = DB::connection('mysql')
            ->table('neoura.system')
            ->orderBy('systemid')
            ->first();

        if (!$system) {
            return [
                'accent_soft' => $fallbackSoft,
                'accent_bold' => $fallbackBold,
            ];
        }

        $soft = $this->normalizeHexColor(
            $system->color1 ?? null,
            $fallbackSoft
        );

        $boldFallback = $this->colorShift($soft, -30);
        $bold = $this->normalizeHexColor($system->color2 ?? null, $boldFallback);

        return [
            'accent_soft' => $soft,
            'accent_bold' => $bold,
        ];
    }

    private function websiteSettings(): array
    {
        $theme = $this->themeSettings();
        $brandDisplay = $this->brandDisplaySettings();
        $accentSoftColor = $theme['accent_soft'];
        $accentBoldColor = $theme['accent_bold'];

        $system = DB::connection('mysql')
            ->table('neoura.system')
            ->orderBy('systemid')
            ->first();

        if (!$system) {
            return [
                'systemid' => null,
                'name' => 'Neora Color Studio',
                'logo_path' => 'images/neora-logo.svg',
                'logo_url' => asset('images/neora-logo.svg'),
                'phone' => $this->defaultContact['phone'],
                'instagram' => $this->defaultContact['instagram'],
                'address' => $this->defaultContact['address'],
                'maps' => $this->defaultContact['maps'],
                'bank_name' => '',
                'bank_number' => '',
                'bank_accounts' => [],
                'theme_color_soft' => $accentSoftColor,
                'theme_color_bold' => $accentBoldColor,
                'theme_color' => $accentSoftColor,
                'theme_color_strong' => $accentBoldColor,
                'show_name_in_brand' => (bool) ($brandDisplay['show_name_in_brand'] ?? $this->defaultBrandNameVisible()),
            ];
        }

        $banks = DB::connection('mysql')
            ->table('neoura.bank')
            ->where('systemid', $system->systemid)
            ->orderBy('bankid')
            ->get();
        $firstBank = $banks->first();

        $address = (string) ($system->systemaddress ?? '');
        $mapsQuery = trim($address) !== '' ? urlencode($address) : 'Kemang+Raya+18+Jakarta';

        return [
            'systemid' => $system->systemid,
            'name' => (string) ($system->systemname ?? 'Neora Color Studio'),
            'logo_path' => (string) ($system->systemlogo ?? 'images/neora-logo.svg'),
            'logo_url' => $this->resolveLogoUrl($system->systemlogo ?? 'images/neora-logo.svg'),
            'phone' => (string) ($system->systemcontact ?? $this->defaultContact['phone']),
            'instagram' => (string) ($system->system_insta ?? $this->defaultContact['instagram']),
            'address' => $address !== '' ? $address : $this->defaultContact['address'],
            'maps' => 'https://maps.google.com/?q=' . $mapsQuery,
            'bank_name' => (string) ($firstBank->bankname ?? ''),
            'bank_number' => (string) ($firstBank->banknumber ?? ''),
            'bank_accounts' => $banks->map(function ($bank) {
                return [
                    'bankname' => (string) ($bank->bankname ?? ''),
                    'banknumber' => (string) ($bank->banknumber ?? ''),
                ];
            })->all(),
            'theme_color_soft' => $accentSoftColor,
            'theme_color_bold' => $accentBoldColor,
            'theme_color' => $accentSoftColor,
            'theme_color_strong' => $accentBoldColor,
            'show_name_in_brand' => (bool) ($brandDisplay['show_name_in_brand'] ?? $this->defaultBrandNameVisible()),
        ];
    }

    public function home(Request $request)
    {
        $homeServices = $this->servicePageRows();
        $adminAuth = $request->session()->get('admin_auth');
        $showAdminMenu = $this->canSeeAdminMenu($adminAuth);
        $website = $this->websiteSettings();
        $carouselSlides = $this->carouselSlides();
        $carouselSettings = $this->carouselSettings();
        $aboutContent = $this->aboutContent();
        $sidebarServices = $this->sidebarServices();
        $bookingLookupResult = $request->session()->get('booking_lookup_result');
        $bookingLookupError = (string) $request->session()->get('booking_lookup_error', '');

        $data = [
            'title' => $website['name'] . ' | Personal Color Analysis',
            'contact' => [
                'phone' => $website['phone'],
                'instagram' => $website['instagram'],
                'address' => $website['address'],
                'maps' => $website['maps'],
            ],
            'homeServices' => $homeServices,
            'adminAuth' => $adminAuth,
            'showAdminMenu' => $showAdminMenu,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'website' => $website,
            'carouselSlides' => $carouselSlides,
            'carouselAutoplayMs' => (int) ($carouselSettings['autoplay_ms'] ?? $this->defaultCarouselAutoplayMs()),
            'aboutContent' => $aboutContent,
            'sidebarServices' => $sidebarServices,
            'bookingLookupResult' => is_array($bookingLookupResult) ? $bookingLookupResult : null,
            'bookingLookupError' => $bookingLookupError,
        ];

        $this->renderParts([
            'all.header',
            'all.menu',
            'all.home',
            'all.footer',
        ], $data);
    }

    public function basicSession()
    {
        $service = $this->servicePackages()['Basic Session'];
        $service['bookingRoute'] = route('booking', ['plan' => 'Basic Session']);
        $website = $this->websiteSettings();

        $data = [
            'title' => 'Basic Session | ' . $website['name'],
            'contact' => [
                'phone' => $website['phone'],
                'instagram' => $website['instagram'],
                'address' => $website['address'],
                'maps' => $website['maps'],
            ],
            'service' => $service,
            'website' => $website,
        ];

        $this->renderParts(['all.header', 'all.navbar', 'all.service-detail', 'all.footer'], $data);
    }

    public function exclusiveSession()
    {
        $service = $this->servicePackages()['Exclusive Session'];
        $service['bookingRoute'] = route('booking', ['plan' => 'Exclusive Session']);
        $website = $this->websiteSettings();

        $data = [
            'title' => 'Exclusive Session | ' . $website['name'],
            'contact' => [
                'phone' => $website['phone'],
                'instagram' => $website['instagram'],
                'address' => $website['address'],
                'maps' => $website['maps'],
            ],
            'service' => $service,
            'website' => $website,
        ];

        $this->renderParts(['all.header', 'all.navbar', 'all.service-detail', 'all.footer'], $data);
    }

    public function luxeSession()
    {
        $service = $this->servicePackages()['Luxe Session'];
        $service['bookingRoute'] = route('booking', ['plan' => 'Luxe Session']);
        $website = $this->websiteSettings();

        $data = [
            'title' => 'Luxe Session | ' . $website['name'],
            'contact' => [
                'phone' => $website['phone'],
                'instagram' => $website['instagram'],
                'address' => $website['address'],
                'maps' => $website['maps'],
            ],
            'service' => $service,
            'website' => $website,
        ];

        $this->renderParts(['all.header', 'all.navbar', 'all.service-detail', 'all.footer'], $data);
    }

    public function booking(Request $request)
    {
        $packages = $this->bookingPackagesFromDatabase();
        $selectedPlan = $request->query('plan', 'Basic Session');
        $bookingPackage = $packages[$selectedPlan] ?? reset($packages);
        $selectedPlan = (string) ($bookingPackage['name'] ?? $selectedPlan);
        $bookingDurationMinutes = $this->serviceDurationMinutes((string) ($bookingPackage['duration'] ?? '60'));
        $bookingDate = (string) $request->query('date', now()->toDateString());
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate) !== 1) {
            $bookingDate = now()->toDateString();
        }

        $bookingRecords = $this->bookedSlots();
        $website = $this->websiteSettings();

        $data = [
            'title' => 'Booking | ' . $website['name'],
            'pageScript' => 'booking.js',
            'contact' => [
                'phone' => $website['phone'],
                'instagram' => $website['instagram'],
                'address' => $website['address'],
                'maps' => $website['maps'],
            ],
            'selectedPlan' => $selectedPlan,
            'bookingPackage' => $bookingPackage,
            'bookingDate' => $bookingDate,
            'bookingDurationMinutes' => $bookingDurationMinutes,
            'bookingTimeOptions' => $this->timeOptions(),
            'bookingSchedule' => [
                'open' => '10:00',
                'close' => '22:00',
                'step' => 30,
                'records' => array_map(function (array $booking) {
                    return [
                        'booking_date' => (string) ($booking['booking_date'] ?? ''),
                        'start_time' => (string) ($booking['start_time'] ?? ''),
                        'end_time' => (string) ($booking['end_time'] ?? ''),
                    ];
                }, $bookingRecords),
            ],
            'website' => $website,
        ];

        $this->renderParts(['all.header', 'all.navbar', 'all.booking', 'all.footer'], $data);
    }

    public function bookingSubmit(Request $request)
    {
        $packages = $this->bookingPackagesFromDatabase();
        $selectedPlan = (string) $request->query('plan', '');
        if ($selectedPlan === '' || !array_key_exists($selectedPlan, $packages)) {
            return redirect()->route('booking')->withErrors(['booking' => 'Selected package is not available.']);
        }

        $bookingPackage = $packages[$selectedPlan];
        $serviceId = $this->resolveServiceId($bookingPackage);
        if ($serviceId <= 0) {
            return back()->withErrors(['booking' => 'Service ID is invalid. Please contact admin.'])->withInput();
        }

        $durationMinutes = $this->serviceDurationMinutes((string) ($bookingPackage['duration'] ?? '60'));
        $website = $this->websiteSettings();
        $studioName = (string) ($website['name'] ?? 'Neora Color Studio');
        $bookingCode = $this->generateBookingCode();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'regex:/^\+?[0-9\s-]{8,20}$/'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'time_slot' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'payment_bank' => ['required', 'string', 'max:255'],
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $openMinutes = $this->toMinutes('10:00');
        $closeMinutes = $this->toMinutes('22:00');
        $startMinutes = $this->toMinutes($validated['time_slot']);
        $endMinutes = $startMinutes + $durationMinutes;
        $todayDate = now()->toDateString();
        $currentMinutes = ((int) now()->format('H') * 60) + (int) now()->format('i');

        if ($validated['booking_date'] === $todayDate && $startMinutes < $currentMinutes) {
            return back()
                ->withErrors(['time_slot' => 'Selected time has passed. Please choose a future time slot.'])
                ->withInput();
        }

        if ($startMinutes < $openMinutes || $endMinutes > $closeMinutes) {
            return back()
                ->withErrors(['time_slot' => 'Selected time is outside operational hours for this service duration.'])
                ->withInput();
        }

        $existingSlots = DB::connection('mysql')
            ->table('neoura.timeslot')
            ->select('start_time', 'end_time')
            ->where('date', $validated['booking_date'])
            ->where('is_booked', 1)
            ->get();

        foreach ($existingSlots as $slot) {
            $existingStart = $this->toMinutes(substr((string) ($slot->start_time ?? ''), 0, 5));
            $existingEnd = $this->toMinutes(substr((string) ($slot->end_time ?? ''), 0, 5));
            if ($existingStart < 0 || $existingEnd <= $existingStart) {
                continue;
            }

            if ($this->isOverlap($startMinutes, $endMinutes, $existingStart, $existingEnd)) {
                return back()
                    ->withErrors(['time_slot' => 'This time slot is already full. Please choose another time.'])
                    ->withInput();
            }
        }

        $proofPath = '';
        if ($request->hasFile('payment_proof')) {
            $file = $request->file('payment_proof');
            $directory = public_path('images/booking-proof');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = 'proof-' . time() . '-' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $filename);
            $proofPath = 'images/booking-proof/' . $filename;
        }

        DB::connection('mysql')->transaction(function () use (
            $validated,
            $serviceId,
            $bookingCode,
            $proofPath,
            $endMinutes
        ) {
            $slotId = DB::connection('mysql')
                ->table('neoura.timeslot')
                ->insertGetId([
                    'serviceid' => $serviceId,
                    'date' => $validated['booking_date'],
                    'start_time' => $validated['time_slot'] . ':00',
                    'end_time' => $this->toHm($endMinutes) . ':00',
                    'is_booked' => 1,
                ]);

            $bookingId = DB::connection('mysql')
                ->table('neoura.booking')
                ->insertGetId([
                    'name' => $validated['full_name'],
                    'email' => $validated['email'],
                    'phonenumber' => $validated['phone'],
                    'serviceid' => $serviceId,
                    'slotid' => $slotId,
                    'status' => 'Pending',
                    'bookingcode' => $bookingCode,
                ]);

            DB::connection('mysql')
                ->table('neoura.payment')
                ->insert([
                    'bookingid' => $bookingId,
                    'paymentdate' => now()->toDateTimeString(),
                    'bank' => $validated['payment_bank'],
                    'proof' => $proofPath,
                ]);
        });

        $startTime = $validated['time_slot'];
        $endTime = $this->toHm($endMinutes);
        $activeLocale = strtolower(trim((string) app()->getLocale()));
        $messageText = $this->whatsappMessageText(
            $validated['full_name'],
            $selectedPlan,
            $bookingCode,
            $validated['booking_date'],
            $startTime,
            $endTime,
            $studioName,
            $activeLocale
        );
        $whatsAppResult = $this->sendWhatsAppMessage($validated['phone'], $messageText);
        $manualWhatsAppLink = 'https://wa.me/' . $this->normalizeWhatsAppNumber($validated['phone']) . '?text=' . rawurlencode($messageText);
        $emailText = $this->bookingEmailText(
            $validated['full_name'],
            $selectedPlan,
            $bookingCode,
            $validated['booking_date'],
            $startTime,
            $endTime,
            $studioName,
            $activeLocale
        );
        $emailResult = $this->sendBookingEmail(
            $validated['email'],
            $this->bookingEmailSubject($bookingCode, $activeLocale),
            $emailText
        );

        return redirect()
            ->route('booking', ['plan' => $selectedPlan, 'date' => $validated['booking_date']])
            ->with('status', 'Booking submitted successfully. Your payment is pending validation.')
            ->with('booking_code', $bookingCode)
            ->with('whatsapp_sent', (bool) ($whatsAppResult['sent'] ?? false))
            ->with('whatsapp_error', (string) ($whatsAppResult['reason'] ?? ''))
            ->with('whatsapp_link', $manualWhatsAppLink)
            ->with('email_sent', (bool) ($emailResult['sent'] ?? false))
            ->with('email_error', (string) ($emailResult['reason'] ?? ''));
    }

    public function bookingStatusLookup(Request $request)
    {
        $validated = $request->validate([
            'booking_code' => ['required', 'string', 'max:255'],
            'phone_last4' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $bookingCode = strtoupper(trim((string) $validated['booking_code']));
        $phoneLast4 = (string) $validated['phone_last4'];

        $row = DB::connection('mysql')
            ->table('neoura.booking as b')
            ->join('neoura.service as s', 's.serviceid', '=', 'b.serviceid')
            ->join('neoura.timeslot as t', 't.slotid', '=', 'b.slotid')
            ->select(
                'b.bookingcode',
                'b.name',
                'b.phonenumber',
                'b.status',
                's.name as service_name',
                't.date',
                't.start_time',
                't.end_time'
            )
            ->where('b.bookingcode', $bookingCode)
            ->first();

        if (!$row) {
            return redirect()
                ->to(route('home') . '#booking-status')
                ->with('booking_lookup_error', 'Booking code not found.')
                ->withInput();
        }

        $digits = preg_replace('/\D+/', '', (string) ($row->phonenumber ?? '')) ?? '';
        $actualLast4 = substr($digits, -4);
        if ($actualLast4 !== $phoneLast4) {
            return redirect()
                ->to(route('home') . '#booking-status')
                ->with('booking_lookup_error', 'Last 4 digits of phone number are not valid.')
                ->withInput();
        }

        return redirect()->to(route('home') . '#booking-status')
            ->with('booking_lookup_result', [
                'service_name' => (string) ($row->service_name ?? '-'),
                'date' => (string) ($row->date ?? '-'),
                'start_time' => substr((string) ($row->start_time ?? '-'), 0, 5),
                'end_time' => substr((string) ($row->end_time ?? '-'), 0, 5),
                'name' => (string) ($row->name ?? '-'),
                'status' => (string) ($row->status ?? '-'),
                'booking_code' => (string) ($row->bookingcode ?? '-'),
            ]);
    }

    public function adminService(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'service')) {
            return $this->sidebarPermissionDenied($request);
        }

        $website = $this->websiteSettings();
        $sidebarServices = $this->sidebarServices();
        $allServiceRows = $this->servicePageRows();
        $page = (int) $request->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $perPage = 20;
        $totalRows = count($allServiceRows);
        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }
        $offset = ($page - 1) * $perPage;
        $serviceRows = array_values(array_slice($allServiceRows, $offset, $perPage));
        $countOnPage = count($serviceRows);
        $servicePagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRows,
            'last_page' => $lastPage,
            'from' => $totalRows > 0 ? ($offset + 1) : 0,
            'to' => $totalRows > 0 ? min($offset + $countOnPage, $totalRows) : 0,
        ];

        if ($request->expectsJson() || $request->ajax()) {
            $html = view('admin.partials.service-list', [
                'serviceRows' => $serviceRows,
                'servicePagination' => $servicePagination,
            ])->render();

            return response()->json([
                'status' => 'ok',
                'html' => $html,
                'pagination' => $servicePagination,
            ]);
        }

        $data = [
            'title' => 'Service | ' . $website['name'],
            'adminAuth' => $adminAuth,
            'showAdminMenu' => true,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'sidebarServices' => $sidebarServices,
            'serviceRows' => $serviceRows,
            'servicePagination' => $servicePagination,
            'website' => $website,
            'pageScript' => 'admin-service.js',
        ];

        $this->renderParts(['all.header', 'all.menu', 'admin.service', 'all.footer'], $data);
    }

    public function exportServiceExcel(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'service')) {
            return $this->sidebarPermissionDenied($request);
        }

        $rows = $this->servicePageRows();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Services');
        $sheet->fromArray(['serviceid', 'name', 'detail', 'duration', 'price', 'descriptions'], null, 'A1');

        $cursor = 2;
        foreach ($rows as $row) {
            $descriptions = is_array($row['descriptions'] ?? null) ? $row['descriptions'] : [];
            $sheet->fromArray([
                (int) ($row['serviceid'] ?? 0),
                (string) ($row['name'] ?? ''),
                (string) ($row['detail'] ?? ''),
                (string) ($row['duration'] ?? ''),
                (string) ($row['price'] ?? ''),
                implode(' | ', array_map(fn($item) => trim((string) $item), $descriptions)),
            ], null, 'A' . $cursor);
            $cursor++;
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filePath = tempnam(sys_get_temp_dir(), 'service_export_');
        (new Xlsx($spreadsheet))->save($filePath);

        return response()->download(
            $filePath,
            'service-data-' . date('Ymd-His') . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function importServiceExcel(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'service')) {
            return $this->sidebarPermissionDenied($request);
        }

        $validated = $request->validate([
            'service_excel' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $file = $validated['service_excel'] ?? null;
        if (!$file) {
            return redirect()->route('admin.service')->withErrors(['service' => 'Excel file is required.']);
        }

        $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();
        $highestRow = (int) $sheet->getHighestDataRow();

        $payload = [];
        $rowErrors = [];
        for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
            $serviceIdRaw = trim((string) $sheet->getCell('A' . $rowNum)->getFormattedValue());
            $name = trim((string) $sheet->getCell('B' . $rowNum)->getFormattedValue());
            $detail = trim((string) $sheet->getCell('C' . $rowNum)->getFormattedValue());
            $duration = trim((string) $sheet->getCell('D' . $rowNum)->getFormattedValue());
            $price = trim((string) $sheet->getCell('E' . $rowNum)->getFormattedValue());
            $descriptionsRaw = trim((string) $sheet->getCell('F' . $rowNum)->getFormattedValue());

            if ($serviceIdRaw === '' && $name === '' && $detail === '' && $duration === '' && $price === '' && $descriptionsRaw === '') {
                continue;
            }

            if ($name === '' || $detail === '' || $duration === '' || $price === '') {
                $rowErrors[] = 'Row ' . $rowNum . ' is invalid. Name, detail, duration, and price are required.';
                continue;
            }

            if (mb_strlen($name) > 255 || mb_strlen($detail) > 255 || mb_strlen($duration) > 255 || mb_strlen($price) > 255) {
                $rowErrors[] = 'Row ' . $rowNum . ' exceeds max length (255 chars).';
                continue;
            }

            $descriptionRows = preg_split('/\r\n|\r|\n|\|/', $descriptionsRaw) ?: [];
            $descriptions = collect($descriptionRows)
                ->map(fn($line) => trim((string) $line))
                ->filter()
                ->values()
                ->all();

            $normalizedServiceId = 0;
            if ($serviceIdRaw !== '' && is_numeric($serviceIdRaw)) {
                $normalizedServiceId = (int) round((float) $serviceIdRaw);
            }

            $payload[] = [
                'serviceid' => max(0, $normalizedServiceId),
                'name' => $name,
                'detail' => $detail,
                'duration' => $duration,
                'price' => $price,
                'descriptions' => $descriptions,
            ];
        }

        if (!empty($rowErrors)) {
            return redirect()->route('admin.service')->withErrors(['service' => implode(' ', $rowErrors)]);
        }
        if (empty($payload)) {
            return redirect()->route('admin.service')->withErrors(['service' => 'No valid data found in the uploaded Excel file.']);
        }

        $created = 0;
        $updated = 0;
        DB::connection('mysql')->transaction(function () use ($payload, &$created, &$updated) {
            foreach ($payload as $row) {
                $serviceId = (int) ($row['serviceid'] ?? 0);
                $exists = null;
                if ($serviceId > 0) {
                    $exists = DB::connection('mysql')
                        ->table('neoura.service')
                        ->select('serviceid')
                        ->where('serviceid', $serviceId)
                        ->first();
                }

                if ($exists) {
                    DB::connection('mysql')
                        ->table('neoura.service')
                        ->where('serviceid', $serviceId)
                        ->update([
                            'name' => $row['name'],
                            'detail' => $row['detail'],
                            'duration' => $row['duration'],
                            'price' => $row['price'],
                        ]);
                    $updated++;
                } else {
                    $serviceId = (int) DB::connection('mysql')
                        ->table('neoura.service')
                        ->insertGetId([
                            'name' => $row['name'],
                            'detail' => $row['detail'],
                            'duration' => $row['duration'],
                            'price' => $row['price'],
                        ]);
                    $created++;
                }

                DB::connection('mysql')
                    ->table('neoura.description')
                    ->where('serviceid', $serviceId)
                    ->delete();

                $descriptions = is_array($row['descriptions'] ?? null) ? $row['descriptions'] : [];
                if (!empty($descriptions)) {
                    $descriptionInsert = collect($descriptions)
                        ->map(fn($description) => [
                            'name' => (string) $description,
                            'serviceid' => $serviceId,
                        ])
                        ->all();

                    DB::connection('mysql')
                        ->table('neoura.description')
                        ->insert($descriptionInsert);
                }
            }
        });

        return redirect()->route('admin.service')
            ->with('status', 'Service import completed. Created: ' . $created . ', Updated: ' . $updated . '.');
    }

    public function adminServiceStore(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'service')) {
            return $this->sidebarPermissionDenied($request);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'detail' => ['required', 'string', 'max:255'],
            'duration' => ['required', 'string', 'max:255'],
            'price' => ['required', 'string', 'max:255'],
            'descriptions_text' => ['nullable', 'string', 'max:10000'],
        ]);

        $normalizedDescriptionsText = str_replace(['\\r\\n', '\\n', '\\r'], "\n", (string) ($validated['descriptions_text'] ?? ''));
        $lines = preg_split('/\r\n|\r|\n/', $normalizedDescriptionsText) ?: [];
        $descriptions = collect($lines)
            ->map(fn($line) => trim((string) $line))
            ->filter()
            ->values();

        $request->attributes->set('activity_action_override', 'Create Service');
        $request->attributes->set(
            'activity_detail_override',
            'Create Service ' . trim((string) ($validated['name'] ?? '-'))
            . '; duration ' . trim((string) ($validated['duration'] ?? '-'))
            . '; price ' . trim((string) ($validated['price'] ?? '-'))
        );

        DB::connection('mysql')->transaction(function () use ($validated, $descriptions) {
            $serviceId = DB::connection('mysql')
                ->table('neoura.service')
                ->insertGetId([
                    'name' => $validated['name'],
                    'detail' => $validated['detail'],
                    'duration' => $validated['duration'],
                    'price' => $validated['price'],
                ]);

            if ($descriptions->isNotEmpty()) {
                $rows = $descriptions->map(function ($description) use ($serviceId) {
                    return [
                        'name' => $description,
                        'serviceid' => $serviceId,
                    ];
                })->all();

                DB::connection('mysql')
                    ->table('neoura.description')
                    ->insert($rows);
            }
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Service added.',
            ]);
        }

        return redirect()->route('admin.service')->with('status', 'Service added.');
    }

    public function adminServiceUpdate(Request $request, int $serviceid)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'service')) {
            return $this->sidebarPermissionDenied($request);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'detail' => ['required', 'string', 'max:255'],
            'duration' => ['required', 'string', 'max:255'],
            'price' => ['required', 'string', 'max:255'],
            'descriptions_text' => ['nullable', 'string', 'max:10000'],
        ]);

        $service = DB::connection('mysql')
            ->table('neoura.service')
            ->where('serviceid', $serviceid)
            ->first();

        if (!$service) {
            return redirect()->route('admin.service')->withErrors(['service' => 'Service not found.']);
        }

        $normalizedDescriptionsText = str_replace(['\\r\\n', '\\n', '\\r'], "\n", (string) ($validated['descriptions_text'] ?? ''));
        $lines = preg_split('/\r\n|\r|\n/', $normalizedDescriptionsText) ?: [];
        $descriptions = collect($lines)
            ->map(fn($line) => trim((string) $line))
            ->filter()
            ->values();

        $beforeSnapshot = $this->serviceSnapshotById($serviceid) ?? [];
        $nextSnapshot = $this->normalizeServiceSnapshotFromInput($serviceid, $validated, $descriptions->all());
        $changes = $this->serviceRecycleChangesForUpdate($beforeSnapshot, $nextSnapshot);
        $serviceName = trim((string) ($beforeSnapshot['name'] ?? $validated['name'] ?? ''));
        $request->attributes->set('activity_action_override', 'Edit Service');
        $request->attributes->set(
            'activity_detail_override',
            'Edit Service ' . ($serviceName !== '' ? $serviceName : ('#' . $serviceid))
            . '; ' . $this->serviceActivityDetailFromChanges($changes)
        );
        $this->archiveServiceRecycleEntry('update', $serviceid, $adminAuth, (string) $request->ip(), $nextSnapshot);

        DB::connection('mysql')->transaction(function () use ($serviceid, $validated, $descriptions) {
            DB::connection('mysql')
                ->table('neoura.service')
                ->where('serviceid', $serviceid)
                ->update([
                    'name' => $validated['name'],
                    'detail' => $validated['detail'],
                    'duration' => $validated['duration'],
                    'price' => $validated['price'],
                ]);

            DB::connection('mysql')
                ->table('neoura.description')
                ->where('serviceid', $serviceid)
                ->delete();

            if ($descriptions->isNotEmpty()) {
                $rows = $descriptions->map(function ($description) use ($serviceid) {
                    return [
                        'name' => $description,
                        'serviceid' => $serviceid,
                    ];
                })->all();

                DB::connection('mysql')
                    ->table('neoura.description')
                    ->insert($rows);
            }
        });

        return redirect()->route('admin.service')->with('status', 'Service updated.');
    }

    public function adminServiceDelete(Request $request, int $serviceid)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'service')) {
            return $this->sidebarPermissionDenied($request);
        }

        $service = DB::connection('mysql')
            ->table('neoura.service')
            ->where('serviceid', $serviceid)
            ->first();

        if (!$service) {
            return redirect()->route('admin.service')->withErrors(['service' => 'Service not found.']);
        }

        $request->attributes->set('activity_action_override', 'Delete Service');
        $request->attributes->set(
            'activity_detail_override',
            'Delete Service ' . trim((string) ($service->name ?? ('#' . $serviceid)))
            . '; duration ' . trim((string) ($service->duration ?? '-'))
            . '; price ' . trim((string) ($service->price ?? '-'))
        );

        $this->archiveServiceRecycleEntry('delete', $serviceid, $adminAuth, (string) $request->ip());

        DB::connection('mysql')->transaction(function () use ($serviceid) {
            DB::connection('mysql')
                ->table('neoura.description')
                ->where('serviceid', $serviceid)
                ->delete();

            DB::connection('mysql')
                ->table('neoura.service')
                ->where('serviceid', $serviceid)
                ->delete();
        });

        return redirect()->route('admin.service')->with('status', 'Service deleted.');
    }

    public function superAdminRecycleBin(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'recycle')) {
            return $this->sidebarPermissionDenied($request);
        }

        $website = $this->websiteSettings();
        $sidebarServices = $this->sidebarServices();
        $entries = $this->serviceRecycleBinEntries();
        $selectedLevel = strtolower(trim((string) $request->query('level', 'all')));
        $selectedAction = strtolower(trim((string) $request->query('action', 'all')));
        $page = (int) $request->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $levelOptions = collect($entries)
            ->map(fn($entry) => strtolower(trim((string) ($entry['actor']['levelname'] ?? ''))))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $filteredRecycleEntries = collect($entries)
            ->filter(function ($entry) use ($selectedLevel, $selectedAction) {
                if (!is_array($entry)) {
                    return false;
                }

                $entryLevel = strtolower(trim((string) ($entry['actor']['levelname'] ?? '')));
                $entryAction = strtolower(trim((string) ($entry['action'] ?? '')));

                if ($selectedLevel !== '' && $selectedLevel !== 'all' && $entryLevel !== $selectedLevel) {
                    return false;
                }

                if ($selectedAction !== '' && $selectedAction !== 'all' && $entryAction !== $selectedAction) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();

        $perPage = 20;
        $totalRows = count($filteredRecycleEntries);
        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }
        $offset = ($page - 1) * $perPage;
        $recycleEntries = array_values(array_slice($filteredRecycleEntries, $offset, $perPage));
        $countOnPage = count($recycleEntries);
        $recyclePagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRows,
            'last_page' => $lastPage,
            'from' => $totalRows > 0 ? ($offset + 1) : 0,
            'to' => $totalRows > 0 ? min($offset + $countOnPage, $totalRows) : 0,
        ];

        if ($request->expectsJson() || $request->ajax()) {
            $html = view('superadmin.partials.recycle-bin-table', [
                'recycleEntries' => $recycleEntries,
                'recyclePagination' => $recyclePagination,
            ])->render();

            return response()->json([
                'status' => 'ok',
                'html' => $html,
                'count' => count($recycleEntries),
                'pagination' => $recyclePagination,
            ]);
        }

        $data = [
            'title' => 'Recycle Bin | ' . $website['name'],
            'adminAuth' => $adminAuth,
            'showAdminMenu' => true,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'sidebarServices' => $sidebarServices,
            'website' => $website,
            'recycleEntries' => $recycleEntries,
            'recyclePagination' => $recyclePagination,
            'recycleLevelOptions' => $levelOptions,
            'selectedRecycleLevel' => $selectedLevel,
            'selectedRecycleAction' => $selectedAction,
            'pageScript' => 'superadmin-recycle-bin.js',
        ];

        $this->renderParts(['all.header', 'all.menu', 'superadmin.recycle-bin', 'all.footer'], $data);
    }

    public function superAdminRecycleBinRestore(Request $request, string $recycleId)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'recycle')) {
            return $this->sidebarPermissionDenied($request);
        }

        $targetId = trim($recycleId);
        if ($targetId === '') {
            return redirect()->route('superadmin.recyclebin')->withErrors(['recycle' => 'Invalid recycle item ID.']);
        }

        $entries = $this->serviceRecycleBinEntries();
        $index = null;
        $entry = null;

        foreach ($entries as $cursor => $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((string) ($item['recycle_id'] ?? '') === $targetId) {
                $index = $cursor;
                $entry = $item;
                break;
            }
        }

        if ($index === null || !is_array($entry)) {
            return redirect()->route('superadmin.recyclebin')->withErrors(['recycle' => 'Recycle item not found.']);
        }

        $snapshot = $entry['service'] ?? null;
        if (!is_array($snapshot)) {
            return redirect()->route('superadmin.recyclebin')->withErrors(['recycle' => 'Recycle snapshot is invalid.']);
        }

        DB::connection('mysql')->transaction(function () use ($snapshot) {
            $this->restoreServiceFromSnapshot($snapshot);
        });

        unset($entries[$index]);
        $this->saveServiceRecycleBinEntries(array_values($entries));

        return redirect()->route('superadmin.recyclebin')->with('status', 'Service restored from recycle bin.');
    }

    public function superAdminRecycleBinDeletePermanent(Request $request, string $recycleId)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'recycle')) {
            return $this->sidebarPermissionDenied($request);
        }

        $targetId = trim($recycleId);
        if ($targetId === '') {
            return redirect()->route('superadmin.recyclebin')->withErrors(['recycle' => 'Invalid recycle item ID.']);
        }

        $entries = $this->serviceRecycleBinEntries();
        $index = null;
        $entry = null;

        foreach ($entries as $cursor => $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((string) ($item['recycle_id'] ?? '') === $targetId) {
                $index = $cursor;
                $entry = $item;
                break;
            }
        }

        if ($index === null || !is_array($entry)) {
            return redirect()->route('superadmin.recyclebin')->withErrors(['recycle' => 'Recycle item not found.']);
        }

        $action = strtolower(trim((string) ($entry['action'] ?? '')));
        $serviceId = (int) (($entry['service']['serviceid'] ?? 0));

        if ($action === 'delete' && $serviceId > 0) {
            $remainingEntries = array_values(array_filter($entries, function ($item) use ($serviceId) {
                if (!is_array($item)) {
                    return false;
                }

                return (int) (($item['service']['serviceid'] ?? 0)) !== $serviceId;
            }));

            $removedCount = count($entries) - count($remainingEntries);
            $this->saveServiceRecycleBinEntries($remainingEntries);

            return redirect()->route('superadmin.recyclebin')->with(
                'status',
                $removedCount > 1
                    ? 'Delete action purged permanently, including related edit/delete history for the same service.'
                    : 'Delete action purged permanently.'
            );
        }

        unset($entries[$index]);
        $this->saveServiceRecycleBinEntries(array_values($entries));

        return redirect()->route('superadmin.recyclebin')->with('status', 'Recycle history item deleted permanently.');
    }

    public function adminPaymentValidation(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'payment')) {
            return $this->sidebarPermissionDenied($request);
        }

        $selectedBank = trim((string) $request->query('bank', ''));
        $selectedStatus = strtolower(trim((string) $request->query('status', '')));
        if (!in_array($selectedStatus, ['pending', 'approved', 'rejected'], true)) {
            $selectedStatus = '';
        }
        $page = (int) $request->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $allPaymentRows = $this->paymentValidationRows();
        $filteredPaymentRows = $this->paymentValidationRows($selectedBank, $selectedStatus);
        $perPage = 15;
        $totalRows = count($filteredPaymentRows);
        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }
        $offset = ($page - 1) * $perPage;
        $paymentRows = array_values(array_slice($filteredPaymentRows, $offset, $perPage));
        $countOnPage = count($paymentRows);
        $paymentPagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRows,
            'last_page' => $lastPage,
            'from' => $totalRows > 0 ? ($offset + 1) : 0,
            'to' => $totalRows > 0 ? min($offset + $countOnPage, $totalRows) : 0,
        ];
        $bankOptions = collect($allPaymentRows)
            ->pluck('bank')
            ->map(fn($bank) => trim((string) $bank))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($request->expectsJson() || $request->ajax()) {
            $html = view('admin.partials.payment-validation-list', [
                'paymentRows' => $paymentRows,
                'paymentPagination' => $paymentPagination,
            ])->render();
            return response()->json([
                'status' => 'ok',
                'html' => $html,
                'count' => count($paymentRows),
                'pagination' => $paymentPagination,
            ]);
        }

        $website = $this->websiteSettings();
        $sidebarServices = $this->sidebarServices();

        $data = [
            'title' => 'Payment Validation | ' . $website['name'],
            'adminAuth' => $adminAuth,
            'showAdminMenu' => true,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'sidebarServices' => $sidebarServices,
            'website' => $website,
            'selectedBank' => $selectedBank,
            'selectedStatus' => $selectedStatus,
            'bankOptions' => $bankOptions,
            'paymentRows' => $paymentRows,
            'paymentPagination' => $paymentPagination,
            'pageScript' => 'admin-payment.js',
        ];

        $this->renderParts(['all.header', 'all.menu', 'admin.payment-validation', 'all.footer'], $data);
    }

    private function paymentValidationRows(string $bankFilter = '', string $statusFilter = ''): array
    {
        $query = DB::connection('mysql')
            ->table('neoura.payment as p')
            ->join('neoura.booking as b', 'b.bookingid', '=', 'p.bookingid')
            ->leftJoin('neoura.service as s', 's.serviceid', '=', 'b.serviceid')
            ->leftJoin('neoura.timeslot as t', 't.slotid', '=', 'b.slotid')
            ->select(
                'p.paymentid',
                'p.paymentdate',
                'p.bank',
                'p.proof',
                'b.bookingid',
                'b.bookingcode',
                'b.name',
                'b.email',
                'b.phonenumber',
                'b.status',
                's.name as service_name',
                't.date as booking_date',
                't.start_time',
                't.end_time'
            );

        if ($bankFilter !== '') {
            $query->where('p.bank', $bankFilter);
        }
        if ($statusFilter !== '') {
            $query->whereRaw('LOWER(TRIM(COALESCE(b.status, ""))) = ?', [$statusFilter]);
        }

        return $query
            ->orderByDesc('p.paymentdate')
            ->orderByDesc('p.paymentid')
            ->get()
            ->map(function ($row) {
                $proof = trim((string) ($row->proof ?? ''));
                $proofUrl = $proof;
                if ($proof !== '' && !Str::startsWith($proof, ['http://', 'https://'])) {
                    $proofUrl = asset(ltrim($proof, '/'));
                }

                return [
                    'paymentid' => (int) ($row->paymentid ?? 0),
                    'bookingid' => (int) ($row->bookingid ?? 0),
                    'booking_code' => (string) ($row->bookingcode ?? '-'),
                    'name' => (string) ($row->name ?? '-'),
                    'email' => (string) ($row->email ?? '-'),
                    'phone' => (string) ($row->phonenumber ?? '-'),
                    'status' => (string) ($row->status ?? 'Pending'),
                    'service_name' => (string) ($row->service_name ?? '-'),
                    'booking_date' => (string) ($row->booking_date ?? '-'),
                    'start_time' => substr((string) ($row->start_time ?? '-'), 0, 5),
                    'end_time' => substr((string) ($row->end_time ?? '-'), 0, 5),
                    'payment_date' => (string) ($row->paymentdate ?? '-'),
                    'bank' => (string) ($row->bank ?? '-'),
                    'proof' => $proof,
                    'proof_url' => $proofUrl,
                    'is_image_proof' => preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $proof) === 1,
                ];
            })
            ->all();
    }

    public function adminPaymentValidationUpdate(Request $request, int $bookingid)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'payment')) {
            return $this->sidebarPermissionDenied($request);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
        ]);

        $booking = DB::connection('mysql')
            ->table('neoura.booking')
            ->select('bookingid', 'slotid', 'status')
            ->where('bookingid', $bookingid)
            ->first();

        if (!$booking) {
            return redirect()->route('admin.payment')->withErrors(['payment' => 'Booking not found.']);
        }

        $nextStatus = $validated['action'] === 'approve' ? 'Approved' : 'Rejected';
        $previousStatus = trim((string) ($booking->status ?? 'Pending'));
        $request->attributes->set('activity_action_override', 'Update Payment Validation');
        $request->attributes->set(
            'activity_detail_override',
            'Booking #' . $bookingid . '; status from ' . ($previousStatus !== '' ? $previousStatus : 'Pending') . ' to ' . $nextStatus
        );

        DB::connection('mysql')->transaction(function () use ($booking, $bookingid, $nextStatus) {
            DB::connection('mysql')
                ->table('neoura.booking')
                ->where('bookingid', $bookingid)
                ->update(['status' => $nextStatus]);

            if ($nextStatus === 'Rejected') {
                DB::connection('mysql')
                    ->table('neoura.timeslot')
                    ->where('slotid', (int) ($booking->slotid ?? 0))
                    ->update(['is_booked' => 0]);
            }
        });

        return redirect()->route('admin.payment')->with('status', 'Payment status updated to ' . $nextStatus . '.');
    }

    public function adminUserData(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'user')) {
            return $this->sidebarPermissionDenied($request);
        }

        $search = trim((string) $request->query('q', ''));
        if (mb_strlen($search) > 100) {
            $search = mb_substr($search, 0, 100);
        }

        $levelId = (int) $request->query('levelid', 0);
        if ($levelId < 1) {
            $levelId = 0;
        }

        $page = (int) $request->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $perPage = 20;
        $searchLike = '%' . $search . '%';

        $baseUserQuery = DB::connection('mysql')
            ->table('neoura.user as u')
            ->leftJoin('neoura.employer as e', 'e.userid', '=', 'u.userid')
            ->leftJoin('neoura.level as l', 'l.levelid', '=', 'u.levelid')
            ->select(
                'u.userid',
                'u.username',
                'u.levelid as user_levelid',
                'e.name as employer_name',
                'e.email as employer_email',
                'e.phonenumber as employer_phone',
                'l.levelname'
            );

        if ($levelId > 0) {
            $baseUserQuery->where('u.levelid', $levelId);
        }

        if ($search !== '') {
            $baseUserQuery->where(function ($query) use ($searchLike) {
                $query
                    ->where('u.username', 'like', $searchLike)
                    ->orWhere('e.name', 'like', $searchLike)
                    ->orWhere('e.email', 'like', $searchLike)
                    ->orWhere('e.phonenumber', 'like', $searchLike)
                    ->orWhere('l.levelname', 'like', $searchLike);
            });
        }

        $totalRows = (clone $baseUserQuery)->count('u.userid');
        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;

        $userRows = $baseUserQuery
            ->orderBy('u.userid')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($row) {
                return [
                    'userid' => (int) ($row->userid ?? 0),
                    'levelid' => (int) ($row->user_levelid ?? 0),
                    'username' => trim((string) ($row->username ?? '')) ?: '-',
                    'name' => trim((string) ($row->employer_name ?? '')) ?: '-',
                    'email' => trim((string) ($row->employer_email ?? '')) ?: '-',
                    'phonenumber' => trim((string) ($row->employer_phone ?? '')) ?: '-',
                    'level' => trim((string) ($row->levelname ?? '')) ?: '-',
                ];
            })
            ->all();

        $levelOptions = DB::connection('mysql')
            ->table('neoura.level')
            ->select('levelid', 'levelname')
            ->orderBy('levelid')
            ->get()
            ->map(function ($row) {
                return [
                    'levelid' => (int) ($row->levelid ?? 0),
                    'levelname' => trim((string) ($row->levelname ?? '')) ?: '-',
                ];
            })
            ->filter(fn($row) => ($row['levelid'] ?? 0) > 0)
            ->values()
            ->all();

        $countOnPage = count($userRows);
        $from = $totalRows > 0 ? ($offset + 1) : 0;
        $to = $totalRows > 0 ? min($offset + $countOnPage, $totalRows) : 0;
        $pagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRows,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $to,
        ];

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'rows' => $userRows,
                'filters' => [
                    'q' => $search,
                    'levelid' => $levelId,
                ],
                'pagination' => $pagination,
            ]);
        }

        $website = $this->websiteSettings();
        $sidebarServices = $this->sidebarServices();
        $data = [
            'title' => 'User Data | ' . $website['name'],
            'adminAuth' => $adminAuth,
            'showAdminMenu' => true,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'sidebarServices' => $sidebarServices,
            'website' => $website,
            'userRows' => $userRows,
            'userFilters' => [
                'q' => $search,
                'levelid' => $levelId,
            ],
            'userPagination' => $pagination,
            'levelOptions' => $levelOptions,
            'pageScript' => 'admin-user-data.js',
        ];

        $this->renderParts(['all.header', 'all.menu', 'admin.user-data', 'all.footer'], $data);
    }

    public function exportUserDataExcel(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'user')) {
            return $this->sidebarPermissionDenied($request);
        }

        $rows = DB::connection('mysql')
            ->table('neoura.user as u')
            ->leftJoin('neoura.employer as e', 'e.userid', '=', 'u.userid')
            ->select(
                'u.userid',
                'u.username',
                'e.name as employer_name',
                'e.email as employer_email',
                'e.phonenumber as employer_phone',
                'u.levelid'
            )
            ->orderBy('u.userid')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('UserData');
        $sheet->fromArray(['userid', 'username', 'name', 'email', 'phonenumber', 'levelid'], null, 'A1');

        $cursor = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                (int) ($row->userid ?? 0),
                (string) ($row->username ?? ''),
                (string) ($row->employer_name ?? ''),
                (string) ($row->employer_email ?? ''),
                (string) ($row->employer_phone ?? ''),
                (int) ($row->levelid ?? 0),
            ], null, 'A' . $cursor);
            $cursor++;
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filePath = tempnam(sys_get_temp_dir(), 'userdata_export_');
        (new Xlsx($spreadsheet))->save($filePath);

        $request->attributes->set('activity_action_override', 'Export User Data Excel');
        $request->attributes->set('activity_detail_override', 'Exported user data to Excel (.xlsx).');

        return response()->download(
            $filePath,
            'user-data-' . date('Ymd-His') . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function importUserDataExcel(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'user')) {
            return $this->sidebarPermissionDenied($request);
        }

        $validated = $request->validate([
            'userdata_excel' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $file = $validated['userdata_excel'] ?? null;
        if (!$file) {
            return redirect()->route('admin.userdata')->withErrors(['userdata' => 'Excel file is required.']);
        }

        $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();
        $highestRow = (int) $sheet->getHighestDataRow();
        $actorLevel = strtolower(trim((string) ($adminAuth['levelname'] ?? '')));

        $created = 0;
        $updated = 0;

        try {
            DB::connection('mysql')->transaction(function () use ($sheet, $highestRow, $actorLevel, &$created, &$updated) {
                for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                    $userIdRaw = trim((string) $sheet->getCell('A' . $rowNum)->getFormattedValue());
                    $username = trim((string) $sheet->getCell('B' . $rowNum)->getFormattedValue());
                    $name = trim((string) $sheet->getCell('C' . $rowNum)->getFormattedValue());
                    $email = trim((string) $sheet->getCell('D' . $rowNum)->getFormattedValue());
                    $phonenumber = trim((string) $sheet->getCell('E' . $rowNum)->getFormattedValue());
                    $levelIdRaw = trim((string) $sheet->getCell('F' . $rowNum)->getFormattedValue());

                    if ($userIdRaw === '' && $username === '' && $name === '' && $email === '' && $phonenumber === '' && $levelIdRaw === '') {
                        continue;
                    }

                    if ($username === '' || $name === '' || $email === '' || $phonenumber === '' || $levelIdRaw === '') {
                        throw new \RuntimeException('Row ' . $rowNum . ' is invalid. Username, name, email, phone number, and levelid are required.');
                    }

                    if (
                        mb_strlen($username) > 255
                        || mb_strlen($name) > 255
                        || mb_strlen($email) > 255
                        || mb_strlen($phonenumber) > 255
                    ) {
                        throw new \RuntimeException('Row ' . $rowNum . ' exceeds max length (255 chars).');
                    }

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new \RuntimeException('Row ' . $rowNum . ' has invalid email format.');
                    }

                    $normalizedUserId = 0;
                    if ($userIdRaw !== '' && is_numeric($userIdRaw)) {
                        $normalizedUserId = (int) round((float) $userIdRaw);
                    }

                    if (!is_numeric($levelIdRaw)) {
                        throw new \RuntimeException('Row ' . $rowNum . ' levelid must be numeric.');
                    }
                    $levelId = (int) round((float) $levelIdRaw);
                    if ($levelId < 1) {
                        throw new \RuntimeException('Row ' . $rowNum . ' levelid must be greater than 0.');
                    }

                    $level = DB::connection('mysql')
                        ->table('neoura.level')
                        ->select('levelid', 'levelname')
                        ->where('levelid', $levelId)
                        ->first();
                    if (!$level) {
                        throw new \RuntimeException('Row ' . $rowNum . ' has invalid levelid.');
                    }

                    $targetLevel = strtolower(trim((string) ($level->levelname ?? '')));
                    if ($targetLevel === 'superadmin' && $actorLevel !== 'superadmin') {
                        throw new \RuntimeException('Row ' . $rowNum . ' cannot set level to superadmin.');
                    }

                    $userById = null;
                    if ($normalizedUserId > 0) {
                        $userById = DB::connection('mysql')
                            ->table('neoura.user')
                            ->select('userid', 'username', 'levelid')
                            ->where('userid', $normalizedUserId)
                            ->first();
                    }

                    $userByUsername = DB::connection('mysql')
                        ->table('neoura.user')
                        ->select('userid', 'username', 'levelid')
                        ->whereRaw('LOWER(username) = ?', [strtolower($username)])
                        ->first();

                    if ($userById && $userByUsername && (int) ($userByUsername->userid ?? 0) !== (int) ($userById->userid ?? 0)) {
                        throw new \RuntimeException('Row ' . $rowNum . ' username is already used by another user.');
                    }

                    $targetUser = $userById ?: $userByUsername;
                    if ($targetUser) {
                        $currentLevel = DB::connection('mysql')
                            ->table('neoura.level')
                            ->select('levelname')
                            ->where('levelid', (int) ($targetUser->levelid ?? 0))
                            ->first();
                        $currentLevelName = strtolower(trim((string) ($currentLevel->levelname ?? '')));
                        if ($currentLevelName === 'superadmin' && $actorLevel !== 'superadmin') {
                            throw new \RuntimeException('Row ' . $rowNum . ' cannot modify existing superadmin user.');
                        }
                    }

                    if ($targetUser) {
                        $targetUserId = (int) ($targetUser->userid ?? 0);
                        DB::connection('mysql')
                            ->table('neoura.user')
                            ->where('userid', $targetUserId)
                            ->update([
                                'username' => $username,
                                'password' => Hash::make($username),
                                'levelid' => $levelId,
                            ]);

                        $employerExists = DB::connection('mysql')
                            ->table('neoura.employer')
                            ->where('userid', $targetUserId)
                            ->exists();

                        if ($employerExists) {
                            DB::connection('mysql')
                                ->table('neoura.employer')
                                ->where('userid', $targetUserId)
                                ->update([
                                    'name' => $name,
                                    'email' => $email,
                                    'phonenumber' => $phonenumber,
                                ]);
                        } else {
                            DB::connection('mysql')
                                ->table('neoura.employer')
                                ->insert([
                                    'name' => $name,
                                    'email' => $email,
                                    'phonenumber' => $phonenumber,
                                    'userid' => $targetUserId,
                                ]);
                        }

                        $updated++;
                    } else {
                        $newUserId = (int) DB::connection('mysql')
                            ->table('neoura.user')
                            ->insertGetId([
                                'username' => $username,
                                'password' => Hash::make($username),
                                'levelid' => $levelId,
                            ]);

                        DB::connection('mysql')
                            ->table('neoura.employer')
                            ->insert([
                                'name' => $name,
                                'email' => $email,
                                'phonenumber' => $phonenumber,
                                'userid' => $newUserId,
                            ]);

                        $created++;
                    }
                }
            });
        } catch (\Throwable $e) {
            return redirect()->route('admin.userdata')
                ->withErrors(['userdata' => $e->getMessage()]);
        }

        if (($created + $updated) < 1) {
            return redirect()->route('admin.userdata')
                ->withErrors(['userdata' => 'No valid rows found in uploaded Excel file.']);
        }

        $request->attributes->set('activity_action_override', 'Import User Data Excel');
        $request->attributes->set(
            'activity_detail_override',
            'Imported user data from Excel; created ' . $created . ', updated ' . $updated . '.'
        );

        return redirect()->route('admin.userdata')
            ->with('status', 'User import completed. Created: ' . $created . ', Updated: ' . $updated . '.');
    }

    public function adminUserStore(Request $request): JsonResponse
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'user')) {
            return response()->json(['status' => 'error', 'message' => 'You do not have access to this menu.'], 403);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phonenumber' => ['required', 'string', 'max:255'],
            'levelid' => ['required', 'integer', 'min:1'],
        ]);

        $username = trim((string) ($validated['username'] ?? ''));
        if ($username === '') {
            return response()->json(['status' => 'error', 'message' => 'Username is required.'], 422);
        }

        $existingUsername = DB::connection('mysql')
            ->table('neoura.user')
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->exists();
        if ($existingUsername) {
            return response()->json(['status' => 'error', 'message' => 'Username already exists.'], 422);
        }

        $level = DB::connection('mysql')
            ->table('neoura.level')
            ->select('levelid', 'levelname')
            ->where('levelid', (int) $validated['levelid'])
            ->first();
        if (!$level) {
            return response()->json(['status' => 'error', 'message' => 'Invalid level.'], 422);
        }

        $targetLevel = strtolower(trim((string) ($level->levelname ?? '')));
        $actorLevel = strtolower(trim((string) ($adminAuth['levelname'] ?? '')));
        if ($targetLevel === 'superadmin' && $actorLevel !== 'superadmin') {
            return response()->json(['status' => 'error', 'message' => 'Only superadmin can create superadmin account.'], 403);
        }

        $userId = DB::connection('mysql')->transaction(function () use ($validated, $username) {
            $newUserId = DB::connection('mysql')
                ->table('neoura.user')
                ->insertGetId([
                    'username' => $username,
                    'password' => Hash::make($username),
                    'levelid' => (int) $validated['levelid'],
                ]);

            DB::connection('mysql')
                ->table('neoura.employer')
                ->insert([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phonenumber' => $validated['phonenumber'],
                    'userid' => $newUserId,
                ]);

            return $newUserId;
        });

        $request->attributes->set('activity_action_override', 'Create User');
        $request->attributes->set(
            'activity_detail_override',
            'Create user ' . $username
            . '; name ' . trim((string) ($validated['name'] ?? '-'))
            . '; email ' . trim((string) ($validated['email'] ?? '-'))
            . '; phone ' . trim((string) ($validated['phonenumber'] ?? '-'))
            . '; level ' . trim((string) ($level->levelname ?? '-'))
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'User added successfully.',
            'user' => [
                'userid' => (int) $userId,
                'username' => $username,
                'name' => (string) $validated['name'],
                'email' => (string) $validated['email'],
                'phonenumber' => (string) $validated['phonenumber'],
                'level' => trim((string) ($level->levelname ?? '-')) ?: '-',
            ],
        ]);
    }

    public function adminUserResetPassword(Request $request, int $userid): JsonResponse
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'user')) {
            return response()->json(['status' => 'error', 'message' => 'You do not have access to this menu.'], 403);
        }

        $target = DB::connection('mysql')
            ->table('neoura.user as u')
            ->leftJoin('neoura.level as l', 'l.levelid', '=', 'u.levelid')
            ->select('u.userid', 'u.username', 'l.levelname')
            ->where('u.userid', $userid)
            ->first();

        if (!$target) {
            return response()->json(['status' => 'error', 'message' => 'User not found.'], 404);
        }

        $targetLevel = strtolower(trim((string) ($target->levelname ?? '')));
        $actorLevel = strtolower(trim((string) ($adminAuth['levelname'] ?? '')));
        if ($targetLevel === 'superadmin' && $actorLevel !== 'superadmin') {
            return response()->json(['status' => 'error', 'message' => 'Only superadmin can reset superadmin password.'], 403);
        }

        $username = trim((string) ($target->username ?? ''));
        if ($username === '') {
            return response()->json(['status' => 'error', 'message' => 'Username is invalid.'], 422);
        }

        DB::connection('mysql')
            ->table('neoura.user')
            ->where('userid', $userid)
            ->update([
                'password' => Hash::make($username),
            ]);

        $request->attributes->set('activity_action_override', 'Reset User Password');
        $request->attributes->set('activity_detail_override', 'Reset password for user ' . $username . ' (userid ' . $userid . ').');

        return response()->json([
            'status' => 'ok',
            'message' => 'Password reset successful. Default password is username.',
        ]);
    }

    public function adminUserDelete(Request $request, int $userid): JsonResponse
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'user')) {
            return response()->json(['status' => 'error', 'message' => 'You do not have access to this menu.'], 403);
        }

        $actorUserId = (int) ($adminAuth['userid'] ?? 0);
        if ($actorUserId > 0 && $actorUserId === $userid) {
            return response()->json(['status' => 'error', 'message' => 'You cannot delete your own account.'], 422);
        }

        $target = DB::connection('mysql')
            ->table('neoura.user as u')
            ->leftJoin('neoura.level as l', 'l.levelid', '=', 'u.levelid')
            ->select('u.userid', 'u.username', 'l.levelname')
            ->where('u.userid', $userid)
            ->first();

        if (!$target) {
            return response()->json(['status' => 'error', 'message' => 'User not found.'], 404);
        }

        $targetLevel = strtolower(trim((string) ($target->levelname ?? '')));
        $actorLevel = strtolower(trim((string) ($adminAuth['levelname'] ?? '')));
        if ($targetLevel === 'superadmin' && $actorLevel !== 'superadmin') {
            return response()->json(['status' => 'error', 'message' => 'Only superadmin can delete superadmin account.'], 403);
        }

        DB::connection('mysql')->transaction(function () use ($userid) {
            DB::connection('mysql')
                ->table('neoura.employer')
                ->where('userid', $userid)
                ->delete();

            DB::connection('mysql')
                ->table('neoura.user')
                ->where('userid', $userid)
                ->delete();
        });

        $request->attributes->set('activity_action_override', 'Delete User');
        $request->attributes->set(
            'activity_detail_override',
            'Delete user ' . trim((string) ($target->username ?? ('#' . $userid)))
            . '; level ' . trim((string) ($target->levelname ?? '-'))
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'User deleted.',
        ]);
    }

    public function adminActivityLog(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'activity')) {
            return $this->sidebarPermissionDenied($request);
        }

        $website = $this->websiteSettings();
        $sidebarServices = $this->sidebarServices();
        $actorLevel = strtolower(trim((string) ($adminAuth['levelname'] ?? '')));
        $actorUserId = (int) ($adminAuth['userid'] ?? 0);

        $filteredEntries = collect($this->activityLogEntries())
            ->filter(fn($entry) => is_array($entry))
            ->filter(function ($entry) use ($actorLevel, $actorUserId) {
                if (!is_array($entry)) {
                    return false;
                }

                $actor = is_array($entry['actor'] ?? null) ? $entry['actor'] : [];
                $entryUserId = (int) ($actor['userid'] ?? 0);
                $entryLevel = strtolower(trim((string) ($actor['levelname'] ?? '')));

                if (in_array($actorLevel, ['admin', 'manager'], true)) {
                    return $actorUserId > 0 && $entryUserId === $actorUserId;
                }

                if ($actorLevel === 'superadmin') {
                    return in_array($entryLevel, ['admin', 'manager', 'superadmin'], true);
                }

                return false;
            })
            ->values()
            ->all();

        $page = (int) $request->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $perPage = 20;
        $totalRows = count($filteredEntries);
        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;
        $activityEntries = collect($filteredEntries)
            ->slice($offset, $perPage)
            ->values()
            ->all();

        $countOnPage = count($activityEntries);
        $from = $totalRows > 0 ? ($offset + 1) : 0;
        $to = $totalRows > 0 ? min($offset + $countOnPage, $totalRows) : 0;
        $pagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRows,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $to,
        ];

        if ($request->expectsJson() || $request->ajax()) {
            $html = view('admin.partials.activity-log-table', [
                'activityEntries' => $activityEntries,
                'activityPagination' => $pagination,
            ])->render();

            return response()->json([
                'status' => 'ok',
                'html' => $html,
                'pagination' => $pagination,
            ]);
        }

        $data = [
            'title' => 'Activity Log | ' . $website['name'],
            'adminAuth' => $adminAuth,
            'showAdminMenu' => true,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'sidebarServices' => $sidebarServices,
            'website' => $website,
            'activityEntries' => $activityEntries,
            'activityPagination' => $pagination,
            'pageScript' => 'admin-activity-log.js',
        ];

        $this->renderParts(['all.header', 'all.menu', 'admin.activity-log', 'all.footer'], $data);
    }

    public function adminActivityLocationUpdate(Request $request): JsonResponse
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $request->session()->put('admin_activity_coords', [
            'latitude' => trim((string) ($validated['latitude'] ?? '')),
            'longitude' => trim((string) ($validated['longitude'] ?? '')),
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Location saved.',
        ]);
    }


    private function parseRupiahAmount(string $rawPrice): int
    {
        $value = strtolower(trim($rawPrice));
        if ($value === '') {
            return 0;
        }

        if (preg_match('/([\d.,]+)\s*(juta|jt|ribu|rb)\b/i', $value, $matches) === 1) {
            $numberPart = str_replace(',', '.', (string) ($matches[1] ?? '0'));
            if (substr_count($numberPart, '.') > 1) {
                $numberPart = str_replace('.', '', $numberPart);
            }

            $base = (float) $numberPart;
            $unit = strtolower((string) ($matches[2] ?? ''));
            $multiplier = in_array($unit, ['juta', 'jt'], true) ? 1000000 : 1000;

            return (int) round(max(0, $base * $multiplier));
        }

        $digits = preg_replace('/\D+/', '', $value);
        return (int) ($digits !== '' ? $digits : 0);
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format(max(0, $amount), 0, ',', '.');
    }

    private function approvedPaymentIncomeEntries(): array
    {
        return DB::connection('mysql')
            ->table('neoura.payment as p')
            ->join('neoura.booking as b', 'b.bookingid', '=', 'p.bookingid')
            ->leftJoin('neoura.timeslot as ts', 'ts.slotid', '=', 'b.slotid')
            ->leftJoin('neoura.service as s', 's.serviceid', '=', 'b.serviceid')
            ->select(
                'p.paymentdate',
                'ts.date as schedule_date',
                's.price as service_price'
            )
            ->whereRaw('LOWER(TRIM(COALESCE(b.status, ""))) = ?', ['approved'])
            ->orderBy('p.paymentdate')
            ->get()
            ->map(function ($row) {
                $scheduleDateRaw = trim((string) ($row->schedule_date ?? ''));
                $paymentDateRaw = trim((string) ($row->paymentdate ?? ''));
                $dateSource = $scheduleDateRaw !== '' ? $scheduleDateRaw : $paymentDateRaw;
                $timestamp = strtotime($dateSource);
                $paymentDate = $timestamp !== false ? date('Y-m-d', $timestamp) : '';
                $amount = $this->parseRupiahAmount((string) ($row->service_price ?? ''));

                return [
                    'payment_date' => $paymentDate,
                    'amount' => max(0, $amount),
                ];
            })
            ->filter(fn($entry) => is_array($entry) && ($entry['payment_date'] ?? '') !== '')
            ->values()
            ->all();
    }

    private function ensureExpenseStorageSchema(): void
    {
        $connection = DB::connection('mysql');
        $schemaName = 'neoura';

        $yearColumn = $connection->selectOne(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$schemaName, 'expense', 'expense_year']
        );
        if (!$yearColumn) {
            $connection->statement('ALTER TABLE neoura.expense ADD COLUMN expense_year INT NULL AFTER cost');
        }

        $monthColumn = $connection->selectOne(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$schemaName, 'expense', 'expense_month']
        );
        if (!$monthColumn) {
            $connection->statement('ALTER TABLE neoura.expense ADD COLUMN expense_month TINYINT NULL AFTER expense_year');
        }

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        $connection->table('neoura.expense')
            ->where(function ($query) {
                $query->whereNull('expense_year')->orWhereNull('expense_month');
            })
            ->update([
                'expense_year' => $currentYear,
                'expense_month' => $currentMonth,
            ]);
    }

    private function expenseRows(?int $year = null, ?int $month = null): array
    {
        $query = DB::connection('mysql')
            ->table('neoura.expense')
            ->select('expenseid', 'expensename', 'cost', 'expense_year', 'expense_month');

        if ($year !== null) {
            $query->where('expense_year', $year);
        }
        if ($month !== null) {
            $query->where('expense_month', $month);
        }

        return $query
            ->orderByDesc('expenseid')
            ->get()
            ->map(function ($row) {
                $rawCost = trim((string) ($row->cost ?? '0'));
                $costValue = $this->parseRupiahAmount($rawCost);

                return [
                    'expenseid' => (int) ($row->expenseid ?? 0),
                    'expensename' => trim((string) ($row->expensename ?? '')),
                    'cost_raw' => $rawCost,
                    'cost_value' => $costValue,
                    'cost_label' => $this->formatRupiah($costValue),
                    'expense_year' => (int) ($row->expense_year ?? 0),
                    'expense_month' => (int) ($row->expense_month ?? 0),
                ];
            })
            ->all();
    }

    private function ensureExpenseMonthSeeded(int $year, int $month): bool
    {
        $existing = (int) DB::connection('mysql')
            ->table('neoura.expense')
            ->where('expense_year', $year)
            ->where('expense_month', $month)
            ->count();
        if ($existing > 0) {
            return false;
        }

        $previousYear = $year;
        $previousMonth = $month - 1;
        if ($previousMonth < 1) {
            $previousMonth = 12;
            $previousYear--;
        }

        $previousRows = DB::connection('mysql')
            ->table('neoura.expense')
            ->select('expensename')
            ->where('expense_year', $previousYear)
            ->where('expense_month', $previousMonth)
            ->orderBy('expenseid')
            ->get();

        $names = collect($previousRows)
            ->map(fn($row) => trim((string) ($row->expensename ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($names)) {
            return false;
        }

        $insertRows = [];
        foreach ($names as $name) {
            $insertRows[] = [
                'expensename' => $name,
                'cost' => '0',
                'expense_year' => $year,
                'expense_month' => $month,
            ];
        }

        DB::connection('mysql')->table('neoura.expense')->insert($insertRows);
        return true;
    }

    private function expenseTotalForMonth(int $year, int $month): int
    {
        $rows = $this->expenseRows($year, $month);
        return array_reduce($rows, function ($carry, $row) {
            return $carry + (int) ($row['cost_value'] ?? 0);
        }, 0);
    }

    public function adminFinancialReport(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'financial')) {
            return $this->sidebarPermissionDenied($request);
        }

        $this->ensureExpenseStorageSchema();
        $website = $this->websiteSettings();
        $sidebarServices = $this->sidebarServices();
        $filters = $this->resolveFinancialReportFilters($request);
        $seededFromPreviousMonth = false;
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        if ((int) $filters['selectedYear'] === $currentYear && (int) $filters['selectedMonth'] === $currentMonth) {
            $seededFromPreviousMonth = $this->ensureExpenseMonthSeeded($currentYear, $currentMonth);
        }
        $reportData = $this->buildFinancialReportData(
            (int) $filters['selectedYear'],
            (int) $filters['selectedMonth']
        );

        $data = [
            'title' => 'Financial Report | ' . $website['name'],
            'adminAuth' => $adminAuth,
            'showAdminMenu' => true,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'sidebarServices' => $sidebarServices,
            'website' => $website,
            'reportType' => $filters['reportType'],
            'selectedYear' => $filters['selectedYear'],
            'selectedMonth' => $filters['selectedMonth'],
            'yearOptions' => $reportData['yearOptions'],
            'dailyRows' => $reportData['dailyRows'],
            'monthlyRows' => $reportData['monthlyRows'],
            'yearlyRows' => $reportData['yearlyRows'],
            'expenseRows' => $reportData['expenseRows'],
            'expenseCostResetThisMonth' => $seededFromPreviousMonth,
            'totalExpenseValue' => $reportData['totalExpenseValue'],
            'totalExpenseLabel' => $reportData['totalExpenseLabel'],
            'netIncomeLabel' => $reportData['netIncomeLabel'],
            'monthName' => $reportData['monthName'],
            'summaryCards' => $reportData['summaryCards'],
            'pageScript' => 'admin-financial-report.js',
        ];

        $this->renderParts(['all.header', 'all.menu', 'admin.financial-report', 'all.footer'], $data);
    }

    public function adminFinancialReportExportExcel(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'financial')) {
            return $this->sidebarPermissionDenied($request);
        }

        $this->ensureExpenseStorageSchema();
        $website = $this->websiteSettings();
        $target = $this->resolveFinancialReportExportTarget($request);
        $snapshot = $this->buildFinancialSnapshot((string) $target['type'], (string) $target['period']);
        $generatedAt = date('d M Y H:i');
        $typeLabel = ucfirst((string) ($snapshot['type'] ?? '-'));

        $incomeValue = (int) ($snapshot['income'] ?? 0);
        $outcomeValue = (int) ($snapshot['outcome'] ?? 0);
        $finalRevenue = $incomeValue - $outcomeValue;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Financial Statement');
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $sheet->mergeCells('C1:G1');
        $sheet->setCellValue('C1', strtoupper((string) ($website['name'] ?? 'NEOURA')));
        $sheet->mergeCells('C2:G2');
        $sheet->setCellValue('C2', 'FINANCIAL STATEMENT');
        $sheet->mergeCells('C3:G3');
        $sheet->setCellValue('C3', 'Period: ' . (string) ($snapshot['period_label'] ?? '-'));
        $sheet->mergeCells('C4:G4');
        $sheet->setCellValue('C4', 'Report Type: ' . $typeLabel . ' | Generated: ' . $generatedAt);

        $sheet->setCellValue('A6', 'Executive Summary');
        $sheet->mergeCells('A6:G6');
        $sheet->setCellValue('A7', 'Metric');
        $sheet->mergeCells('A7:D7');
        $sheet->setCellValue('E7', 'Value');
        $sheet->mergeCells('E7:G7');

        $sheet->setCellValue('A8', 'Total Income');
        $sheet->mergeCells('A8:D8');
        $sheet->setCellValue('E8', $incomeValue);
        $sheet->mergeCells('E8:G8');

        $sheet->setCellValue('A9', 'Total Outcome');
        $sheet->mergeCells('A9:D9');
        $sheet->setCellValue('E9', $outcomeValue);
        $sheet->mergeCells('E9:G9');

        $sheet->setCellValue('A10', 'Result');
        $sheet->mergeCells('A10:D10');
        $sheet->setCellValue('E10', $finalRevenue);
        $sheet->mergeCells('E10:G10');

        $sheet->setCellValue('A11', 'Result Description');
        $sheet->mergeCells('A11:D11');
        $sheet->setCellValue('E11', (string) ($snapshot['result_description'] ?? 'Result is calculated automatically.'));
        $sheet->mergeCells('E11:G11');

        $sheet->setCellValue('A12', 'Income Description');
        $sheet->mergeCells('A12:D12');
        $sheet->setCellValue('E12', (string) ($snapshot['income_description'] ?? '-'));
        $sheet->mergeCells('E12:G12');

        $sheet->setCellValue('A13', 'Outcome Description');
        $sheet->mergeCells('A13:D13');
        $sheet->setCellValue('E13', (string) ($snapshot['outcome_description'] ?? '-'));
        $sheet->mergeCells('E13:G13');

        $logoPath = trim((string) ($website['logo_path'] ?? ''));
        $logoFilePath = $logoPath !== '' ? public_path(ltrim($logoPath, '/')) : '';
        if ($logoFilePath !== '' && is_file($logoFilePath)) {
            $drawing = new Drawing();
            $drawing->setName('Website Logo');
            $drawing->setDescription('Website Logo');
            $drawing->setPath($logoFilePath);
            $drawing->setHeight(58);
            $drawing->setCoordinates('A1');
            $drawing->setWorksheet($sheet);
            $sheet->getRowDimension(1)->setRowHeight(46);
            $sheet->getRowDimension(2)->setRowHeight(24);
        }

        $row = 15;
        $sheet->setCellValue('A' . $row, 'Outcome Detail');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $row++;
        $headerRow = $row;
        $sheet->setCellValue('A' . $row, 'No');
        $sheet->setCellValue('B' . $row, 'Expense Category');
        $sheet->mergeCells('B' . $row . ':D' . $row);
        $sheet->setCellValue('E' . $row, 'Amount (Rp)');
        $sheet->mergeCells('E' . $row . ':G' . $row);
        $row++;

        $index = 1;
        $detailStartRow = $row;
        foreach ((array) ($snapshot['outcome_detail_rows'] ?? []) as $detailRow) {
            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('B' . $row, (string) ($detailRow['label'] ?? '-'));
            $sheet->mergeCells('B' . $row . ':D' . $row);
            $detailValue = (int) ($detailRow['value'] ?? 0);
            $sheet->setCellValue('E' . $row, $detailValue);
            $sheet->mergeCells('E' . $row . ':G' . $row);
            $sheet->getStyle('A' . $row . ':G' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
            $index++;
        }
        if ($index === 1) {
            $sheet->setCellValue('A' . $row, '-');
            $sheet->setCellValue('B' . $row, 'No outcome detail available for this period.');
            $sheet->mergeCells('B' . $row . ':G' . $row);
            $sheet->getStyle('A' . $row . ':G' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
        }

        $totalRow = $row;
        $sheet->setCellValue('A' . $row, 'TOTAL OUTCOME');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->setCellValue('E' . $row, $outcomeValue);
        $sheet->mergeCells('E' . $row . ':G' . $row);

        $footerRow = $row + 2;
        $sheet->mergeCells('A' . $footerRow . ':G' . $footerRow);
        $sheet->setCellValue('A' . $footerRow, 'Prepared by system on ' . $generatedAt . '. Values are stated in Indonesian Rupiah (IDR).');

        $sheet->getStyle('C1:G1')->getFont()->setBold(true)->setSize(18);
        $sheet->getStyle('C2:G2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('C3:G4')->getFont()->setSize(10)->getColor()->setRGB('4B5563');
        $sheet->getStyle('C1:G4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('C1:G4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('C1:G1')->getAlignment()->setWrapText(true);

        $sheet->getStyle('A6:G6')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A6:G6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
        $sheet->getStyle('A7:G7')->getFont()->setBold(true);
        $sheet->getStyle('A7:G7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F8FC');
        $sheet->getStyle('A8:G13')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A10:G11')->getFont()->setBold(true);
        $sheet->getStyle('A12:A13')->getFont()->setBold(true);

        $sheet->getStyle('A15:G15')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A15:G15')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
        $sheet->getStyle('A' . $headerRow . ':G' . $headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $headerRow . ':G' . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F8FC');
        $sheet->getStyle('A' . $totalRow . ':G' . $totalRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $totalRow . ':G' . $totalRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EDF7ED');
        $sheet->getStyle('A' . $totalRow . ':G' . $totalRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $currencyFormat = '"Rp" #,##0;[Red]-"Rp" #,##0';
        $sheet->getStyle('E8:G10')->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('E' . $detailStartRow . ':G' . $totalRow)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle('E8:G' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('E12:G13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
        $sheet->getStyle('A' . $headerRow . ':A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A7:G' . $totalRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A' . $footerRow . ':G' . $footerRow)->getFont()->setItalic(true)->getColor()->setRGB('6B7280');

        $sheet->getColumnDimension('A')->setWidth(11);
        $sheet->getColumnDimension('B')->setWidth(13);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(14);
        $sheet->getColumnDimension('G')->setWidth(13);
        $sheet->freezePane('A' . ($headerRow + 1));
        $sheet->setAutoFilter('A' . $headerRow . ':F' . $totalRow);

        $filePath = tempnam(sys_get_temp_dir(), 'financial_report_');
        (new Xlsx($spreadsheet))->save($filePath);
        $filename = 'financial-report-' . $target['type'] . '-' . str_replace('-', '', (string) $target['period']) . '.xlsx';

        return response()->download(
            $filePath,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function adminFinancialReportPrint(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'financial')) {
            return $this->sidebarPermissionDenied($request);
        }

        $this->ensureExpenseStorageSchema();
        $website = $this->websiteSettings();
        $target = $this->resolveFinancialReportExportTarget($request);
        $snapshot = $this->buildFinancialSnapshot((string) $target['type'], (string) $target['period']);

        return view('admin.financial-report-print', [
            'website' => $website,
            'snapshot' => $snapshot,
            'generatedAt' => date('d M Y H:i'),
            'typeLabel' => ucfirst((string) ($snapshot['type'] ?? '-')),
        ]);
    }

    public function adminFinancialReportExportPdf(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'financial')) {
            return $this->sidebarPermissionDenied($request);
        }
        if (!class_exists('TCPDF')) {
            return redirect()->route('admin.financial')->withErrors([
                'financial' => 'TCPDF is not installed. Please install package tecnickcom/tcpdf first.',
            ]);
        }
        $this->ensureExpenseStorageSchema();
        $website = $this->websiteSettings();
        $target = $this->resolveFinancialReportExportTarget($request);
        $snapshot = $this->buildFinancialSnapshot((string) $target['type'], (string) $target['period']);
        $generatedAt = date('d M Y H:i');
        $typeLabel = ucfirst((string) ($snapshot['type'] ?? '-'));
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator((string) ($website['name'] ?? 'Neoura'));
        $pdf->SetAuthor((string) ($website['name'] ?? 'Neoura'));
        $pdf->SetTitle('Financial Statement');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $logoHtml = '';
        $logoPath = trim((string) ($website['logo_path'] ?? ''));
        $logoFilePath = $logoPath !== '' ? public_path(ltrim($logoPath, '/')) : '';
        if ($logoFilePath !== '' && is_file($logoFilePath) && is_readable($logoFilePath)) {
            $rawLogo = @file_get_contents($logoFilePath);
            if ($rawLogo !== false && $rawLogo !== '') {
                $ext = strtolower((string) pathinfo($logoFilePath, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    'svg' => 'image/svg+xml',
                    default => 'image/png',
                };
                $logoHtml = '<img src="data:' . $mime . ';base64,' . base64_encode($rawLogo) . '" style="max-height:42px; max-width:170px;" />';
            }
        } else {
            $logoUrl = trim((string) ($website['logo_url'] ?? ''));
            if ($logoUrl !== '') {
                $logoHtml = '<img src="' . e($logoUrl) . '" style="max-height:42px; max-width:170px;" />';
            }
        }

        $outcomeRowsHtml = '';
        $index = 1;
        foreach ((array) ($snapshot['outcome_detail_rows'] ?? []) as $detailRow) {
            $rowBg = $index % 2 === 0 ? '#FAFAFA' : '#FFFFFF';
            $outcomeRowsHtml .= '<tr>'
                . '<td style="background:' . $rowBg . '; border:1px solid #D1D5DB; padding:7px; text-align:center;">' . $index . '</td>'
                . '<td style="background:' . $rowBg . '; border:1px solid #D1D5DB; padding:7px;">' . e((string) ($detailRow['label'] ?? '-')) . '</td>'
                . '<td style="background:' . $rowBg . '; border:1px solid #D1D5DB; padding:7px; text-align:right;">' . e((string) ($detailRow['value_label'] ?? 'Rp 0')) . '</td>'
                . '</tr>';
            $index++;
        }
        if ($outcomeRowsHtml === '') {
            $outcomeRowsHtml = '<tr><td colspan="3" style="border:1px solid #D1D5DB; padding:7px;">No outcome detail available for this period.</td></tr>';
        }
        $outcomeRowsHtml .= '<tr>'
            . '<td colspan="2" style="background:#EEF6EE; border:1px solid #D1D5DB; padding:7px; font-weight:bold;">TOTAL OUTCOME</td>'
            . '<td style="background:#EEF6EE; border:1px solid #D1D5DB; padding:7px; text-align:right; font-weight:bold;">' . e((string) ($snapshot['outcome_label'] ?? 'Rp 0')) . '</td>'
            . '</tr>';

        $html = ''
            . '<table cellspacing="0" cellpadding="0" width="100%" style="border:1px solid #D8DEE8;">'
            . '<tr style="background-color:#F3F6FB;">'
            . '<td width="26%" style="padding:12px; border-right:1px solid #D8DEE8; text-align:center; vertical-align:middle;">' . ($logoHtml !== '' ? $logoHtml : '<span style="font-size:10px; color:#9CA3AF;">No Logo</span>') . '</td>'
            . '<td width="74%" style="padding:12px; vertical-align:middle;">'
            . '<h2 style="margin:0 0 4px 0; font-size:18px; color:#0F172A;">' . e((string) ($website['name'] ?? 'Neoura')) . '</h2>'
            . '<div style="font-size:11px; color:#475569;">Financial Statement</div>'
            . '<div style="font-size:10px; color:#6B7280; margin-top:4px;">Generated at ' . e($generatedAt) . '</div>'
            . '</td>'
            . '</tr></table>'
            . '<table cellspacing="0" cellpadding="0" width="100%" style="margin-top:9px; border:1px solid #D8DEE8;">'
            . '<tr><td width="22%" style="padding:7px 9px; color:#475569; border-right:1px solid #D8DEE8; background:#FAFBFD;">Report Type</td><td width="78%" style="padding:7px 9px;">' . e($typeLabel) . '</td></tr>'
            . '<tr><td width="22%" style="padding:7px 9px; color:#475569; border-top:1px solid #D8DEE8; border-right:1px solid #D8DEE8; background:#FAFBFD;">Period</td><td width="78%" style="padding:7px 9px; border-top:1px solid #D8DEE8;">' . e((string) ($snapshot['period_label'] ?? '-')) . '</td></tr>'
            . '</table>'
            . '<table cellspacing="0" cellpadding="0" width="100%" style="margin-top:9px;">'
            . '<tr style="background-color:#E9EEF9;"><td style="border:1px solid #D1D5DB; padding:7px; width:65%; font-weight:bold;">Executive Summary</td><td style="border:1px solid #D1D5DB; padding:7px; text-align:right; width:35%; font-weight:bold;">Amount</td></tr>'
            . '<tr><td style="border:1px solid #D1D5DB; padding:7px;">Total Income</td><td style="border:1px solid #D1D5DB; padding:7px; text-align:right;">' . e((string) ($snapshot['income_label'] ?? 'Rp 0')) . '</td></tr>'
            . '<tr><td style="border:1px solid #D1D5DB; padding:7px; background:#FAFAFA;">Income Description</td><td style="border:1px solid #D1D5DB; padding:7px; text-align:left; background:#FAFAFA;">' . e((string) ($snapshot['income_description'] ?? '-')) . '</td></tr>'
            . '<tr><td style="border:1px solid #D1D5DB; padding:7px;">Total Outcome</td><td style="border:1px solid #D1D5DB; padding:7px; text-align:right;">' . e((string) ($snapshot['outcome_label'] ?? 'Rp 0')) . '</td></tr>'
            . '<tr><td style="border:1px solid #D1D5DB; padding:7px; background:#FAFAFA;">Outcome Description</td><td style="border:1px solid #D1D5DB; padding:7px; text-align:left; background:#FAFAFA;">' . e((string) ($snapshot['outcome_description'] ?? '-')) . '</td></tr>'
            . '<tr><td style="border:1px solid #D1D5DB; padding:7px; font-weight:bold; background:#EEF6EE;">Result</td><td style="border:1px solid #D1D5DB; padding:7px; text-align:right; font-weight:bold; background:#EEF6EE;">' . e((string) ($snapshot['net_label'] ?? 'Rp 0')) . '</td></tr>'
            . '</table>'
            . '<h3 style="margin:11px 0 6px; font-size:12px; color:#0F172A;">Outcome Detail</h3>'
            . '<table cellspacing="0" cellpadding="0" width="100%"><thead><tr>'
            . '<th style="border:1px solid #D1D5DB; padding:7px; background:#F3F6FB; text-align:center; width:10%;">No</th>'
            . '<th style="border:1px solid #D1D5DB; padding:7px; background:#F3F6FB; text-align:left; width:60%;">Expense Category</th>'
            . '<th style="border:1px solid #D1D5DB; padding:7px; background:#F3F6FB; text-align:right; width:30%;">Amount (Rp)</th>'
            . '</tr></thead><tbody>' . $outcomeRowsHtml . '</tbody></table>'
            . '<p style="font-size:9px; color:#6B7280; margin-top:7px;">This document is system-generated and all values are presented in Indonesian Rupiah (IDR).</p>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $binary = $pdf->Output('financial-report.pdf', 'S');
        $filename = 'financial-report-' . $target['type'] . '-' . str_replace('-', '', (string) $target['period']) . '.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function resolveFinancialReportExportTarget(Request $request): array
    {
        $type = strtolower(trim((string) $request->query('type', 'daily')));
        if (!in_array($type, ['daily', 'monthly', 'yearly'], true)) {
            $type = 'daily';
        }

        $period = trim((string) $request->query('period', ''));
        if ($type === 'daily' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) === 1) {
            return ['type' => $type, 'period' => $period];
        }
        if ($type === 'monthly' && preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
            return ['type' => $type, 'period' => $period];
        }
        if ($type === 'yearly' && preg_match('/^\d{4}$/', $period) === 1) {
            return ['type' => $type, 'period' => $period];
        }

        $filters = $this->resolveFinancialReportFilters($request);
        if ($type === 'monthly') {
            return [
                'type' => $type,
                'period' => sprintf('%04d-%02d', (int) $filters['selectedYear'], (int) $filters['selectedMonth']),
            ];
        }
        if ($type === 'yearly') {
            return [
                'type' => $type,
                'period' => sprintf('%04d', (int) $filters['selectedYear']),
            ];
        }

        return [
            'type' => 'daily',
            'period' => date('Y-m-d'),
        ];
    }

    private function buildFinancialSnapshot(string $type, string $period): array
    {
        $normalizedType = strtolower(trim($type));
        if (!in_array($normalizedType, ['daily', 'monthly', 'yearly'], true)) {
            $normalizedType = 'daily';
        }

        $incomeEntries = $this->approvedPaymentIncomeEntries();
        $income = 0;
        $incomeTransactions = 0;
        $outcome = 0;
        $outcomeDetailRows = [];
        $periodLabel = $period;

        if ($normalizedType === 'yearly') {
            $year = (int) $period;
            if ($year < 2000 || $year > 3000) {
                $year = (int) date('Y');
            }
            $period = sprintf('%04d', $year);
            $periodLabel = $period;

            foreach ($incomeEntries as $entry) {
                $entryDate = (string) ($entry['payment_date'] ?? '');
                if (substr($entryDate, 0, 4) === $period) {
                    $income += (int) ($entry['amount'] ?? 0);
                    $incomeTransactions++;
                }
            }

            for ($month = 1; $month <= 12; $month++) {
                $monthOutcome = $this->expenseTotalForMonth($year, $month);
                $outcome += $monthOutcome;
                $outcomeDetailRows[] = [
                    'label' => date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year,
                    'value' => $monthOutcome,
                    'value_label' => $this->formatRupiah($monthOutcome),
                ];
            }
        } elseif ($normalizedType === 'monthly') {
            if (preg_match('/^(\d{4})-(\d{2})$/', $period, $matches) !== 1) {
                $period = date('Y-m');
                preg_match('/^(\d{4})-(\d{2})$/', $period, $matches);
            }
            $year = (int) ($matches[1] ?? date('Y'));
            $month = (int) ($matches[2] ?? date('m'));
            if ($month < 1 || $month > 12) {
                $month = (int) date('n');
            }
            $period = sprintf('%04d-%02d', $year, $month);
            $periodLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));

            foreach ($incomeEntries as $entry) {
                $entryDate = (string) ($entry['payment_date'] ?? '');
                if (substr($entryDate, 0, 7) === $period) {
                    $income += (int) ($entry['amount'] ?? 0);
                    $incomeTransactions++;
                }
            }

            $expenseRows = $this->expenseRows($year, $month);
            foreach ($expenseRows as $expenseRow) {
                $value = (int) ($expenseRow['cost_value'] ?? 0);
                $outcome += $value;
                $outcomeDetailRows[] = [
                    'label' => (string) ($expenseRow['expensename'] ?? '-'),
                    'value' => $value,
                    'value_label' => $this->formatRupiah($value),
                ];
            }
        } else {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) !== 1) {
                $period = date('Y-m-d');
            }
            $timestamp = strtotime($period);
            if ($timestamp === false) {
                $period = date('Y-m-d');
                $timestamp = strtotime($period);
            }
            $year = (int) date('Y', $timestamp ?: time());
            $month = (int) date('n', $timestamp ?: time());
            $daysInMonth = max(1, (int) cal_days_in_month(CAL_GREGORIAN, $month, $year));
            $periodLabel = date('d F Y', $timestamp ?: time());

            foreach ($incomeEntries as $entry) {
                if ((string) ($entry['payment_date'] ?? '') === $period) {
                    $income += (int) ($entry['amount'] ?? 0);
                    $incomeTransactions++;
                }
            }

            $expenseRows = $this->expenseRows($year, $month);
            foreach ($expenseRows as $expenseRow) {
                $monthlyCost = (int) ($expenseRow['cost_value'] ?? 0);
                $dailyShare = (int) round($monthlyCost / $daysInMonth);
                $outcome += $dailyShare;
                $outcomeDetailRows[] = [
                    'label' => (string) ($expenseRow['expensename'] ?? '-') . ' / day',
                    'value' => $dailyShare,
                    'value_label' => $this->formatRupiah($dailyShare),
                ];
            }
        }

        $net = $income - $outcome;
        $expenseItemCount = count($outcomeDetailRows);
        $incomeDescription = sprintf(
            'Accumulated from %d approved payment transaction(s) for %s period (%s).',
            $incomeTransactions,
            $periodLabel,
            ucfirst($normalizedType)
        );
        $outcomeDescription = $expenseItemCount > 0
            ? sprintf(
                'Accumulated operational outcome from %d expense item(s) allocated in %s.',
                $expenseItemCount,
                $periodLabel
            )
            : sprintf('No expense item recorded for %s.', $periodLabel);
        $resultDescription = sprintf(
            'Result is calculated from %s - %s = %s.',
            $this->formatRupiah($income),
            $this->formatRupiah($outcome),
            $this->formatRupiah($net)
        );

        return [
            'type' => $normalizedType,
            'period' => $period,
            'period_label' => $periodLabel,
            'income' => $income,
            'income_label' => $this->formatRupiah($income),
            'outcome' => $outcome,
            'outcome_label' => $this->formatRupiah($outcome),
            'net' => $net,
            'net_label' => $this->formatRupiah($net),
            'income_description' => $incomeDescription,
            'outcome_description' => $outcomeDescription,
            'result_description' => $resultDescription,
            'outcome_detail_rows' => $outcomeDetailRows,
        ];
    }

    private function resolveFinancialReportFilters(Request $request): array
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        $reportType = strtolower(trim((string) $request->query('type', 'daily')));
        if (!in_array($reportType, ['daily', 'monthly', 'yearly'], true)) {
            $reportType = 'daily';
        }

        $selectedYear = (int) $request->query('year', $currentYear);
        if ($selectedYear < 2000 || $selectedYear > 3000) {
            $selectedYear = $currentYear;
        }

        $selectedMonth = (int) $request->query('month', $currentMonth);
        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = $currentMonth;
        }

        return [
            'reportType' => $reportType,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
        ];
    }

    private function buildFinancialReportData(int $selectedYear, int $selectedMonth): array
    {
        $currentYear = (int) date('Y');
        $entries = $this->approvedPaymentIncomeEntries();
        $daily = [];
        $monthly = [];
        $yearly = [];

        foreach ($entries as $entry) {
            $dateKey = (string) ($entry['payment_date'] ?? '');
            $amount = (int) ($entry['amount'] ?? 0);
            if ($dateKey === '' || $amount < 0) {
                continue;
            }

            $year = (int) substr($dateKey, 0, 4);
            $month = (int) substr($dateKey, 5, 2);
            $monthKey = sprintf('%04d-%02d', $year, $month);
            $yearKey = sprintf('%04d', $year);

            if ($year === $selectedYear && $month === $selectedMonth) {
                if (!isset($daily[$dateKey])) {
                    $daily[$dateKey] = ['transactions' => 0, 'income' => 0];
                }
                $daily[$dateKey]['transactions']++;
                $daily[$dateKey]['income'] += $amount;
            }

            if ($year === $selectedYear) {
                if (!isset($monthly[$monthKey])) {
                    $monthly[$monthKey] = ['transactions' => 0, 'income' => 0];
                }
                $monthly[$monthKey]['transactions']++;
                $monthly[$monthKey]['income'] += $amount;
            }

            if (!isset($yearly[$yearKey])) {
                $yearly[$yearKey] = ['transactions' => 0, 'income' => 0];
            }
            $yearly[$yearKey]['transactions']++;
            $yearly[$yearKey]['income'] += $amount;
        }

        krsort($daily);
        krsort($monthly);
        krsort($yearly);

        $dailyRows = collect($daily)->map(function ($row, $dateKey) {
            $timestamp = strtotime((string) $dateKey);
            return [
                'label' => $timestamp !== false ? date('d M Y', $timestamp) : (string) $dateKey,
                'period' => (string) $dateKey,
                'transactions' => (int) ($row['transactions'] ?? 0),
                'income' => (int) ($row['income'] ?? 0),
                'income_label' => $this->formatRupiah((int) ($row['income'] ?? 0)),
            ];
        })->values()->all();

        $monthlyRows = collect($monthly)->map(function ($row, $monthKey) {
            $timestamp = strtotime((string) $monthKey . '-01');
            return [
                'label' => $timestamp !== false ? date('F Y', $timestamp) : (string) $monthKey,
                'period' => (string) $monthKey,
                'transactions' => (int) ($row['transactions'] ?? 0),
                'income' => (int) ($row['income'] ?? 0),
                'income_label' => $this->formatRupiah((int) ($row['income'] ?? 0)),
            ];
        })->values()->all();

        $yearlyRows = collect($yearly)->map(function ($row, $yearKey) {
            return [
                'label' => (string) $yearKey,
                'period' => (string) $yearKey,
                'transactions' => (int) ($row['transactions'] ?? 0),
                'income' => (int) ($row['income'] ?? 0),
                'income_label' => $this->formatRupiah((int) ($row['income'] ?? 0)),
            ];
        })->values()->all();

        $yearOptions = collect($entries)
            ->map(fn($entry) => (int) substr((string) ($entry['payment_date'] ?? ''), 0, 4))
            ->filter(fn($year) => $year >= 2000 && $year <= 3000)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if (empty($yearOptions)) {
            $yearOptions = [$currentYear];
        } elseif (!in_array($selectedYear, $yearOptions, true)) {
            $yearOptions[] = $selectedYear;
            rsort($yearOptions);
            $yearOptions = array_values(array_unique($yearOptions));
        }

        $selectedMonthKey = sprintf('%04d-%02d', $selectedYear, $selectedMonth);
        $selectedMonthSummary = $monthly[$selectedMonthKey] ?? ['transactions' => 0, 'income' => 0];
        $selectedYearSummary = array_reduce($monthly, function ($carry, $row) {
            $carry['transactions'] += (int) ($row['transactions'] ?? 0);
            $carry['income'] += (int) ($row['income'] ?? 0);
            return $carry;
        }, ['transactions' => 0, 'income' => 0]);
        $allTimeSummary = array_reduce($yearly, function ($carry, $row) {
            $carry['transactions'] += (int) ($row['transactions'] ?? 0);
            $carry['income'] += (int) ($row['income'] ?? 0);
            return $carry;
        }, ['transactions' => 0, 'income' => 0]);

        $expenseRows = $this->expenseRows($selectedYear, $selectedMonth);
        $totalExpense = array_reduce($expenseRows, function ($carry, $row) {
            $carry += (int) ($row['cost_value'] ?? 0);
            return $carry;
        }, 0);
        $netIncome = (int) ($allTimeSummary['income'] ?? 0) - $totalExpense;

        return [
            'yearOptions' => $yearOptions,
            'dailyRows' => $dailyRows,
            'monthlyRows' => $monthlyRows,
            'yearlyRows' => $yearlyRows,
            'expenseRows' => $expenseRows,
            'totalExpenseValue' => $totalExpense,
            'totalExpenseLabel' => $this->formatRupiah($totalExpense),
            'netIncomeLabel' => $this->formatRupiah(max(0, $netIncome)),
            'monthName' => date('F', mktime(0, 0, 0, $selectedMonth, 1)),
            'summaryCards' => [
                [
                    'label' => 'Income ' . date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)),
                    'transactions' => (int) ($selectedMonthSummary['transactions'] ?? 0),
                    'income_label' => $this->formatRupiah((int) ($selectedMonthSummary['income'] ?? 0)),
                    'meta' => 'approved transaction(s)',
                ],
                [
                    'label' => 'Income Year ' . $selectedYear,
                    'transactions' => (int) ($selectedYearSummary['transactions'] ?? 0),
                    'income_label' => $this->formatRupiah((int) ($selectedYearSummary['income'] ?? 0)),
                    'meta' => 'approved transaction(s)',
                ],
                [
                    'label' => 'Income All Time',
                    'transactions' => (int) ($allTimeSummary['transactions'] ?? 0),
                    'income_label' => $this->formatRupiah((int) ($allTimeSummary['income'] ?? 0)),
                    'meta' => 'approved transaction(s)',
                ],
                [
                    'label' => 'Outcome All Time',
                    'transactions' => count($expenseRows),
                    'income_label' => $this->formatRupiah($totalExpense),
                    'meta' => 'expense entry(ies)',
                ],
                [
                    'label' => 'Net Income',
                    'transactions' => 0,
                    'income_label' => $this->formatRupiah(max(0, $netIncome)),
                    'meta' => 'income - outcome',
                ],
            ],
        ];
    }

    private function resolveFinancialReportTableData(string $reportType, array $reportData): array
    {
        $type = strtolower(trim($reportType));
        if (!in_array($type, ['daily', 'monthly', 'yearly'], true)) {
            $type = 'daily';
        }

        if ($type === 'monthly') {
            $rows = collect($reportData['monthlyRows'] ?? [])->map(fn($row) => [
                (string) ($row['label'] ?? '-'),
                (int) ($row['transactions'] ?? 0),
                (string) ($row['income_label'] ?? 'Rp 0'),
            ])->values()->all();

            return [
                'title' => 'Monthly Report',
                'headers' => ['Month', 'Transactions', 'Income'],
                'rows' => $rows,
                'empty_message' => 'No approved payment data for selected year.',
            ];
        }

        if ($type === 'yearly') {
            $rows = collect($reportData['yearlyRows'] ?? [])->map(fn($row) => [
                (string) ($row['label'] ?? '-'),
                (int) ($row['transactions'] ?? 0),
                (string) ($row['income_label'] ?? 'Rp 0'),
            ])->values()->all();

            return [
                'title' => 'Yearly Report',
                'headers' => ['Year', 'Transactions', 'Income'],
                'rows' => $rows,
                'empty_message' => 'No approved payment data yet.',
            ];
        }

        $rows = collect($reportData['dailyRows'] ?? [])->map(fn($row) => [
            (string) ($row['label'] ?? '-'),
            (int) ($row['transactions'] ?? 0),
            (string) ($row['income_label'] ?? 'Rp 0'),
        ])->values()->all();

        return [
            'title' => 'Daily Report',
            'headers' => ['Date', 'Transactions', 'Income'],
            'rows' => $rows,
            'empty_message' => 'No approved payment data for selected month.',
        ];
    }


    public function adminFinancialExpenseStore(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'financial')) {
            return $this->sidebarPermissionDenied($request);
        }

        $this->ensureExpenseStorageSchema();

        $expenseInputs = $request->input('expenses', []);
        if (!is_array($expenseInputs)) {
            $expenseInputs = [];
        }
        if (count($expenseInputs) < 1) {
            $expenseInputs = [[
                'name' => (string) $request->input('expense_name', ''),
                'cost' => (string) $request->input('expense_cost', ''),
            ]];
        }

        $expenseYear = (int) $request->input('expense_year', date('Y'));
        $expenseMonth = (int) $request->input('expense_month', date('n'));
        if ($expenseYear < 2000 || $expenseYear > 3000) {
            $expenseYear = (int) date('Y');
        }
        if ($expenseMonth < 1 || $expenseMonth > 12) {
            $expenseMonth = (int) date('n');
        }

        $rowsToInsert = [];
        foreach ($expenseInputs as $input) {
            if (!is_array($input)) {
                continue;
            }

            $expenseName = trim((string) ($input['name'] ?? ''));
            $expenseCost = trim((string) ($input['cost'] ?? ''));
            if ($expenseName === '' && $expenseCost === '') {
                continue;
            }

            if (mb_strlen($expenseName) > 255 || mb_strlen($expenseCost) > 255) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Expense name or amount is too long.',
                    ], 422);
                }
                return redirect()->route('admin.financial')
                    ->withErrors(['expense' => 'Expense name or amount is too long.'])
                    ->withInput();
            }

            $amount = $this->parseRupiahAmount($expenseCost);
            if ($expenseName === '' || $amount <= 0) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Every expense row must have a name and amount greater than 0.',
                    ], 422);
                }
                return redirect()->route('admin.financial')
                    ->withErrors(['expense' => 'Every expense row must have a name and amount greater than 0.'])
                    ->withInput();
            }

            $rowsToInsert[] = [
                'expensename' => $expenseName,
                'cost' => (string) $amount,
                'expense_year' => $expenseYear,
                'expense_month' => $expenseMonth,
            ];
        }

        if (count($rowsToInsert) < 1) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Add at least one expense row before saving.',
                ], 422);
            }
            return redirect()->route('admin.financial')
                ->withErrors(['expense' => 'Add at least one expense row before saving.'])
                ->withInput();
        }

        $insertedRows = [];
        foreach ($rowsToInsert as $row) {
            $insertId = (int) DB::connection('mysql')
                ->table('neoura.expense')
                ->insertGetId($row);

            $costValue = (int) ($row['cost'] ?? 0);
            $insertedRows[] = [
                'expenseid' => $insertId,
                'expensename' => (string) ($row['expensename'] ?? ''),
                'cost_raw' => (string) ($row['cost'] ?? '0'),
                'cost_value' => $costValue,
                'cost_label' => $this->formatRupiah($costValue),
                'expense_year' => (int) ($row['expense_year'] ?? 0),
                'expense_month' => (int) ($row['expense_month'] ?? 0),
            ];
        }

        if ($request->expectsJson() || $request->ajax()) {
            $totalExpense = $this->expenseTotalForMonth($expenseYear, $expenseMonth);
            return response()->json([
                'status' => 'ok',
                'message' => count($insertedRows) . ' expense row(s) added.',
                'rows' => $insertedRows,
                'total_expense_value' => $totalExpense,
                'total_expense_label' => $this->formatRupiah($totalExpense),
            ]);
        }

        return redirect()->route('admin.financial')->with('status', count($rowsToInsert) . ' expense row(s) added.');
    }

    public function adminFinancialExpenseUpdate(Request $request, int $expenseid)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'financial')) {
            return $this->sidebarPermissionDenied($request);
        }

        $this->ensureExpenseStorageSchema();

        $validated = $request->validate([
            'expense_name' => ['required', 'string', 'max:255'],
            'expense_cost' => ['required', 'string', 'max:255'],
        ]);

        $expenseName = trim((string) ($validated['expense_name'] ?? ''));
        $expenseCost = trim((string) ($validated['expense_cost'] ?? ''));
        $amount = $this->parseRupiahAmount($expenseCost);
        if ($expenseName === '' || $amount <= 0) {
            return redirect()->route('admin.financial')
                ->withErrors(['expense' => 'Expense name is required and amount must be greater than 0.'])
                ->withInput();
        }

        $updated = DB::connection('mysql')
            ->table('neoura.expense')
            ->where('expenseid', $expenseid)
            ->update([
                'expensename' => $expenseName,
                'cost' => (string) $amount,
            ]);

        if ($updated < 1) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Expense row not found.',
                ], 404);
            }
            return redirect()->route('admin.financial')->withErrors(['expense' => 'Expense row not found.']);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Expense row updated.',
                'expense' => [
                    'expenseid' => $expenseid,
                    'name' => $expenseName,
                    'cost_raw' => (string) $amount,
                    'cost_label' => $this->formatRupiah($amount),
                ],
            ]);
        }

        return redirect()->route('admin.financial')->with('status', 'Expense row updated.');
    }

    public function adminFinancialExpenseDelete(Request $request, int $expenseid)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'financial')) {
            return $this->sidebarPermissionDenied($request);
        }

        $this->ensureExpenseStorageSchema();

        $row = DB::connection('mysql')
            ->table('neoura.expense')
            ->select('expenseid', 'expense_year', 'expense_month')
            ->where('expenseid', $expenseid)
            ->first();

        if (!$row) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Expense row not found.',
                ], 404);
            }

            return redirect()->route('admin.financial')->withErrors(['expense' => 'Expense row not found.']);
        }

        DB::connection('mysql')
            ->table('neoura.expense')
            ->where('expenseid', $expenseid)
            ->delete();

        $year = (int) ($row->expense_year ?? date('Y'));
        $month = (int) ($row->expense_month ?? date('n'));
        $totalExpense = $this->expenseTotalForMonth($year, $month);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Expense row deleted.',
                'expenseid' => $expenseid,
                'total_expense_value' => $totalExpense,
                'total_expense_label' => $this->formatRupiah($totalExpense),
            ]);
        }

        return redirect()->route('admin.financial')->with('status', 'Expense row deleted.');
    }

    public function account(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }

        $website = $this->websiteSettings();
        $sidebarServices = $this->sidebarServices();

        $userRow = DB::connection('mysql')
            ->table('neoura.user as u')
            ->leftJoin('neoura.employer as e', 'e.userid', '=', 'u.userid')
            ->select(
                'u.userid',
                'u.username',
                'u.password',
                'u.levelid',
                'e.employerid',
                'e.name as employer_name',
                'e.email as employer_email',
                'e.phonenumber as employer_phone'
            )
            ->where('u.userid', $adminAuth['userid'] ?? 0)
            ->first();

        if (!$userRow) {
            return redirect()->route('home')->withErrors(['account' => 'Account not found.']);
        }

        $data = [
            'title' => 'Account | ' . $website['name'],
            'adminAuth' => $adminAuth,
            'showAdminMenu' => true,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'sidebarServices' => $sidebarServices,
            'website' => $website,
            'pageScript' => 'account.js',
            'accountProfile' => [
                'userid' => (int) $userRow->userid,
                'employerid' => $userRow->employerid ? (int) $userRow->employerid : null,
                'username' => (string) ($userRow->username ?? ''),
                'name' => (string) ($userRow->employer_name ?? ''),
                'email' => (string) ($userRow->employer_email ?? ''),
                'phone' => (string) ($userRow->employer_phone ?? ''),
            ],
        ];

        $this->renderParts(['all.header', 'all.menu', 'all.account', 'all.footer'], $data);
    }

    public function accountSendPhoneOtp(Request $request): JsonResponse
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'phonenumber' => ['required', 'string', 'max:255'],
        ]);

        $targetPhone = trim((string) $validated['phonenumber']);
        if ($targetPhone === '') {
            return response()->json(['status' => 'error', 'message' => 'Phone number is required.'], 422);
        }

        $cooldownUntil = (int) $request->session()->get('account_phone_otp_cooldown_until', 0);
        if ($cooldownUntil > time()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please wait before requesting another OTP.',
                'retry_after' => $cooldownUntil - time(),
            ], 429);
        }

        $otp = (string) random_int(100000, 999999);
        $result = $this->sendWhatsAppMessage($targetPhone, $this->phoneOtpMessageText($otp));
        if (!(bool) ($result['sent'] ?? false)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send OTP via WhatsApp. ' . ((string) ($result['reason'] ?? '')),
            ], 422);
        }

        $request->session()->put('account_phone_otp', [
            'phone' => $targetPhone,
            'otp_hash' => hash('sha256', $otp),
            'expires_at' => time() + 300,
            'verified' => false,
        ]);
        $request->session()->put('account_phone_otp_cooldown_until', time() + 30);

        return response()->json([
            'status' => 'ok',
            'message' => 'OTP has been sent to the new phone number.',
        ]);
    }

    public function accountVerifyPhoneOtp(Request $request): JsonResponse
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'phonenumber' => ['required', 'string', 'max:255'],
            'otp_code' => ['required', 'regex:/^\d{6}$/'],
        ]);

        $otpSession = $request->session()->get('account_phone_otp');
        if (!is_array($otpSession)) {
            return response()->json(['status' => 'error', 'message' => 'OTP session not found. Please request OTP again.'], 422);
        }

        $targetPhone = trim((string) $validated['phonenumber']);
        if (!hash_equals((string) ($otpSession['phone'] ?? ''), $targetPhone)) {
            return response()->json(['status' => 'error', 'message' => 'Phone number does not match OTP target.'], 422);
        }

        if ((int) ($otpSession['expires_at'] ?? 0) < time()) {
            return response()->json(['status' => 'error', 'message' => 'OTP expired. Please request a new OTP.'], 422);
        }

        $otpHash = hash('sha256', (string) $validated['otp_code']);
        if (!hash_equals((string) ($otpSession['otp_hash'] ?? ''), $otpHash)) {
            return response()->json(['status' => 'error', 'message' => 'OTP code is invalid.'], 422);
        }

        $otpSession['verified'] = true;
        $request->session()->put('account_phone_otp', $otpSession);

        return response()->json([
            'status' => 'ok',
            'message' => 'OTP verified successfully.',
        ]);
    }

    public function accountUpdate(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }

        $isAjaxRequest = $request->expectsJson() || $request->ajax();
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phonenumber' => ['required', 'string', 'max:255'],
            'current_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);
        if ($validator->fails()) {
            if ($isAjaxRequest) {
                return response()->json([
                    'message' => (string) $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()
                ->route('account')
                ->withErrors($validator)
                ->withInput();
        }
        $validated = $validator->validated();

        $userRow = DB::connection('mysql')
            ->table('neoura.user as u')
            ->leftJoin('neoura.employer as e', 'e.userid', '=', 'u.userid')
            ->select('u.userid', 'u.password', 'e.employerid', 'e.email as employer_email', 'e.phonenumber as employer_phone')
            ->where('u.userid', $adminAuth['userid'] ?? 0)
            ->first();

        if (!$userRow) {
            if ($isAjaxRequest) {
                return response()->json(['message' => 'Account not found.'], 404);
            }
            return redirect()->route('account')->withErrors(['account' => 'Account not found.']);
        }

        $newPassword = (string) ($validated['new_password'] ?? '');
        $currentPhone = trim((string) ($userRow->employer_phone ?? ''));
        $nextPhone = trim((string) ($validated['phonenumber'] ?? ''));
        $isPhoneChanged = $nextPhone !== '' && !hash_equals($currentPhone, $nextPhone);
        $currentEmail = trim((string) ($userRow->employer_email ?? ''));
        $nextEmail = trim((string) ($validated['email'] ?? ''));
        $isEmailChanged = $nextEmail !== '' && !hash_equals(strtolower($currentEmail), strtolower($nextEmail));

        if ($isEmailChanged) {
            $emailUsedByAnotherUser = DB::connection('mysql')
                ->table('neoura.employer')
                ->whereRaw('LOWER(email) = ?', [strtolower($nextEmail)])
                ->where('userid', '!=', (int) $userRow->userid)
                ->exists();

            if ($emailUsedByAnotherUser) {
                if ($isAjaxRequest) {
                    return response()->json(['message' => 'Email is already used by another account.'], 422);
                }
                return back()->withErrors(['email' => 'Email is already used by another account.'])->withInput();
            }
        }

        if ($isPhoneChanged) {
            $otpSession = $request->session()->get('account_phone_otp');
            $verified = is_array($otpSession)
                && hash_equals((string) ($otpSession['phone'] ?? ''), $nextPhone)
                && (bool) ($otpSession['verified'] ?? false)
                && (int) ($otpSession['expires_at'] ?? 0) >= time();

            if (!$verified) {
                if ($isAjaxRequest) {
                    return response()->json(['message' => 'Please verify OTP for the new phone number first.'], 422);
                }
                return back()->withErrors(['phonenumber' => 'Please verify OTP for the new phone number first.'])->withInput();
            }
        }

        if ($newPassword !== '') {
            $currentPassword = (string) ($validated['current_password'] ?? '');
            if ($currentPassword === '') {
                if ($isAjaxRequest) {
                    return response()->json(['message' => 'Current password is required to set a new password.'], 422);
                }
                return back()->withErrors(['current_password' => 'Current password is required to set a new password.'])->withInput();
            }

            $storedPassword = (string) ($userRow->password ?? '');
            $passwordValid = Hash::check($currentPassword, $storedPassword) || hash_equals($storedPassword, $currentPassword);
            if (!$passwordValid) {
                if ($isAjaxRequest) {
                    return response()->json(['message' => 'Current password is incorrect.'], 422);
                }
                return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
            }
        }

        DB::connection('mysql')->transaction(function () use ($userRow, $validated, $newPassword, $nextEmail, $isEmailChanged) {
            if ($newPassword !== '') {
                DB::connection('mysql')
                    ->table('neoura.user')
                    ->where('userid', $userRow->userid)
                    ->update([
                        'password' => Hash::make($newPassword),
                    ]);
            }

            $payload = [
                'name' => $validated['name'],
                'email' => $isEmailChanged ? (string) ($userRow->employer_email ?? '') : $nextEmail,
                'phonenumber' => $validated['phonenumber'],
                'userid' => $userRow->userid,
            ];

            if (!empty($userRow->employerid)) {
                DB::connection('mysql')
                    ->table('neoura.employer')
                    ->where('employerid', $userRow->employerid)
                    ->update($payload);
            } else {
                DB::connection('mysql')
                    ->table('neoura.employer')
                    ->insert($payload);
            }
        });

        $emailVerificationSent = false;
        $emailVerificationReason = '';
        if ($isEmailChanged) {
            $emailQueueResult = $this->queueAccountEmailChangeVerification((int) $userRow->userid, $nextEmail);
            $emailVerificationSent = (bool) ($emailQueueResult['sent'] ?? false);
            $emailVerificationReason = (string) ($emailQueueResult['reason'] ?? '');
        }

        $request->session()->put('admin_auth', array_merge($adminAuth, [
            'employer_name' => $validated['name'],
            'employer_email' => $isEmailChanged ? $currentEmail : $nextEmail,
            'employer_phone' => $validated['phonenumber'],
        ]));

        if ($isPhoneChanged) {
            $request->session()->forget(['account_phone_otp', 'account_phone_otp_cooldown_until']);
        }

        if ($isAjaxRequest) {
            return response()->json([
                'status' => 'ok',
                'message' => $isEmailChanged
                    ? (
                        $emailVerificationSent
                            ? 'Account updated. Please verify your new email using the link we sent.'
                            : ('Account updated, but we failed to send verification email. ' . $emailVerificationReason)
                    )
                    : 'Account updated.',
            ]);
        }

        if ($isEmailChanged) {
            return redirect()->route('account')->with(
                'status',
                $emailVerificationSent
                    ? 'Account updated. Please verify your new email using the link we sent.'
                    : ('Account updated, but we failed to send verification email. ' . $emailVerificationReason)
            );
        }

        return redirect()->route('account')->with('status', 'Account updated.');
    }

    public function accountVerifyEmailChange(Request $request, string $token)
    {
        $tokenValue = trim($token);
        $website = $this->websiteSettings();

        if ($tokenValue === '') {
            return response()->view('errors.404', ['website' => $website], 404);
        }

        $rows = $this->pruneAccountEmailChangeRequests($this->accountEmailChangeRequests());
        $this->saveAccountEmailChangeRequests($rows);

        $target = null;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (hash_equals((string) ($row['token'] ?? ''), $tokenValue)) {
                $target = $row;
                break;
            }
        }

        if (!$target) {
            return response()->view('errors.404', ['website' => $website], 404);
        }

        $userId = (int) ($target['userid'] ?? 0);
        $nextEmail = trim((string) ($target['next_email'] ?? ''));
        if ($userId <= 0 || $nextEmail === '') {
            return response()->view('errors.404', ['website' => $website], 404);
        }

        $emailUsedByAnotherUser = DB::connection('mysql')
            ->table('neoura.employer')
            ->whereRaw('LOWER(email) = ?', [strtolower($nextEmail)])
            ->where('userid', '!=', $userId)
            ->exists();
        if ($emailUsedByAnotherUser) {
            return response()->view('errors.404', ['website' => $website], 404);
        }

        DB::connection('mysql')
            ->table('neoura.employer')
            ->where('userid', $userId)
            ->update(['email' => $nextEmail]);

        // Remove all pending email-change tokens for this user after successful verification.
        $remainingRows = array_values(array_filter($rows, function ($row) use ($userId) {
            if (!is_array($row)) {
                return false;
            }
            return (int) ($row['userid'] ?? 0) !== $userId;
        }));
        $this->saveAccountEmailChangeRequests($remainingRows);

        $adminAuth = $request->session()->get('admin_auth');
        if (is_array($adminAuth) && (int) ($adminAuth['userid'] ?? 0) === $userId) {
            $request->session()->put('admin_auth', array_merge($adminAuth, [
                'employer_email' => $nextEmail,
            ]));
        }

        $data = [
            'title' => 'Email Verification Success | ' . $website['name'],
            'website' => $website,
            'redirectUrl' => route('account'),
            'redirectSeconds' => 5,
            'pageScript' => 'account-email-change-success.js',
        ];

        $this->renderParts(['all.header', 'all.account-email-change-success', 'all.footer'], $data);
    }

    private function forgotPasswordEmailRequestsFile(): string
    {
        return storage_path('app/forgot-password-email-requests.json');
    }

    private function forgotPasswordEmailRequests(): array
    {
        $file = $this->forgotPasswordEmailRequestsFile();
        if (!is_file($file)) {
            return [];
        }

        $raw = file_get_contents($file);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn($row) => is_array($row))
            ->values()
            ->all();
    }

    private function saveForgotPasswordEmailRequests(array $rows): void
    {
        $normalized = array_values(
            collect($rows)
                ->filter(fn($row) => is_array($row))
                ->take(1000)
                ->all()
        );

        file_put_contents(
            $this->forgotPasswordEmailRequestsFile(),
            json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function pruneForgotPasswordEmailRequests(array $rows): array
    {
        $nowTs = time();
        return array_values(array_filter($rows, function ($row) use ($nowTs) {
            if (!is_array($row)) {
                return false;
            }

            return (int) ($row['expires_at_ts'] ?? 0) >= $nowTs;
        }));
    }

    private function forgotPasswordUserByEmail(string $email): ?object
    {
        $target = strtolower(trim($email));
        if ($target === '') {
            return null;
        }

        $user = DB::connection('mysql')
            ->table('neoura.user as u')
            ->join('neoura.employer as e', 'e.userid', '=', 'u.userid')
            ->select('u.userid', 'u.username', 'e.email', 'e.phonenumber as employer_phone')
            ->whereRaw('LOWER(e.email) = ?', [$target])
            ->first();

        return $user ?: null;
    }

    private function forgotPasswordUserByPhone(string $phone): ?object
    {
        $targetNormalized = $this->normalizeWhatsAppNumber($phone);
        $targetDigits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if ($targetNormalized === '' && $targetDigits === '') {
            return null;
        }

        $users = DB::connection('mysql')
            ->table('neoura.user as u')
            ->join('neoura.employer as e', 'e.userid', '=', 'u.userid')
            ->select('u.userid', 'u.username', 'e.email', 'e.phonenumber as employer_phone')
            ->whereNotNull('e.phonenumber')
            ->where('e.phonenumber', '!=', '')
            ->get();

        foreach ($users as $user) {
            $rowPhone = (string) ($user->employer_phone ?? '');
            $rowNormalized = $this->normalizeWhatsAppNumber($rowPhone);
            $rowDigits = preg_replace('/\D+/', '', trim($rowPhone)) ?? '';

            if ($targetNormalized !== '' && $rowNormalized !== '' && hash_equals($targetNormalized, $rowNormalized)) {
                return $user;
            }

            if ($targetDigits !== '' && $rowDigits !== '') {
                if (hash_equals($targetDigits, $rowDigits)) {
                    return $user;
                }
                if (hash_equals(ltrim($targetDigits, '0'), ltrim($rowDigits, '0'))) {
                    return $user;
                }
            }
        }

        return null;
    }

    private function queueForgotPasswordEmailReset(int $userId, string $email): array
    {
        $targetEmail = trim($email);
        if ($userId <= 0 || $targetEmail === '') {
            return [
                'sent' => false,
                'reason' => 'Invalid password reset request.',
            ];
        }

        $token = (string) Str::random(64);
        $expiresAt = now()->addMinutes(30);
        $resetLink = route('password.forgot.email.reset', ['token' => $token]);
        $appName = trim((string) config('app.name', 'Neora Color Studio')) ?: 'Neora Color Studio';

        $message = implode("\n", [
            "Hello,",
            "",
            "We received a request to reset your {$appName} account password.",
            "",
            "Reset your password using this secure link:",
            $resetLink,
            "",
            "This link expires at " . $expiresAt->format('d M Y H:i:s T') . ".",
            "If you did not request this reset, you can ignore this email.",
            "",
            "Regards,",
            "{$appName} Support Team",
        ]);

        $mailResult = $this->sendBookingEmail($targetEmail, 'Password Reset Request', $message);
        if (!(bool) ($mailResult['sent'] ?? false)) {
            return $mailResult;
        }

        $rows = $this->pruneForgotPasswordEmailRequests($this->forgotPasswordEmailRequests());
        $rows = array_values(array_filter($rows, function ($row) use ($userId, $targetEmail) {
            if (!is_array($row)) {
                return false;
            }

            $sameUser = (int) ($row['userid'] ?? 0) === $userId;
            $sameEmail = strtolower(trim((string) ($row['email'] ?? ''))) === strtolower($targetEmail);
            return !$sameUser && !$sameEmail;
        }));

        array_unshift($rows, [
            'token' => $token,
            'userid' => $userId,
            'email' => $targetEmail,
            'created_at' => now()->toDateTimeString(),
            'expires_at' => $expiresAt->toDateTimeString(),
            'expires_at_ts' => $expiresAt->timestamp,
        ]);

        $this->saveForgotPasswordEmailRequests($rows);

        return [
            'sent' => true,
            'reason' => '',
        ];
    }

    private function forgotPasswordPhoneOtpText(string $otp): string
    {
        return implode("\n", [
            "Neora Color Studio - Password Reset",
            "",
            "Your reset OTP code is: *{$otp}*",
            "This code is valid for 5 minutes.",
            "",
            "Do not share this code with anyone.",
        ]);
    }

    private function maskPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if ($digits === '') {
            return 'your phone number';
        }

        $lastFour = substr($digits, -4);
        return '******' . $lastFour;
    }

    private function grantLoginAccess(Request $request, int $seconds = 600): void
    {
        $request->session()->put('admin_login_access_until', time() + max(20, $seconds));
    }

    public function forgotPasswordEmail(Request $request)
    {
        $website = $this->websiteSettings();
        $this->grantLoginAccess($request, 900);

        $data = [
            'title' => 'Forgot Password by Email | ' . $website['name'],
            'website' => $website,
            'pageScript' => 'forgot-password.js',
        ];

        $this->renderParts(['all.header', 'all.forgot-password-email', 'all.footer'], $data);
    }

    public function forgotPasswordEmailSend(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $targetEmail = trim((string) ($validated['email'] ?? ''));
        $user = $this->forgotPasswordUserByEmail($targetEmail);
        if (!$user) {
            return back()->with('forgot_popup_error', 'Email is not registered.')->withInput();
        }

        $result = $this->queueForgotPasswordEmailReset((int) ($user->userid ?? 0), (string) ($user->email ?? ''));
        if (!(bool) ($result['sent'] ?? false)) {
            return back()->withErrors([
                'email' => 'We could not send the reset email right now. Please try again later.',
            ])->withInput();
        }

        return redirect()->route('password.forgot.email')->with(
            'status',
            'A password reset link has been sent to your email.'
        );
    }

    public function forgotPasswordEmailReset(Request $request, string $token)
    {
        $tokenValue = trim($token);
        $website = $this->websiteSettings();
        if ($tokenValue === '') {
            return response()->view('errors.404', ['website' => $website], 404);
        }

        $rows = $this->pruneForgotPasswordEmailRequests($this->forgotPasswordEmailRequests());
        $this->saveForgotPasswordEmailRequests($rows);
        $target = collect($rows)->first(function ($row) use ($tokenValue) {
            return is_array($row) && hash_equals((string) ($row['token'] ?? ''), $tokenValue);
        });

        if (!is_array($target)) {
            return response()->view('errors.404', ['website' => $website], 404);
        }

        $this->grantLoginAccess($request, 900);
        $data = [
            'title' => 'Reset Password | ' . $website['name'],
            'website' => $website,
            'formAction' => route('password.forgot.email.reset.update', ['token' => $tokenValue]),
            'formTitle' => 'Reset Your Password',
            'formDescription' => 'Enter your new password for your account.',
            'backUrl' => route('password.forgot.email'),
            'backLabel' => 'Back to Forgot Password by Email',
            'pageScript' => 'password-visibility.js',
        ];

        $this->renderParts(['all.header', 'all.forgot-password-reset', 'all.footer'], $data);
    }

    public function forgotPasswordEmailResetUpdate(Request $request, string $token)
    {
        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $tokenValue = trim($token);
        if ($tokenValue === '') {
            return back()->withErrors(['new_password' => 'Reset token is invalid.']);
        }

        $rows = $this->pruneForgotPasswordEmailRequests($this->forgotPasswordEmailRequests());
        $target = null;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (hash_equals((string) ($row['token'] ?? ''), $tokenValue)) {
                $target = $row;
                break;
            }
        }

        if (!is_array($target)) {
            return back()->withErrors(['new_password' => 'Reset token is invalid or expired.']);
        }

        $userId = (int) ($target['userid'] ?? 0);
        if ($userId <= 0) {
            return back()->withErrors(['new_password' => 'Reset token is invalid.']);
        }

        DB::connection('mysql')
            ->table('neoura.user')
            ->where('userid', $userId)
            ->update([
                'password' => Hash::make((string) ($validated['new_password'] ?? '')),
            ]);

        $rows = array_values(array_filter($rows, function ($row) use ($userId) {
            return is_array($row) && (int) ($row['userid'] ?? 0) !== $userId;
        }));
        $this->saveForgotPasswordEmailRequests($rows);

        $this->grantLoginAccess($request, 900);
        return redirect()->route('login')->with('status', 'Password has been reset successfully. Please log in with your new password.');
    }

    public function forgotPasswordPhone(Request $request)
    {
        $website = $this->websiteSettings();
        $otpState = $request->session()->get('forgot_password_phone_otp');
        $showOtpForm = is_array($otpState) && (int) ($otpState['expires_at'] ?? 0) >= time();

        $this->grantLoginAccess($request, 900);
        $data = [
            'title' => 'Forgot Password by Phone | ' . $website['name'],
            'website' => $website,
            'showOtpForm' => $showOtpForm,
            'otpMaskedPhone' => $showOtpForm ? $this->maskPhoneNumber((string) ($otpState['phone'] ?? '')) : '',
            'pageScript' => 'forgot-password.js',
        ];

        $this->renderParts(['all.header', 'all.forgot-password-phone', 'all.footer'], $data);
    }

    public function forgotPasswordPhoneSendOtp(Request $request)
    {
        $validated = $request->validate([
            'phonenumber' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->forgotPasswordUserByPhone((string) ($validated['phonenumber'] ?? ''));
        if (!$user) {
            return back()->with('forgot_popup_error', 'Phone number is not registered.')->withInput();
        }

        $targetPhone = trim((string) ($user->employer_phone ?? ''));
        if ($targetPhone === '') {
            return back()->with('forgot_popup_error', 'Phone number is not registered.')->withInput();
        }

        $otp = (string) random_int(100000, 999999);
        $result = $this->sendWhatsAppMessage($targetPhone, $this->forgotPasswordPhoneOtpText($otp));
        if (!(bool) ($result['sent'] ?? false)) {
            $reason = trim((string) ($result['reason'] ?? ''));
            return back()->withErrors([
                'phonenumber' => $reason !== '' ? ('Failed to send OTP: ' . $reason) : 'Failed to send OTP. Please try again.',
            ])->withInput();
        }

        $request->session()->put('forgot_password_phone_otp', [
            'userid' => (int) ($user->userid ?? 0),
            'phone' => $targetPhone,
            'otp_hash' => hash('sha256', $otp),
            'expires_at' => time() + 300,
            'verified' => false,
        ]);
        $request->session()->forget('forgot_password_phone_verified_userid');

        return redirect()->route('password.forgot.phone')->with(
            'status',
            'OTP has been sent to your registered phone number.'
        );
    }

    public function forgotPasswordPhoneVerifyOtp(Request $request)
    {
        $validated = $request->validate([
            'otp_code' => ['required', 'regex:/^\d{6}$/'],
        ]);

        $otpState = $request->session()->get('forgot_password_phone_otp');
        if (!is_array($otpState)) {
            return redirect()->route('password.forgot.phone')->withErrors([
                'otp_code' => 'OTP session not found. Please request a new OTP.',
            ]);
        }

        if ((int) ($otpState['expires_at'] ?? 0) < time()) {
            $request->session()->forget('forgot_password_phone_otp');
            return redirect()->route('password.forgot.phone')->withErrors([
                'otp_code' => 'OTP has expired. Please request a new OTP.',
            ]);
        }

        $otpHash = hash('sha256', (string) ($validated['otp_code'] ?? ''));
        if (!hash_equals((string) ($otpState['otp_hash'] ?? ''), $otpHash)) {
            return redirect()->route('password.forgot.phone')->withErrors([
                'otp_code' => 'OTP code is invalid.',
            ]);
        }

        $otpState['verified'] = true;
        $request->session()->put('forgot_password_phone_otp', $otpState);
        $request->session()->put('forgot_password_phone_verified_userid', (int) ($otpState['userid'] ?? 0));

        return redirect()->route('password.forgot.phone.reset');
    }

    public function forgotPasswordPhoneReset(Request $request)
    {
        $verifiedUserId = (int) $request->session()->get('forgot_password_phone_verified_userid', 0);
        if ($verifiedUserId <= 0) {
            return redirect()->route('password.forgot.phone')->withErrors([
                'otp_code' => 'Please verify OTP first.',
            ]);
        }

        $website = $this->websiteSettings();
        $this->grantLoginAccess($request, 900);
        $data = [
            'title' => 'Reset Password | ' . $website['name'],
            'website' => $website,
            'formAction' => route('password.forgot.phone.reset.update'),
            'formTitle' => 'Set a New Password',
            'formDescription' => 'OTP verification is complete. Enter your new password.',
            'backUrl' => route('password.forgot.phone'),
            'backLabel' => 'Back to Forgot Password by Phone',
            'pageScript' => 'password-visibility.js',
        ];

        $this->renderParts(['all.header', 'all.forgot-password-reset', 'all.footer'], $data);
    }

    public function forgotPasswordPhoneResetUpdate(Request $request)
    {
        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $verifiedUserId = (int) $request->session()->get('forgot_password_phone_verified_userid', 0);
        if ($verifiedUserId <= 0) {
            return redirect()->route('password.forgot.phone')->withErrors([
                'otp_code' => 'Please verify OTP first.',
            ]);
        }

        DB::connection('mysql')
            ->table('neoura.user')
            ->where('userid', $verifiedUserId)
            ->update([
                'password' => Hash::make((string) ($validated['new_password'] ?? '')),
            ]);

        $request->session()->forget([
            'forgot_password_phone_otp',
            'forgot_password_phone_verified_userid',
        ]);

        $this->grantLoginAccess($request, 900);
        return redirect()->route('login')->with('status', 'Password has been reset successfully. Please log in with your new password.');
    }

    public function login(Request $request)
    {
        $accessUntil = (int) $request->session()->get('admin_login_access_until', 0);
        $website = $this->websiteSettings();
        $failedLoginAttempts = max(0, (int) $request->session()->get('admin_login_failed_attempts', 0));
        $requireCaptcha = $failedLoginAttempts >= 3;

        if ($accessUntil < time()) {
            return response()->view('errors.login-denied', ['website' => $website], 403);
        }

        // One-time temporary access: consume right after login page is opened.
        $request->session()->forget('admin_login_access_until');
        $formToken = Str::random(40);
        $offlineLeft = random_int(1, 20);
        $offlineRight = random_int(1, 20);
        $offlineAnswer = $offlineLeft + $offlineRight;
        $request->session()->put('admin_login_form_token', $formToken);
        $request->session()->put('admin_login_form_expires_at', time() + 300);
        if ($requireCaptcha) {
            $request->session()->put('admin_login_offline_captcha_answer', $offlineAnswer);
        } else {
            $request->session()->forget('admin_login_offline_captcha_answer');
        }
        $data = [
            'title' => 'Login | ' . $website['name'],
            'loginFormToken' => $formToken,
            'recaptchaSiteKey' => trim((string) env('RECAPTCHA_SITE_KEY', '')),
            'offlineCaptchaQuestion' => $offlineLeft . ' + ' . $offlineRight . ' = ?',
            'requireCaptcha' => $requireCaptcha,
            'website' => $website,
            'pageScript' => 'password-visibility.js',
        ];

        $this->renderParts(['all.header', 'all.login', 'all.footer'], $data);
    }

    public function loginSubmit(Request $request)
    {
        $failedLoginAttempts = max(0, (int) $request->session()->get('admin_login_failed_attempts', 0));
        $requireCaptcha = $failedLoginAttempts >= 3;
        $redirectLoginError = function (array $errors, array $inputKeys = ['username'], ?string $popupMessage = null) use ($request) {
            $this->grantLoginAccess($request, 900);
            $response = back()->withErrors($errors)->withInput($request->only($inputKeys));
            if ($popupMessage !== null && trim($popupMessage) !== '') {
                $response = $response->with('login_popup_error', $popupMessage);
            }

            return $response;
        };

        $validationRules = [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'login_form_token' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ];
        if ($requireCaptcha) {
            $validationRules['captcha_mode'] = ['required', 'string', 'in:online,offline'];
            $validationRules['g-recaptcha-response'] = ['nullable', 'string'];
            $validationRules['offline_captcha_answer'] = ['nullable', 'string', 'max:20'];
        }
        $validated = $request->validate($validationRules);

        $sessionToken = (string) $request->session()->get('admin_login_form_token', '');
        $sessionTokenExpiresAt = (int) $request->session()->get('admin_login_form_expires_at', 0);

        if (
            empty($sessionToken) ||
            $sessionTokenExpiresAt < time() ||
            !hash_equals($sessionToken, $validated['login_form_token'])
        ) {
            $request->session()->forget(['admin_login_form_token', 'admin_login_form_expires_at']);
            $this->grantLoginAccess($request, 900);

            return redirect()->route('login')
                ->withErrors(['login' => 'Login session expired. Please try again.'])
                ->with('login_popup_error', 'Login session expired. Please try again.')
                ->withInput($request->only('username'));
        }

        if ($requireCaptcha) {
            $captchaMode = strtolower(trim((string) ($validated['captcha_mode'] ?? 'offline')));
            if ($captchaMode === 'online') {
                $recaptchaSecret = trim((string) env('RECAPTCHA_SECRET_KEY', ''));
                $recaptchaResponse = trim((string) ($validated['g-recaptcha-response'] ?? ''));
                if ($recaptchaSecret === '') {
                    return $redirectLoginError([
                        'captcha' => 'Online captcha is not configured. Please contact administrator.',
                    ], ['username', 'captcha_mode'], 'Online captcha is not configured. Please contact administrator.');
                }
                if ($recaptchaResponse === '') {
                    return $redirectLoginError([
                        'captcha' => 'Please verify online captcha first.',
                    ], ['username', 'captcha_mode'], 'Please verify online captcha first.');
                }

                try {
                    $captchaVerifyResponse = Http::asForm()
                        ->timeout(10)
                        ->post('https://www.google.com/recaptcha/api/siteverify', [
                            'secret' => $recaptchaSecret,
                            'response' => $recaptchaResponse,
                            'remoteip' => (string) $request->ip(),
                        ]);

                    $captchaPayload = $captchaVerifyResponse->json();
                    if (!$captchaVerifyResponse->ok() || !is_array($captchaPayload) || !((bool) ($captchaPayload['success'] ?? false))) {
                        return $redirectLoginError([
                            'captcha' => 'Online captcha verification failed.',
                        ], ['username', 'captcha_mode'], 'Online captcha verification failed.');
                    }
                } catch (\Throwable $e) {
                    return $redirectLoginError([
                        'captcha' => 'Unable to verify online captcha. Please try again.',
                    ], ['username', 'captcha_mode'], 'Unable to verify online captcha. Please try again.');
                }
            } else {
                $expectedOfflineAnswer = (int) $request->session()->get('admin_login_offline_captcha_answer', -1);
                $offlineAnswerRaw = trim((string) ($validated['offline_captcha_answer'] ?? ''));
                if ($expectedOfflineAnswer < 0) {
                    return $redirectLoginError([
                        'captcha' => 'Offline captcha session expired. Please reload login page.',
                    ], ['username', 'captcha_mode'], 'Offline captcha session expired. Please reload login page.');
                }
                if (!preg_match('/^-?\d+$/', $offlineAnswerRaw) || (int) $offlineAnswerRaw !== $expectedOfflineAnswer) {
                    return $redirectLoginError([
                        'captcha' => 'Offline captcha answer is incorrect.',
                    ], ['username', 'captcha_mode', 'offline_captcha_answer'], 'Offline captcha answer is incorrect.');
                }
            }
        }

        $user = DB::connection('mysql')
            ->table('neoura.user as u')
            ->leftJoin('neoura.employer as e', 'e.userid', '=', 'u.userid')
            ->leftJoin('neoura.level as l', 'l.levelid', '=', 'u.levelid')
            ->select(
                'u.userid',
                'u.username',
                'u.password',
                'u.levelid',
                'e.employerid',
                'e.name as employer_name',
                'e.email as employer_email',
                'e.phonenumber as employer_phone',
                'l.levelname'
            )
            ->where('u.username', $validated['username'])
            ->first();

        if (!$user) {
            $request->session()->put('admin_login_failed_attempts', $failedLoginAttempts + 1);
            return $redirectLoginError([
                'username' => 'Username not found.',
            ], ['username'], 'Username not found.');
        }

        $plainPassword = $validated['password'];
        $storedPassword = (string) $user->password;
        $passwordValid = Hash::check($plainPassword, $storedPassword) || hash_equals($storedPassword, $plainPassword);

        if (!$passwordValid) {
            $request->session()->put('admin_login_failed_attempts', $failedLoginAttempts + 1);
            return $redirectLoginError([
                'password' => 'Incorrect password.',
            ], ['username'], 'Incorrect password.');
        }

        $request->session()->forget([
            'admin_login_failed_attempts',
            'admin_login_form_token',
            'admin_login_form_expires_at',
            'admin_login_offline_captcha_answer',
        ]);
        $request->session()->put('admin_auth', [
            'userid' => $user->userid,
            'username' => $user->username,
            'levelid' => $user->levelid,
            'levelname' => $user->levelname,
            'employerid' => $user->employerid,
            'employer_name' => $user->employer_name,
            'employer_email' => $user->employer_email,
            'employer_phone' => $user->employer_phone,
            'logged_in_at' => now()->toDateTimeString(),
        ]);

        $request->attributes->set('activity_action_override', 'Login');
        $request->attributes->set(
            'activity_detail_override',
            'User ' . trim((string) ($user->username ?? '-')) . ' logged in successfully.'
        );

        $latitude = trim((string) ($validated['latitude'] ?? ''));
        $longitude = trim((string) ($validated['longitude'] ?? ''));
        if ($latitude !== '' && $longitude !== '') {
            $request->session()->put('admin_activity_coords', [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        }

        return redirect()->route('home')->with('status', 'Admin login successful.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget([
            'admin_auth',
            'admin_logo_click_started_at',
            'admin_logo_click_count',
            'admin_login_access_until',
            'admin_login_form_token',
            'admin_login_form_expires_at',
            'admin_login_offline_captcha_answer',
            'admin_activity_coords',
            'forgot_password_phone_otp',
            'forgot_password_phone_verified_userid',
        ]);
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Admin logout successful.');
    }

    public function updateCarousel(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }

        $validated = $request->validate([
            'carousel_autoplay_ms' => ['required', 'integer', 'min:500', 'max:60000'],
            'slides' => ['required', 'array', 'min:1', 'max:20'],
            'slides.*.title' => ['required', 'string', 'max:120'],
            'slides.*.description' => ['required', 'string', 'max:255'],
            'slides.*.existing_image' => ['nullable', 'string', 'max:255'],
            'slides.*.image' => ['nullable', 'image', 'max:3072'],
        ]);

        $slides = [];
        foreach (array_values($validated['slides']) as $index => $slideData) {
            $existingPath = trim((string) ($slideData['existing_image'] ?? ''));
            if (!Str::startsWith($existingPath, 'images/carousel/')) {
                $existingPath = '';
            }

            $imagePath = $existingPath;
            $file = $request->file("slides.$index.image");
            if ($file) {
                $directory = public_path('images/carousel');
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                $filename = 'carousel-' . ($index + 1) . '-' . time() . '-' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $file->move($directory, $filename);
                $imagePath = 'images/carousel/' . $filename;
            }

            $slides[] = [
                'title' => trim((string) ($slideData['title'] ?? '')),
                'description' => trim((string) ($slideData['description'] ?? '')),
                'image_path' => $imagePath,
            ];
        }

        $this->saveCarouselSlides($slides);
        $this->saveCarouselSettings([
            'autoplay_ms' => (int) ($validated['carousel_autoplay_ms'] ?? $this->defaultCarouselAutoplayMs()),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Home content updated.',
                'carousel_autoplay_ms' => (int) ($validated['carousel_autoplay_ms'] ?? $this->defaultCarouselAutoplayMs()),
                'slides' => $this->decorateCarouselSlides($slides),
            ]);
        }

        return redirect()->route('home')->with('status', 'Home content updated.');
    }

    public function updateAboutContent(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }

        $validated = $request->validate([
            'about_title' => ['required', 'string', 'max:255'],
            'about_description' => ['required', 'string', 'max:3000'],
        ]);

        $this->saveAboutContent([
            'title' => $validated['about_title'],
            'description' => $validated['about_description'],
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'About Us updated.',
                'about' => [
                    'title' => $validated['about_title'],
                    'description' => $validated['about_description'],
                ],
            ]);
        }

        return redirect()->route('home')->with('status', 'About Us updated.');
    }

    public function superAdminPermission(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'permission')) {
            return $this->sidebarPermissionDenied($request);
        }

        $website = $this->websiteSettings();
        $showAdminMenu = $this->canSeeAdminMenu($adminAuth);
        $sidebarServices = $this->sidebarServices();
        $permissionRows = $this->sidebarPermissionMenuRows();
        $sidebarPermissions = $this->sidebarPermissions();

        $data = [
            'title' => 'Sidebar Permission | ' . $website['name'],
            'adminAuth' => $adminAuth,
            'showAdminMenu' => $showAdminMenu,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'sidebarServices' => $sidebarServices,
            'website' => $website,
            'permissionRows' => $permissionRows,
            'sidebarPermissions' => $sidebarPermissions,
        ];

        $this->renderParts(['all.header', 'all.menu', 'superadmin.permission', 'all.footer'], $data);
    }

    public function superAdminPermissionUpdate(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'permission')) {
            return $this->sidebarPermissionDenied($request);
        }

        $payload = $request->input('permissions', []);
        if (!is_array($payload)) {
            $payload = [];
        }

        $this->saveSidebarPermissions($payload);

        return redirect()->route('superadmin.permission')->with('status', 'Sidebar permission updated.');
    }

    public function superAdminSetting(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'setting')) {
            return $this->sidebarPermissionDenied($request);
        }

        $website = $this->websiteSettings();
        $showAdminMenu = $this->canSeeAdminMenu($adminAuth);
        $sidebarServices = $this->sidebarServices();
        $data = [
            'title' => 'Setting | ' . $website['name'],
            'adminAuth' => $adminAuth,
            'showAdminMenu' => $showAdminMenu,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'sidebarServices' => $sidebarServices,
            'website' => $website,
            'pageScript' => 'setting.js',
        ];

        $this->renderParts(['all.header', 'all.menu', 'superadmin.setting', 'all.footer'], $data);
    }

    public function superAdminBackupDatabase(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'backup')) {
            return $this->sidebarPermissionDenied($request);
        }

        $website = $this->websiteSettings();
        $showAdminMenu = $this->canSeeAdminMenu($adminAuth);
        $sidebarServices = $this->sidebarServices();
        $data = [
            'title' => 'Backup Database | ' . $website['name'],
            'adminAuth' => $adminAuth,
            'showAdminMenu' => $showAdminMenu,
            'sidebarPermissionMap' => $this->sidebarPermissionMapForAuth($adminAuth),
            'sidebarServices' => $sidebarServices,
            'website' => $website,
        ];

        $this->renderParts(['all.header', 'all.menu', 'superadmin.backup', 'all.footer'], $data);
    }

    public function superAdminBackupDatabaseExportSql(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'backup')) {
            return $this->sidebarPermissionDenied($request);
        }

        try {
            $connection = DB::connection('mysql');
            $database = $this->resolveBackupDatabaseName($connection);
            $tables = collect($connection->select(
                'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME',
                [$database, 'BASE TABLE']
            ))
                ->map(fn($row) => (string) ($row->TABLE_NAME ?? ''))
                ->filter()
                ->values()
                ->all();

            if (empty($tables)) {
                return redirect()->route('superadmin.backup')->withErrors(['backup' => 'No table found to export.']);
            }
        } catch (\Throwable $e) {
            return redirect()->route('superadmin.backup')->withErrors(['backup' => 'Failed to prepare export: ' . $e->getMessage()]);
        }

        $request->attributes->set('activity_action_override', 'Backup Database Export SQL');
        $request->attributes->set('activity_detail_override', 'Exported SQL backup for database ' . $database . '.');
        $filename = 'backup-' . $database . '-' . date('Ymd-His') . '.sql';

        return response()->streamDownload(function () use ($connection, $database, $tables) {
            echo "-- Neoura SQL Backup\n";
            echo '-- Generated at: ' . now()->toDateTimeString() . "\n";
            echo '-- Source database: ' . $database . "\n\n";
            echo "SET NAMES utf8mb4;\n";
            echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $tableName) {
                $qualified = $this->escapeQualifiedTableName($database, $tableName);
                $createRow = $connection->selectOne('SHOW CREATE TABLE ' . $qualified);
                if (!$createRow) {
                    continue;
                }

                $createTableSql = '';
                foreach ((array) $createRow as $key => $value) {
                    if (is_string($key) && str_starts_with($key, 'Create ')) {
                        $createTableSql = (string) $value;
                        break;
                    }
                }
                if ($createTableSql === '') {
                    continue;
                }

                $tableIdent = $this->escapeSqlIdentifier($tableName);
                $createTableSql = str_replace($this->escapeSqlIdentifier($database) . '.', '', $createTableSql);

                echo '-- --------------------------------------------------' . "\n";
                echo '-- Table: ' . $tableName . "\n";
                echo 'DROP TABLE IF EXISTS ' . $tableIdent . ";\n";
                echo $createTableSql . ";\n\n";

                $columns = collect($connection->select(
                    'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                    [$database, $tableName]
                ))
                    ->map(fn($row) => (string) ($row->COLUMN_NAME ?? ''))
                    ->filter()
                    ->values()
                    ->all();

                if (empty($columns)) {
                    continue;
                }

                $selectColumns = implode(', ', array_map(fn($column) => $this->escapeSqlIdentifier($column), $columns));
                $rows = $connection->select('SELECT ' . $selectColumns . ' FROM ' . $qualified);
                if (empty($rows)) {
                    echo "\n";
                    continue;
                }

                $insertColumns = implode(', ', array_map(fn($column) => $this->escapeSqlIdentifier($column), $columns));
                foreach (array_chunk($rows, 200) as $chunkRows) {
                    $valueRows = [];
                    foreach ($chunkRows as $row) {
                        $rowArray = (array) $row;
                        $valueCells = [];
                        foreach ($columns as $column) {
                            $valueCells[] = $this->sqlLiteral($rowArray[$column] ?? null);
                        }
                        $valueRows[] = '(' . implode(', ', $valueCells) . ')';
                    }

                    echo 'INSERT INTO ' . $tableIdent . ' (' . $insertColumns . ") VALUES\n";
                    echo implode(",\n", $valueRows) . ";\n";
                }
                echo "\n";
            }

            echo "SET FOREIGN_KEY_CHECKS=1;\n";
        }, $filename, [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ]);
    }

    public function superAdminBackupDatabaseImportSql(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'backup')) {
            return $this->sidebarPermissionDenied($request);
        }

        $validated = $request->validate([
            'backup_sql' => ['required', 'file', 'mimes:sql,txt', 'max:51200'],
        ]);

        $file = $validated['backup_sql'] ?? null;
        if (!$file) {
            return redirect()->route('superadmin.backup')->withErrors(['backup' => 'SQL file is required.']);
        }

        $sql = file_get_contents($file->getRealPath());
        if (!is_string($sql) || trim($sql) === '') {
            return redirect()->route('superadmin.backup')->withErrors(['backup' => 'Uploaded SQL file is empty.']);
        }

        $statements = $this->splitSqlStatements($sql);
        if (empty($statements)) {
            return redirect()->route('superadmin.backup')->withErrors(['backup' => 'No executable SQL statement found.']);
        }

        $executed = 0;
        try {
            $connection = DB::connection('mysql');
            foreach ($statements as $statement) {
                $normalized = ltrim($statement);
                if ($normalized === '') {
                    continue;
                }
                $connection->unprepared($statement);
                $executed++;
            }
        } catch (\Throwable $e) {
            return redirect()->route('superadmin.backup')->withErrors([
                'backup' => 'SQL import failed at statement ' . ($executed + 1) . ': ' . $e->getMessage(),
            ]);
        }

        $request->attributes->set('activity_action_override', 'Backup Database Import SQL');
        $request->attributes->set('activity_detail_override', 'Imported SQL backup. Executed statements: ' . $executed . '.');

        return redirect()->route('superadmin.backup')->with(
            'status',
            'Database restore completed. Executed statements: ' . $executed . '.'
        );
    }

    private function resolveBackupDatabaseName($connection): string
    {
        $preferred = 'neoura';
        $schemaExists = $connection->selectOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$preferred]
        );
        if ($schemaExists) {
            return $preferred;
        }

        $current = trim((string) $connection->getDatabaseName());
        if ($current !== '') {
            return $current;
        }

        $fallback = $connection->selectOne('SELECT DATABASE() as db_name');
        $fallbackName = trim((string) ($fallback->db_name ?? ''));
        if ($fallbackName !== '') {
            return $fallbackName;
        }

        throw new \RuntimeException('Unable to resolve database name.');
    }

    private function escapeSqlIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    private function escapeQualifiedTableName(string $database, string $table): string
    {
        return $this->escapeSqlIdentifier($database) . '.' . $this->escapeSqlIdentifier($table);
    }

    private function sqlLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $stringValue = (string) $value;
        $stringValue = str_replace(
            ["\\", "\0", "\n", "\r", "\t", "\x1a", "'"],
            ["\\\\", "\\0", "\\n", "\\r", "\\t", "\\Z", "\\'"],
            $stringValue
        );

        return "'" . $stringValue . "'";
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = '';
        $escaped = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($quote !== '') {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\' && $quote !== '`') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($char === '/' && $next === '*') {
                $i += 2;
                while ($i < $length - 1) {
                    if ($sql[$i] === '*' && $sql[$i + 1] === '/') {
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    public function superAdminSettingUpdate(Request $request)
    {
        $adminAuth = $request->session()->get('admin_auth');
        if (!$this->canSeeAdminMenu($adminAuth)) {
            return response()->view('errors.403', ['website' => $this->websiteSettings()], 403);
        }
        if (!$this->canAccessSidebarMenu($adminAuth, 'setting')) {
            return $this->sidebarPermissionDenied($request);
        }

        $validated = $request->validate([
            'systemname' => ['required', 'string', 'max:255'],
            'website_name_visibility_toggle' => ['nullable', 'boolean'],
            'systemcontact' => ['nullable', 'string', 'max:255'],
            'system_insta' => ['nullable', 'string', 'max:255'],
            'systemaddress' => ['nullable', 'string', 'max:255'],
            'system_theme_color_soft' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'system_theme_color_bold' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'bankname' => ['nullable', 'array'],
            'bankname.*' => ['nullable', 'string', 'max:255'],
            'banknumber' => ['nullable', 'array'],
            'banknumber.*' => ['nullable', 'string', 'max:255'],
            'systemlogo' => ['nullable', 'image', 'max:2048'],
        ]);

        $system = DB::connection('mysql')
            ->table('neoura.system')
            ->orderBy('systemid')
            ->first();

        $systemId = $system->systemid ?? null;
        $logoPath = (string) ($system->systemlogo ?? 'images/neora-logo.svg');

        if ($request->hasFile('systemlogo')) {
            $file = $request->file('systemlogo');
            $directory = public_path('images/system');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = 'logo-' . time() . '-' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $filename);
            $logoPath = 'images/system/' . $filename;
        }

        $systemPayload = [
            'systemname' => $validated['systemname'],
            'systemlogo' => $logoPath,
            'systemcontact' => $validated['systemcontact'] ?? '',
            'system_insta' => $validated['system_insta'] ?? '',
            'systemaddress' => $validated['systemaddress'] ?? '',
            'color1' => $this->normalizeHexColor(
                $validated['system_theme_color_soft'] ?? null,
                $this->defaultThemeSoftColor()
            ),
            'color2' => $this->normalizeHexColor(
                $validated['system_theme_color_bold'] ?? null,
                $this->defaultThemeBoldColor()
            ),
        ];

        if ($systemId) {
            DB::connection('mysql')->table('neoura.system')->where('systemid', $systemId)->update($systemPayload);
        } else {
            $systemId = DB::connection('mysql')->table('neoura.system')->insertGetId($systemPayload);
        }

        $bankNames = $validated['bankname'] ?? [];
        $bankNumbers = $validated['banknumber'] ?? [];
        $maxBankRows = max(count($bankNames), count($bankNumbers));
        $bankRows = [];
        for ($i = 0; $i < $maxBankRows; $i++) {
            $bankName = trim((string) ($bankNames[$i] ?? ''));
            $bankNumber = trim((string) ($bankNumbers[$i] ?? ''));
            if ($bankName === '' && $bankNumber === '') {
                continue;
            }

            $bankRows[] = [
                'bankname' => $bankName,
                'banknumber' => $bankNumber,
                'systemid' => $systemId,
            ];
        }

        DB::connection('mysql')->table('neoura.bank')->where('systemid', $systemId)->delete();
        if (!empty($bankRows)) {
            DB::connection('mysql')->table('neoura.bank')->insert($bankRows);
        }

        $this->saveBrandDisplaySettings([
            'show_name_in_brand' => (bool) ($validated['website_name_visibility_toggle'] ?? false),
        ]);

        return redirect()->route('superadmin.setting')->with('status', 'Website settings updated.');
    }

    public function registerLogoClick(Request $request): JsonResponse
    {
        $now = microtime(true);
        $windowSeconds = 2.0;
        $requiredClicks = 5;

        $startedAt = (float) $request->session()->get('admin_logo_click_started_at', 0);
        $count = (int) $request->session()->get('admin_logo_click_count', 0);

        if ($startedAt <= 0 || ($now - $startedAt) > $windowSeconds) {
            $startedAt = $now;
            $count = 1;
        } else {
            $count++;
        }

        if ($count >= $requiredClicks) {
            $request->session()->forget(['admin_logo_click_started_at', 'admin_logo_click_count']);
            $request->session()->put('admin_login_access_until', time() + 20);

            return response()->json([
                'unlocked' => true,
                'redirect' => route('login'),
            ]);
        }

        $request->session()->put('admin_logo_click_started_at', $startedAt);
        $request->session()->put('admin_logo_click_count', $count);

        return response()->json([
            'unlocked' => false,
            'remaining' => $requiredClicks - $count,
        ]);
    }

    public function notFound()
    {
        return response()->view('errors.404', ['website' => $this->websiteSettings()], 404);
    }
}
