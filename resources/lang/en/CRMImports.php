<?php

return [
    'invalid_type' =>
        'Unrecognised type :type found on record :record_number',
    'invalid_country' =>
        'Unrecognised country :country found on record :record_number',
    'clixray_error_getting_partner' =>
        'Error from Clixray whilst trying to retrieve details for extid :extid on record :record_number. Code :code, Error :message',
    'no_extid' =>
        'Unable to extract ext_id from record :record_number',
    'no_rcmanager' =>
        'Adding partner for record :record_number, but no value set for RcManager',
    'partner_exception' =>
        'Exception whilst adding new partner and relationships. Code :code, Error :message, record :record_number',
    'extid_already_seen' =>
        'Record :record_number - A record for extid :extid was already imported by record :imported_by',
];
