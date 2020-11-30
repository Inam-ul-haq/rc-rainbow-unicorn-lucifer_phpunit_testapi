<?php

namespace App\Imports;

use DB;
use App\User;
use App\Partner;
use App\PartnerGroup;
use App\Imports\ImportBase;
use CoastDigital\Clixray\Requests;
use Grimzy\LaravelMysqlSpatial\Types\Point;

class CrmPartnersImport extends ImportBase implements CrmImports
{
    /**
     * We check the first line matches this, just in case the record layout gets changed
     */
    private $expected_fields =
        [
            'AccountNumber',
            'Name',
            'Address1',
            'Address2',
            'AddressCity',
            'AddressCounty',
            'AddressPostCode',
            'AddressCountry',
            'Phone',
            'Email',
            'JwbVouchers',
            'RcVouchers',
            'JwbStockist',
            'RcStockist',
            'GreenVouchers',
            'GreenStockist',
            'CrmAccount',
            'VetoStatId',
            'AccountId',
            'CustomerType',
            'VetVouchers',
            'VetStockist',
            'JwbManager',
            'RcManager',
            'VendorNumber',
            'CompanyClass',
            'VFU_VetFollowUp',
            'Latitude',
            'Longitude',
        ];

    private $loyalty_groups =
        [
            'loyalty-vet',
            'loyalty-retail',
            'Loyalty All (Vet & Retail)',
        ];

    private $business_managers = [];
    private $partner_accounts = [];
    private $crm_partner_groups = [];
    private $imported_extids = [];
    private $clixray_partner_groups = [];
    private $exception_on_failure = 0;
    private $initial_import = 1;
    protected $clixray_link = null;
    protected $default_location_point = null;

    public function __construct($filename, $exception_on_failure = 1)
    {
        parent::__construct($filename);
        $this->exception_on_failure = $exception_on_failure;
        $this->clixray_link = new \CoastDigital\Clixray\Requests(config('clixray.services'));
        $this->clixray_partner_groups = $this->clixray_link->getPartnerGroupDetailsByHandle();

        $this->default_location_point = new Point(54.091399, -2.963779);
    }

    public function import()
    {
        while (($fields = fgetcsv($this->file_handle)) !== false) {
            print '.';
            self::processFields($fields);
        }
    }

