<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Category;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private const STORE_KEYS = [
        'store_name', 'store_tagline', 'store_address',
        'store_phone', 'store_email', 'receipt_footer', 'pos_class',
        'pos_layout', 'pos_product_display', 'report_scope',
        'service_charge_enabled', 'service_charge_pct',
        'pb1_enabled', 'pb1_pct',
        'thermal_printer_device', 'thermal_printer_name',
        'mobile_api_key', 'mobile_api_secret',
    ];

    private const LOGO_DIR = 'images';

    /** Item Group (category) filter settings, one per screen. Stored as JSON arrays of category IDs. */
    private const ITEM_GROUP_KEYS = [
        'pos_item_groups',
        'delivery_item_groups',
        'stock_request_item_groups',
        'slice_item_groups',
    ];

    public function index()
    {
        $settings = [];
        foreach (self::STORE_KEYS as $key) {
            $settings[$key] = Setting::get($key, '');
        }
        $settings['store_logo'] = Setting::get('store_logo', '');
        $settings['thermal_printer_device'] = Setting::get('thermal_printer_device', '/dev/usb/lp1');
        $settings['thermal_printer_name']   = Setting::get('thermal_printer_name', 'EPPOS58');
        $settings['os_family'] = PHP_OS_FAMILY;

        // Item Group filters — decode stored JSON arrays of category IDs (default: empty = all)
        foreach (self::ITEM_GROUP_KEYS as $key) {
            $decoded = json_decode(Setting::get($key, '[]'), true);
            $settings[$key] = is_array($decoded) ? array_map('intval', $decoded) : [];
        }
        $itemGroups = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Rentang tanggal laporan: dikunci per role (admin selalu bebas).
        $reportRoles       = \App\Models\Role::configurable();
        $lockedReportRoles = Setting::reportDateLimitedRoles();
        if (in_array('*', $lockedReportRoles, true)) {
            $lockedReportRoles = $reportRoles->pluck('name')->all();
        }

        return view('settings.index', compact('settings', 'itemGroups', 'reportRoles', 'lockedReportRoles'));
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        // Hapus logo lama jika ada
        $old = Setting::get('store_logo', '');
        if ($old && file_exists(public_path($old))) {
            @unlink(public_path($old));
        }

        $file = $request->file('logo');
        $ext  = $file->getClientOriginalExtension();
        $name = 'store-logo.' . strtolower($ext);
        $dir  = public_path(self::LOGO_DIR);

        try {
            $file->move($dir, $name);
        } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException $e) {
            return response()->json([
                'success' => false,
                'message' => "Server tidak bisa menulis ke folder \"public/images\". Hubungi admin server untuk memastikan folder tersebut writable oleh web server (chmod/chown).",
            ], 500);
        }

        $path = self::LOGO_DIR . '/' . $name;
        Setting::set('store_logo', $path, 'store');

        return response()->json(['success' => true, 'path' => $path, 'url' => asset($path)]);
    }

    public function removeLogo()
    {
        $old = Setting::get('store_logo', '');
        if ($old && file_exists(public_path($old))) {
            @unlink(public_path($old));
        }
        Setting::set('store_logo', '', 'store');

        return response()->json(['success' => true]);
    }

    public function save(Request $request)
    {
        $request->validate([
            'store_name'             => 'nullable|string|max:100',
            'store_tagline'          => 'nullable|string|max:150',
            'store_address'          => 'nullable|string|max:300',
            'store_phone'            => 'nullable|string|max:30',
            'store_email'            => 'nullable|email|max:100',
            'receipt_footer'         => 'nullable|string|max:200',
            'pos_class'              => 'nullable|string|max:100',
            'pos_layout'             => 'nullable|in:index,quick,express',
            'pos_product_display'    => 'nullable|in:image,text',
            'report_scope'           => 'nullable|in:all,user',
            'report_date_limit_roles'   => 'nullable|array',
            'report_date_limit_roles.*' => 'string|max:50',
            'service_charge_enabled' => 'nullable|in:0,1',
            'service_charge_pct'     => 'nullable|numeric|min:0|max:100',
            'pb1_enabled'            => 'nullable|in:0,1',
            'pb1_pct'                => 'nullable|numeric|min:0|max:100',
            'thermal_printer_device' => 'nullable|string|max:255',
            'thermal_printer_name'   => 'nullable|string|max:255',
            'mobile_api_key'         => 'nullable|string|max:64',
            'mobile_api_secret'      => 'nullable|string|max:64',
        ]);

        // Checkboxes: jika tidak dicentang, request tidak mengirim nilai → default '0'
        $data = $request->only(self::STORE_KEYS);
        $data['service_charge_enabled'] = $request->has('service_charge_enabled') ? '1' : '0';
        $data['pb1_enabled']            = $request->has('pb1_enabled') ? '1' : '0';

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '', 'store');
        }

        // Rentang tanggal laporan: daftar role yang terkunci ke hari ini.
        // Checkbox yang tidak dicentang tidak dikirim → tidak ada role terkunci.
        $lockedRoles = array_values(array_unique(array_filter(
            (array) $request->input('report_date_limit_roles', []),
            fn ($r) => is_string($r) && $r !== '' && $r !== 'admin'
        )));
        Setting::set('report_date_limit_roles', json_encode($lockedRoles), 'store');

        // Item Group filters arrive as CSV of category IDs (e.g. "1,3,5"); store as JSON array.
        foreach (self::ITEM_GROUP_KEYS as $key) {
            $csv = (string) $request->input($key, '');
            $ids = array_values(array_filter(array_map('intval', explode(',', $csv))));
            Setting::set($key, json_encode($ids), 'store');
        }

        return response()->json(['success' => true]);
    }

    // Helper yang dipanggil oleh PosController
    public static function storeSettings(): array
    {
        return [
            'store_name'             => Setting::get('store_name', ''),
            'store_tagline'          => Setting::get('store_tagline', 'Point of Sale System'),
            'store_address'          => Setting::get('store_address', ''),
            'store_phone'            => Setting::get('store_phone', ''),
            'store_email'            => Setting::get('store_email', ''),
            'receipt_footer'         => Setting::get('receipt_footer', 'Terima kasih atas kunjungan Anda!'),
            'pos_profile'            => Setting::get('erpnext_pos_profile', ''),
            'pos_class'              => Setting::get('pos_class', ''),
            'pos_layout'             => Setting::get('pos_layout', 'index'),
            'pos_product_display'    => Setting::get('pos_product_display', 'image'),
            'service_charge_enabled' => Setting::get('service_charge_enabled', '0'),
            'service_charge_pct'     => (float) Setting::get('service_charge_pct', '0'),
            'pb1_enabled'            => Setting::get('pb1_enabled', '0'),
            'pb1_pct'                => (float) Setting::get('pb1_pct', '0'),
            'store_logo'             => Setting::get('store_logo', ''),
        ];
    }
}
