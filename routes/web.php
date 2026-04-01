<?php

use App\Http\Controllers\Ctrl;
use Illuminate\Support\Facades\Route;

Route::get('/lang/{locale}', function (string $locale) {
    $normalized = strtolower(trim($locale));
    if (!in_array($normalized, ['en', 'id'], true)) {
        $normalized = config('app.locale', 'en');
    }

    session(['locale' => $normalized]);

    return redirect()->back();
})->name('lang.switch');

Route::get('/', [Ctrl::class, 'home'])->name('home');
Route::get('/service/basic-session', [Ctrl::class, 'basicSession'])->name('service.basic');
Route::get('/service/exclusive-session', [Ctrl::class, 'exclusiveSession'])->name('service.exclusive');
Route::get('/service/luxe-session', [Ctrl::class, 'luxeSession'])->name('service.luxe');
Route::get('/booking', [Ctrl::class, 'booking'])->name('booking');
Route::post('/booking', [Ctrl::class, 'bookingSubmit'])->name('booking.submit');
Route::post('/booking/status', [Ctrl::class, 'bookingStatusLookup'])->name('booking.status');
Route::get('/login', [Ctrl::class, 'login'])->name('login');
Route::post('/login', [Ctrl::class, 'loginSubmit'])->name('login.submit');
Route::get('/forgot-password/email', [Ctrl::class, 'forgotPasswordEmail'])->name('password.forgot.email');
Route::post('/forgot-password/email', [Ctrl::class, 'forgotPasswordEmailSend'])->name('password.forgot.email.send');
Route::get('/forgot-password/reset/{token}', [Ctrl::class, 'forgotPasswordEmailReset'])->name('password.forgot.email.reset');
Route::post('/forgot-password/reset/{token}', [Ctrl::class, 'forgotPasswordEmailResetUpdate'])->name('password.forgot.email.reset.update');
Route::get('/forgot-password/phone', [Ctrl::class, 'forgotPasswordPhone'])->name('password.forgot.phone');
Route::post('/forgot-password/phone/send-otp', [Ctrl::class, 'forgotPasswordPhoneSendOtp'])->name('password.forgot.phone.send_otp');
Route::post('/forgot-password/phone/verify-otp', [Ctrl::class, 'forgotPasswordPhoneVerifyOtp'])->name('password.forgot.phone.verify_otp');
Route::get('/forgot-password/phone/reset', [Ctrl::class, 'forgotPasswordPhoneReset'])->name('password.forgot.phone.reset');
Route::post('/forgot-password/phone/reset', [Ctrl::class, 'forgotPasswordPhoneResetUpdate'])->name('password.forgot.phone.reset.update');
Route::post('/logout', [Ctrl::class, 'logout'])->name('logout');
Route::post('/admin/logo-click', [Ctrl::class, 'registerLogoClick'])->name('admin.logo.click');
Route::post('/carousel/update', [Ctrl::class, 'updateCarousel'])->name('carousel.update');
Route::post('/about/update', [Ctrl::class, 'updateAboutContent'])->name('about.update');
Route::post('/contact/update', [Ctrl::class, 'updateContactContent'])->name('contact.update');
Route::get('/service', [Ctrl::class, 'adminService'])->name('admin.service');
Route::get('/service/export-excel', [Ctrl::class, 'exportServiceExcel'])->name('admin.service.export_excel');
Route::post('/service/import-excel', [Ctrl::class, 'importServiceExcel'])->name('admin.service.import_excel');
Route::post('/service/store', [Ctrl::class, 'adminServiceStore'])->name('admin.service.store');
Route::post('/service/{serviceid}/update', [Ctrl::class, 'adminServiceUpdate'])->name('admin.service.update');
Route::post('/service/{serviceid}/delete', [Ctrl::class, 'adminServiceDelete'])->name('admin.service.delete');
Route::get('/recyclebin', [Ctrl::class, 'superAdminRecycleBin'])->name('superadmin.recyclebin');
Route::post('/recyclebin/{recycleId}/restore', [Ctrl::class, 'superAdminRecycleBinRestore'])->name('superadmin.recyclebin.restore');
Route::post('/recyclebin/{recycleId}/delete-permanent', [Ctrl::class, 'superAdminRecycleBinDeletePermanent'])->name('superadmin.recyclebin.delete_permanent');
Route::get('/paymentvalidation', [Ctrl::class, 'adminPaymentValidation'])->name('admin.payment');
Route::post('/paymentvalidation/{bookingid}', [Ctrl::class, 'adminPaymentValidationUpdate'])->name('admin.payment.update');
Route::get('/activity-log', [Ctrl::class, 'adminActivityLog'])->name('admin.activitylog');
Route::post('/activity-log/location', [Ctrl::class, 'adminActivityLocationUpdate'])->name('admin.activitylog.location');