    private function processFields($fields = [])
    {

        static $first_line_checked = false;
        static $record_number = 0;
        $record_number++;

        if (count($fields) === 0) {
            return;
        }

        if ($first_line_checked === false) {
            $this->checkFirstLineFieldsCount($fields, $this->expected_fields);
            $first_line_checked = true;
            return;
        }

        if ($this->checkDataLineFieldsCount($fields, $this->expected_fields, $record_number) === false) {
            return;
        }

        ## For now, if there's no manager, we'll add them but report it in warnings
        if ($fields[23] === '') {
            $this->warnings[] = __('CRMImports.no_rcmanager', ['record_number' => $record_number]);
        }

        $rc_manager_email = $fields[23];

        $type = null;
        switch ($fields[19]) {
            case '06-RETAIL':
                $type = 'retailer';
                break;
            case '07-VET':
                $type = 'vet';
                break;
            default:
                $this->warnings[] = __(
                    'CRMImports.invalid_type',
                    [
                        'type' => $fields[19],
                        'record_number' => $record_number
                    ]
                );
                return;
        }

        $country = null;
        switch ($fields[7]) {
            case 'IE':
            case 'Ireland':
            case 'COUNTY CORK':
                $country = 'Republic of Ireland';
                break;

            case 'GB':
            case 'United Kingdom':
            case 'GB-NI':
            case 'GB NI':
            case '':
                $country = 'United Kingdom';
                break;

            case 'GG':
            case 'JE':
                $country = 'Channel Islands';
                break;


            default:
                $this->warnings[] = __(
                    'CRMImports.invalid_country',
                    ['country' => $fields[7],
                    'record_number' => $record_number]
                );
                return;
        }

        // If a VetoStatId is provided, use that as the ext_id. If that's missing, use the AccountNumber.
        // That can also sometimes be missing though, for example with PetsAtHome stores.
        // In that case, use the CRMNumber.

        if ($fields[17]) {
            $ext_id = $fields[17];
        } elseif ($fields[0]) {
            $ext_id = $fields[0];
        } elseif ($fields[16]) {
            $ext_id = $fields[16];
        } else {
            $this->warnings[] = __('CRMImports.no_extid', ['record_number' => $record_number]);
        }

        if (isset($this->imported_extids[$ext_id])) {
            $this->warnings[] = __(
                'CRMImports.extid_already_seen',
                [
                    'record_number' => $record_number,
                    'extid' => $ext_id,
                    'imported_by' => $this->imported_extids[$ext_id]
                ]
            );
            return;
        }

        try {
            $clixray_partner = $this->clixray_link->getPartnerDetails($ext_id);
        } catch (\Exception $e) {
            $this->warnings[] = __(
                'CRMImports.clixray_error_getting_partner',
                [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'record_number' => $record_number,
                    'extid' => $fields[0]
                ]
            );
            return;
        }

        $location_point = $this->default_location_point;
        $exclude_from_spatial_search = 1;

        if ((strlen($fields[27]) and strlen($fields[28])) and
             $fields[27] != 0 and $fields[28] != 0) {
            $location_point = new Point($fields[27], $fields[28]);
            $exclude_from_spatial_search = 0;
        }

        $records_created = [];

        DB::beginTransaction();
        try {
            $partner = new Partner(
                [
                    'type' => $type,
                    'public_name' => e($fields[1]),
                    'location_point' => $location_point,
                    'exclude_from_spatial_search' => $exclude_from_spatial_search,
                    'contact_name_title_id' => null,
                    'contact_first_name' => null,
                    'contact_last_name' => null,
                    'contact_telephone' => $fields[8],
                    'contact_email' => $fields[9],
                    'public_street_line1' => $fields[2],
                    'public_street_line2' => $fields[3],
                    'public_town' => $fields[4],
                    'public_county' => $fields[5],
                    'public_postcode' => $fields[6],
                    'public_country' => $country,
                    'public_email' => $fields[9],
                    'public_vat_number' => $clixray_partner[0]->partner_vat,
                    'accepts_vouchers' => $fields[10] === 'Y' ? true : false,
                    'accepts_loyalty' => false,
                    'crm_id' => $ext_id,
                ]
            );

            $partner->save();
            $records_created['partner'] = 1;

            if (!isset($this->business_managers[$rc_manager_email])) {
                $this->business_managers[$rc_manager_email] = new User(
                    [
                        'email' => $rc_manager_email,
                        'name' => $this->getNameFromEmailAddress($rc_manager_email),
                        'password' => str_random(8),
                        'password_change_needed' => 1,
                    ]
                );
                $this->business_managers[$rc_manager_email]->save();
                $this->business_managers[$rc_manager_email]->assignRole('business manager');
                $records_created['business_manager'] = 1;
            }

            $partner->salesReps()->attach($this->business_managers[$rc_manager_email]);

            /* $fields[25] is the CompanyClass, and each partner is put into a group of that name. The handle
             * though on Clixray looked a bit random at times, so to maintain consistency between Clixray and
             * Unicorn, we'll grab the handle from Clixray and use that.
             * Also, at least on of the CompanyClass groups does not exist on Clixray at the moment. If that's
             * the case, then we'll just have to do a best effort on the handle.
             */

            $group_ids_to_attach = [];

            $group = $this->getClixrayPartnerGroupDetailsByName($fields[25]);

            $handle = $group->partner_group_handle ?? $this->derivePartnerGroupHandle($fields[25]);

            if (!isset($this->partner_groups[$handle])) {
                $this->partner_groups[$handle] = new PartnerGroup(
                    [
                        'group_name' => $fields[25],
                        'managed_remotely' => 1,
                        'group_ref' => $handle,
                    ]
                );
                $this->partner_groups[$handle]->save();
                $records_created['partner_groups'] = 1;
            }

            $group_ids_to_attach[] = $this->partner_groups[$handle]->id;

            foreach ($clixray_partner[0]->partner_groups as $handle) {
                if (!isset($this->partner_groups[$handle])) {
                    $this->partner_groups[$handle] = new PartnerGroup(
                        [
                            'group_name' => $this->getClixrayPartnerGroupDetailsByHandle($handle)->partner_group_name,
                            'managed_remotely' => 0,
                            'group_ref' => $handle,
                        ]
                    );
                    $this->partner_groups[$handle]->save();
                    if (empty($records_created['partner_groups'])) {
                        $records_created['partner_groups'] = 0;
                    }
                    $records_created['partner_groups']++;
                }
                $group_ids_to_attach[] = $this->partner_groups[$handle]->id;
            }

            $partner->groups()->attach($group_ids_to_attach);

            if ($this->partnerAcceptsLoyalty($partner)) {
                $partner->accepts_loyalty = true;
                $partner->save();
            }

            $this->imported_extids[$ext_id] = $record_number;
        } catch (\Exception $e) {
            DB::rollback();
            $this->warnings[] = __(
                'CRMImports.partner_exception',
                [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'record_number' => $record_number
                ]
            );
            if ($this->exception_on_failure) {
                throw $e;
            }
            return;
        }

        DB::commit();

        foreach ($records_created as $type => $count) {
            if (empty($this->records_created_summary[$type])) {
                $this->records_created_summary[$type] = 0;
            }
            $this->records_created_summary[$type] += $count;
        }
    }

