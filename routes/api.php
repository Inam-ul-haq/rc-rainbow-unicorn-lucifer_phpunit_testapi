<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'v0'], function () {
    Route::middleware('auth:api')->get('/user', function (Request $request) {
        return $request->user();
    });

    ## These ones are for consumers
    Route::post('/consumer/register', 'ConsumerAuthController@register');
    Route::post('/consumer/login', 'ConsumerAuthController@login');
    Route::get('/consumer/logout', 'ConsumerAuthController@logout');
    Route::get('/consumer/refresh_token', 'ConsumerAuthController@refresh');
    Route::get('/consumer/forgot_password', 'ConsumerAuthController@forgotPw');
    Route::post('/consumer/reset_password', 'ConsumerAuthController@forgotPwReset');

    ## These ones are for internal users
    Route::post('/user/register', 'UserAuthController@register');
    Route::post('/user/login', 'UserAuthController@login');
    Route::get('/user/logout', 'UserAuthController@logout');
    Route::get('/user/refresh_token', 'UserAuthController@refresh');
    Route::get('/user/forgot_password', 'UserAuthController@forgotPw');
    Route::post('/user/reset_password', 'UserAuthController@forgotPwReset');

    Route::group(
        [
            'prefix' => 'system',
        ],
        function () {
            Route::get('/status', '\Helper@systemStatus');

            Route::group(
                [
                    'middleware' => ['auth:api', 'permission:update motd']
                ],
                function () {
                    Route::patch('/motd', 'SystemVariableController@updateMotd');
                }
            );

            Route::group(
                [
                    'middleware' => ['auth:api', 'permission:set runlevel']
                ],
                function () {
                    Route::patch('/runlevel', 'SystemVariableController@updateRunLevel');
                }
            );
        }
    );

    Route::group(
        [
            'prefix' => 'user',
        ],
        function () {
            Route::group(
                [
                    'middleware' => ['auth:api', 'permission:admin users'],
                ],
                function () {
                    Route::get('/{user}/block', 'UserController@block');
                    Route::get('/{user}/unblock', 'UserController@unblock');
                }
            );
        }
    );

    Route::group(['prefix' => 'metadata'], function () {
        Route::get('/name_titles', 'NameTitleController@list');
        Route::get('/pets/species', 'SpeciesController@index');
        Route::get('/pets/breeds', 'BreedController@index');
        Route::get('/pets/species/{species}/breeds', 'BreedController@index');
    });

    Route::group(['prefix' => 'metadata', 'middleware' => ['auth:api', 'permission:see internal data'] ], function () {
        Route::get('/tags', 'TagController@index');
    });

    Route::group(['prefix' => 'event', 'middleware' => ['auth:api,api_key']], function () {
        Route::post('/', 'ConsumerEventController@store');
        Route::group(['permission:see internal data'], function () {
            Route::post('/search', 'ConsumerEventController@search');
            Route::get('/{consumer_event}', 'ConsumerEventController@show');
            Route::group(['permission:delete consumer events'], function () {
                Route::delete('/{consumer_event}', 'ConsumerEventController@destroy');
            });
        });
    });

    Route::group(['prefix' => 'partner'], function () {
        Route::get('/{crm_id}/get_access_question', 'PartnerController@accessQuestion');
        Route::post('/{crm_id}/check_access_answer', 'PartnerController@checkAccessAnswer');
    });

    Route::group(
        [
            'prefix' => 'users',
        ],
        function () {
            Route::group(
                [
                    'middleware' => ['auth:api', 'permission:admin users'],
                ],
                function () {
                    Route::get('/search', 'UserController@search');
                }
            );
        }
    );

    Route::group(['middleware' => ['auth:api']], function () {
        Route::apiResource('users', 'UserController');
    });

    Route::group(['prefix' => 'user', 'middleware' => ['auth:api'] ], function () {
        Route::get('/{user}/permissions', 'UserController@permissions');
        Route::post(
            '/{user}/{partner}/reject_partner_account_application',
            'UserController@rejectPartnerAccountApplication'
        );
        Route::post(
            '/{user}/{partner}/approve_partner_account_application',
            'UserController@approvePartnerAccountApplication'
        );
        Route::get('/{user}/partners', 'UserController@getPartnerAccounts');
        Route::get('/{user}/{partner}/make_manager', 'UserController@makePartnerManager');
        Route::get('/{user}/{partner}/remove_manager', 'UserController@removePartnerManager');

        Route::delete('/{user}/{partner}', 'UserController@removeAccountFromPartner');
    });

    Route::group(
        [
            'prefix' => 'api_keys',
            'middleware' => ['auth:api', 'permission:issue api keys|admin api keys'],
        ],
        function () {
            Route::post('/', 'PersonalAccessTokenController@store');
            Route::get('/', 'PersonalAccessTokenController@index');
            Route::get('/{token}/revoke', 'PersonalAccessTokenController@revoke');
            Route::patch('/{token}', 'UserApiKeyController@update');
        }
    );

    Route::group(['prefix' => 'partner', 'middleware' => ['auth:api,api_key'] ], function () {
        Route::get('/{partner}', 'PartnerController@show');
    });

    Route::group(['prefix' => 'partner', 'middleware' => ['auth:api'] ], function () {
        Route::get('/{partner}/accept_loyalty', 'PartnerController@acceptLoyalty');
        Route::get('/{partner}/refuse_loyalty', 'PartnerController@refuseLoyalty');
        Route::get('/{partner}/accept_vouchers', 'PartnerController@acceptVouchers');
        Route::get('/{partner}/refuse_vouchers', 'PartnerController@refuseVouchers');
        Route::get('/{partner}/redemptions', 'PartnerController@couponRedemptions');
        Route::get('/{partner}/redemptions/csv', 'PartnerController@couponRedemptionsCsv');
        Route::get('/{partner}/pending_accounts', 'PartnerController@getPendingAccountApprovals');
        Route::get('/{partner}/partner_accounts', 'PartnerController@getPartnerAccounts');
        Route::post('/{partner}/add/user', 'PartnerController@addUserAccount');
    });

    Route::group(['prefix' => 'referrer', 'middleware' => ['auth:api'] ], function () {
        Route::get('/{referrer}', 'ReferrerController@show');
        Route::get('/{referrer}/redemptions', 'ReferrerController@couponRedemptions');
        Route::get('/{referrer}/redemptions/csv', 'ReferrerController@couponRedemptionsCsv');
    });

    Route::group(['prefix' => 'coupon', 'middleware' => ['auth:api'] ], function () {
        Route::get('/{coupon}', 'CouponController@show');

        Route::group(
            [
                'middleware' => ['auth:api', 'permission:cancel coupon'],
            ],
            function () {
                Route::get('/{coupon}/cancel', 'CouponController@cancel');
            }
        );
    });

    Route::group(['prefix' => 'barcode', 'middleware' => ['auth:api'] ], function () {
        Route::group(
            [
                'middleware' => ['auth:api', 'permission:redeem voucher'],
            ],
            function () {
                Route::get('/{barcode}/{partner}/redeem', 'CouponController@redeem');
            }
        );
        Route::group(
            [
                'middleware' => ['auth:api', 'permission:view vouchers'],
            ],
            function () {
                Route::get('/{barcode}/check_validity', 'CouponController@checkValidity');
            }
        );
    });

    Route::group(['prefix' => 'consumers', 'middleware' => ['auth:api', 'permission:view consumers'] ], function () {
        Route::get('/', 'ConsumerController@list');
        Route::get('/search', 'ConsumerController@search');
        Route::get('/search/csv', 'ConsumerController@searchCSV');
    });

    Route::group(['prefix' => 'consumer', 'middleware' => ['auth:api', 'permission:edit consumers'] ], function () {
        Route::get('/{consumer}/activate', 'ConsumerController@activate');
        Route::get('/{consumer}/deactivate', 'ConsumerController@deactivate');
        Route::get('/{consumer}/add_blacklist', 'ConsumerController@addToBlacklist');
        Route::get('/{consumer}/remove_blacklist', 'ConsumerController@removeFromBlacklist');
        Route::post('/{consumer}/update_tags', 'ConsumerController@updateTags');
        Route::patch('/{consumer}', 'ConsumerController@update');
        Route::delete('/{consumer}', 'ConsumerController@destroy');
        Route::post('/{consumer}/reset_pw', 'ConsumerAuthController@adminSendPasswordReset');
        Route::get('/{consumer}/activity', 'ConsumerController@getActivity');
        Route::get('/{consumer}/export_data', 'ConsumerController@exportPersonalData');
        Route::get('/{consumer}/available_vouchers', 'ConsumerController@vouchersAvailableForIssue');
    });

    Route::group(
        ['prefix' => 'consumer', 'middleware' => ['auth:api,api_key', 'permission:edit consumers'] ],
        function () {
            Route::post('/add_to_campaign', 'ConsumerController@addToCampaign');
        }
    );

    Route::group(
        ['prefix' => 'consumer', 'middleware' => ['auth:api,api_key', 'permission:view consumers'] ],
        function () {
            Route::get('/email/{consumer_email}', 'ConsumerController@getByEmail');
            Route::get('/{consumer}', 'ConsumerController@show');
            Route::get('/{consumer}/coupons', 'CouponController@consumerCoupons');
            Route::get('/{consumer}/partners', 'ConsumerController@getAssociatedPartners');
        }
    );

    Route::group(['prefix' => 'notification', 'middleware' => ['auth:api'] ], function () {
        Route::get('/{notification}', 'JobNotificationController@show');
    });

    Route::group(['prefix' => 'pet', 'middleware' => ['auth:api,api_key', 'permission:view consumers'] ], function () {
        Route::post('/add', 'PetController@store');
        Route::get('/{pet}', 'PetController@show');
    });

    Route::group(['prefix' => 'partners', 'middleware' => ['auth:api', 'permission:view partners'] ], function () {

        Route::group(['middleware' => ['auth:api', 'permission:edit partner']], function () {
            Route::patch('/groups/{partner_group}', 'PartnerGroupController@update');
            Route::delete('/groups/{partner_group}', 'PartnerGroupController@destroy');
            Route::post('/groups', 'PartnerGroupController@store');

            Route::post('/groups/{partner_group}/partners/add', 'PartnerGroupController@addMultiplePartners');
            Route::get('/groups/{partner_group}/partners/add/{partner}', 'PartnerGroupController@addPartner');
            Route::delete('/groups/{partner_group}/partners/{partner}', 'PartnerGroupController@removePartner');
            Route::post('/groups/{partner_group}/partners/remove', 'PartnerGroupController@removeMultiplePartners');
            Route::get('/groups/{partner_group}/members', 'PartnerGroupController@getGroupMembers');
        });

        Route::get('/get_ids', 'PartnerController@getIdsList');
        Route::get('/groups', 'PartnerGroupController@index');
        Route::get('/groups/{partner_group}', 'PartnerGroupController@show');

        Route::get('/', 'PartnerController@list');
    });

    Route::group(
        ['prefix' => 'partners', 'middleware' => ['auth:api,api_key', 'permission:view partners'] ],
        function () {
            Route::get('/distance', 'PartnerController@getByDistance');
            Route::get('/search', 'PartnerController@search');
        }
    );

    Route::group(
        [
            'prefix' => 'referrers',
            'middleware' => [
                'auth:api',
                'permission:view referrers',
            ]
        ],
        function () {

            Route::get('/', 'ReferrerController@list');
            Route::get('/search', 'ReferrerController@search');
            Route::get('/search/csv', 'ReferrerController@searchCSV');

            Route::group(
                [
                    'middleware' => [
                        'auth:api',
                        'permission:edit referrer',
                    ],
                ],
                function () {


                    Route::patch(
                        '/groups/{referrer_group}',
                        'ReferrerGroupController@update'
                    );

                    Route::delete(
                        '/groups/{referrer_group}',
                        'ReferrerGroupController@destroy'
                    );

                    Route::post(
                        '/groups',
                        'ReferrerGroupController@store'
                    );

                    Route::post(
                        '/groups/{referrer_group}/referrers/add',
                        'ReferrerGroupController@addMultipleReferrers'
                    );

                    Route::get(
                        '/groups/{referrer_group}/referrers/add/{referrer}',
                        'ReferrerGroupController@addReferrer'
                    );

                    Route::delete(
                        '/groups/{referrer_group}/referrers/{referrer}',
                        'ReferrerGroupController@removeReferrer'
                    );

                    Route::post(
                        '/groups/{referrer_group}/referrers/remove',
                        'ReferrerGroupController@removeMultipleReferrers'
                    );

                    Route::get(
                        '/groups/{referrer_group}/members',
                        'ReferrerGroupController@getGroupMembers'
                    );
                }
            );

            Route::get(
                '/groups',
                'ReferrerGroupController@index'
            );

            Route::get(
                '/groups/{referrer_group}',
                'ReferrerGroupController@show'
            );
        }
    );

    Route::group(['prefix' => 'voucher'], function () {
        Route::group(['middleware' => ['auth:api', 'permission:create voucher']], function () {
            Route::post('/', 'VoucherController@create');
            Route::get('/{voucher}/clone', 'VoucherController@clone');
        });

        Route::group(['middleware' => ['auth:api', 'permission:edit voucher']], function () {
            Route::put('/{voucher}', 'VoucherController@update');
            Route::post('/{voucher}/generate_unique_codes', 'VoucherController@generateUniqueCodes');
            Route::post('/{voucher}/upload_unique_codes', 'VoucherController@uploadUniqueCodes');
            Route::post('/{voucher}/referrers/upload', 'VoucherController@uploadReferrerIDsFile');
            Route::delete('/{voucher}/referrers/{referrer}', 'VoucherController@removeReferrerRestriction');
            Route::delete('/{voucher}/unique_codes/{code}', 'VoucherUniqueCodeController@destroy');
        });

        Route::group(['middleware' => ['auth:api,api_key', 'permission:view vouchers']], function () {
            Route::get('/{voucher}/performance', 'VoucherController@performance');
            Route::get('/{voucher}/get_referrer_points_list', 'VoucherController@referrerPointsList');
            Route::get('/{voucher}/get_referrer_points_list/csv', 'VoucherController@referrerPointsListCsv');
            Route::get('/get_referrer_points_list/csv', 'VoucherController@referrerPointsListCsv');
            Route::get('/{voucher}/unique_codes', 'VoucherController@getUniqueCodesList');
            Route::get('/{voucher}/subscribers', 'VoucherController@getSubscribers');
            Route::get('/{voucher}/subscribers/csv', 'VoucherController@subscribersCsv');
            Route::get('/{voucher}/unique_code/{code}/status', 'VoucherUniqueCodeController@checkStatus');
        });

        Route::group(['middleware' => ['auth:api,api_key', 'permission:view vouchers']], function () {
            Route::get('/reference/{reference}', 'VoucherController@getByReference');
            Route::get('/{voucher}', 'VoucherController@show');
            Route::get('/{voucher}/partners/distance', 'VoucherController@getValidPartnersListByDistance');
            Route::get('/{voucher}/partners/count', 'VoucherController@getValidPartnersCount');
            Route::get('/{voucher}/partners', 'VoucherController@getValidPartnersList');
            Route::get('/{voucher}/referrers', 'VoucherController@getValidReferrersList');
        });

        Route::group(['middleware' => ['auth:api,api_key', 'permission:issue coupon']], function () {
            Route::post('/{voucher}/{consumer}/issue', 'VoucherController@issueVoucherCoupon');
        });
    });

    Route::group(['prefix' => 'vouchers'], function () {
        Route::group(['middleware' => ['auth:api,api_key', 'permission:view vouchers']], function () {
            Route::get('/', 'VoucherController@index');
        });
    });
});