Route::get('/financial-report', [Ctrl::class, 'adminFinancialReport'])->name('admin.financialreport');

Route::get('/financial-report', [Ctrl::class, 'adminFinancialReport'])->name('admin.financial');
Route::post('/financial-report/expense', [Ctrl::class, 'adminFinancialExpenseStore'])->name('admin.financial.expense.store');
Route::post('/financial-report/expense/{expenseid}/update', [Ctrl::class, 'adminFinancialExpenseUpdate'])->name('admin.financial.expense.update');
Route::post('/financial-report/expense/{expenseid}/delete', [Ctrl::class, 'adminFinancialExpenseDelete'])->name('admin.financial.expense.delete');
Route::get('/financial-report/print', [Ctrl::class, 'adminFinancialReportPrint'])->name('admin.financial.print');
Route::get('/financial-report/export-pdf', [Ctrl::class, 'adminFinancialReportExportPdf'])->name('admin.financial.export_pdf');
Route::get('/financial-report/export-excel', [Ctrl::class, 'adminFinancialReportExportExcel'])->name('admin.financial.export_excel');

Route::get('/userdata', [Ctrl::class, 'adminUserData'])->name('admin.userdata');
Route::get('/userdata/export-excel', [Ctrl::class, 'exportUserDataExcel'])->name('admin.userdata.export_excel');
Route::post('/userdata/import-excel', [Ctrl::class, 'importUserDataExcel'])->name('admin.userdata.import_excel');
Route::post('/userdata/store', [Ctrl::class, 'adminUserStore'])->name('admin.userdata.store');
Route::post('/userdata/{userid}/reset-password', [Ctrl::class, 'adminUserResetPassword'])->name('admin.userdata.reset_password');
Route::post('/userdata/{userid}/delete', [Ctrl::class, 'adminUserDelete'])->name('admin.userdata.delete');
Route::get('/account', [Ctrl::class, 'account'])->name('account');
Route::post('/account', [Ctrl::class, 'accountUpdate'])->name('account.update');
Route::get('/account/email-change/verify/{token}', [Ctrl::class, 'accountVerifyEmailChange'])->name('account.email_change.verify');
Route::post('/account/phone-otp/send', [Ctrl::class, 'accountSendPhoneOtp'])->name('account.phone_otp.send');
Route::post('/account/phone-otp/verify', [Ctrl::class, 'accountVerifyPhoneOtp'])->name('account.phone_otp.verify');
Route::get('/permission', [Ctrl::class, 'superAdminPermission'])->name('superadmin.permission');
Route::post('/permission', [Ctrl::class, 'superAdminPermissionUpdate'])->name('superadmin.permission.update');
Route::get('/setting', [Ctrl::class, 'superAdminSetting'])->name('superadmin.setting');
Route::post('/setting', [Ctrl::class, 'superAdminSettingUpdate'])->name('superadmin.setting.update');
Route::get('/backup-database', [Ctrl::class, 'superAdminBackupDatabase'])->name('superadmin.backup');
Route::post('/backup-database/export-sql', [Ctrl::class, 'superAdminBackupDatabaseExportSql'])->name('superadmin.backup.export_sql');
Route::post('/backup-database/import-sql', [Ctrl::class, 'superAdminBackupDatabaseImportSql'])->name('superadmin.backup.import_sql');

Route::get('/internal-dashboard', function () {
    return 'Internal dashboard: access granted.';
})->middleware('restricted.access')->name('internal.dashboard');

Route::fallback([Ctrl::class, 'notFound']);