    private function getClixrayPartnerGroupDetailsByHandle($handle)
    {
        static $column_vals = [];
        if (count($column_vals) === 0) {
            $column_vals = array_column($this->clixray_partner_groups, 'partner_group_handle');
        }
        $key = array_search($handle, $column_vals);
        if ($key !== false) {
            return $this->clixray_partner_groups[$key];
        }

        return false;
    }

    private function getClixrayPartnerGroupDetailsByName($name)
    {
        static $column_vals = [];
        if (count($column_vals) === 0) {
            $column_vals = array_column($this->clixray_partner_groups, 'partner_group_name');
        }
        $key = array_search($name, $column_vals);
        if ($key !== false) {
            return $this->clixray_partner_groups[$key];
        }

        return false;
    }

    /**
     * Try to create something suitable for a partner group reference based on a group name.
     * Convert $name to lowercase, replace ' ' with '_'.
     * If name has more than one set of brackets, replace brackets with '-' (except for the
     * last set, which are ditched).
     * So, "PAH Vet Group (Vets4Pets In Store) (3108-2)" would become: "pah_vet_group-vets4pets_in_store"
     * & "Out of Town Offline UK (1001-2)" would become "out_of_town_offline_uk".
     *
     * @param $name - name of group to base return value on
     *
     * @return string
     */
    private function derivePartnerGroupHandle($name)
    {
        $handle = strtolower(str_replace(' ', '_', $name));
        $parts = explode('(', $handle);
        array_pop($parts);
        $handle = implode('-', $parts);
        $handle = str_replace('_-', '-', $handle);
        $handle = str_replace(')', '', $handle);
        $handle = substr($handle, 0, -1);
        return $handle;
    }

    /**
     * Determine if a partner should accept loyalty vouchers or not, based on their group membership
     *
     * @param $partner - Partner object
     *
     * @return - number of groups partner is a member of that are loyalty groups
     */
    private function partnerAcceptsLoyalty($partner)
    {
        return count(array_intersect($partner->groups()->pluck('group_ref')->toArray(), $this->loyalty_groups));
    }
}
