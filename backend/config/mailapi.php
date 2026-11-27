<?php

return [
    // Brevo (https://www.brevo.com) transactional e-mail over HTTPS: 300 free e-mails a day.
    // Some hosts (for example Render's free plan) block the SMTP ports, so SMTP cannot be used there.
    // When BREVO_API_KEY is set the reset codes are sent through Brevo's API, otherwise through the normal
    // Laravel mailer (MAIL_MAILER: log, smtp, ...). The sender (MAIL_FROM_ADDRESS) must be verified in Brevo.
    'brevo_key' => env('BREVO_API_KEY'),
];
