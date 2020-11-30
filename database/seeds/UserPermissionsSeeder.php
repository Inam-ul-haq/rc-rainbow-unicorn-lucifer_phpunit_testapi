<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\SystemVariable;

// @codingStandardsIgnoreLine
class UserPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'admin users']);
        Permission::create(['name' => 'login at runlevel 1']);
        Permission::create(['name' => 'create voucher']);
        Permission::create(['name' => 'view vouchers']);
        Permission::create(['name' => 'edit voucher']);
        Permission::create(['name' => 'redeem voucher']);
        Permission::create(['name' => 'view consumers']);
        Permission::create(['name' => 'edit consumers']);
        Permission::create(['name' => 'view reports list']);
        Permission::create(['name' => 'add new report']);
        Permission::create(['name' => 'assign voucher to user']);
        Permission::create(['name' => 'view partners']);
        Permission::create(['name' => 'edit partner']);
        Permission::create(['name' => 'view referrers']);
        Permission::create(['name' => 'edit referrer']);
        Permission::create(['name' => 'view loyalty']);
        Permission::create(['name' => 'view statements']);
        Permission::create(['name' => 'view redemptions']);
        Permission::create(['name' => 'see internal data']);
        Permission::create(['name' => 'edit all partner users']);
        Permission::create(['name' => 'edit own partner users']);
        Permission::create(['name' => 'update motd']);
        Permission::create(['name' => 'set runlevel']);
        Permission::create(['name' => 'cancel coupon']);
        Permission::create(['name' => 'issue coupon']);
        Permission::create(['name' => 'issue api keys']);
        Permission::create(['name' => 'admin api keys']);
        Permission::create(['name' => 'delete consumer events']);

        Permission::create(['guard_name' => 'api_key', 'name' => 'view vouchers']);
        Permission::create(['guard_name' => 'api_key', 'name' => 'view consumers']);
        Permission::create(['guard_name' => 'api_key', 'name' => 'edit consumers']);
        Permission::create(['guard_name' => 'api_key', 'name' => 'view partners']);
        Permission::create(['guard_name' => 'api_key', 'name' => 'view referrers']);
        Permission::create(['guard_name' => 'api_key', 'name' => 'view loyalty']);
        Permission::create(['guard_name' => 'api_key', 'name' => 'issue coupon']);


        $admin_role = Role::create(['name' => 'admin'])
            ->givePermissionTo([
                'admin users',
                'login at runlevel 1',
                'create voucher',
                'view vouchers',
                'edit voucher',
                'redeem voucher',
                'view consumers',
                'edit consumers',
                'view reports list',
                'add new report',
                'assign voucher to user',
                'view partners',
                'edit partner',
                'view referrers',
                'edit referrer',
                'view loyalty',
                'view statements',
                'view redemptions',
                'see internal data',
                'edit all partner users',
                'update motd',
                'set runlevel',
                'cancel coupon',
                'issue coupon',
                'issue api keys',
                'admin api keys',
                'delete consumer events',
            ]);

        $rc_admin_role = Role::create(['name' => 'rc admin'])
            ->givePermissionTo([
                'admin users',
                'login at runlevel 1',
                'create voucher',
                'view vouchers',
                'edit voucher',
                'redeem voucher',
                'view consumers',
                'edit consumers',
                'view reports list',
                'add new report',
                'assign voucher to user',
                'view partners',
                'edit partner',
                'view referrers',
                'edit referrer',
                'view loyalty',
                'view statements',
                'view redemptions',
                'see internal data',
                'edit all partner users',
                'update motd',
                'set runlevel',
                'cancel coupon',
                'issue coupon',
                'issue api keys',
                'admin api keys',
                'delete consumer events',
            ]);

        $partner_user_role = Role::create(['name' => 'partner user'])
            ->givePermissionTo([
                'view vouchers',
                'redeem voucher',
                'view loyalty',
                'view statements',
                'view redemptions',
            ]);
        SystemVariable::create([
            'variable_name' => 'anonymous_signup_role',
            'variable_value' => 'partner user',
        ]);

        $marketing_role = Role::create(['name' => 'marketing'])
            ->givePermissionTo([
                'admin users',
                'create voucher',
                'view vouchers',
                'edit voucher',
                'redeem voucher',
                'view consumers',
                'edit consumers',
                'view reports list',
                'add new report',
                'view partners',
                'edit partner',
                'view referrers',
                'edit referrer',
                'see internal data',
                'edit all partner users',
                'update motd',
                'cancel coupon',
                'issue coupon',
                'issue api keys',
            ]);

        $customer_care_role = Role::create(['name' => 'customer care'])
            ->givePermissionTo([
                'view vouchers',
                'view consumers',
                'edit consumers',
                'view partners',
                'edit partner',
                'view referrers',
                'edit referrer',
                'redeem voucher',
                'see internal data',
                'edit all partner users',
            ]);

        $business_manager_role = Role::create(['name' => 'business manager'])
            ->givePermissionTo([
                'view partners',
                'edit partner',
                'view vouchers',
                'redeem voucher',
                'view referrers',
                'see internal data',
            ]);

        $api_key_role = Role::create(['guard_name' => 'api_key', 'name' => 'api key'])
            ->givePermissionTo([
                'view vouchers',
                'view consumers',
                'edit consumers',
                'view partners',
                'view referrers',
                'view loyalty',
                'issue coupon',
            ]);
    }
}
