<?php

return [
    'AccountApplicationApprovedHeader' =>
        'Account Approved',
    'AccountApplicationApprovedContent' => <<<HTML
<p>Your request for an account has been approved.</p>
<p>:approve_message</p>
<p>Regards</p>
HTML
    ,
    'AccountApplicationRejectedHeader' =>
        'Account Rejected',
    'AccountApplicationRejectedContent' => <<<HTML
<p>Your request for an account has been rejected.</p>
<p>:reject_message</p>
<p>Regards</p>
HTML

];
