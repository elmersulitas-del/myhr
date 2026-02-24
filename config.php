<?php
// config.php
session_start();

define('GOOGLE_CLIENT_ID', '114629538600-4enr9va17bt69r21nqp7594vlmou60aa.apps.googleusercontent.com');

define('GOOGLE_CLIENT_SECRET', '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');

// IMPORTANT: Must exactly match your "Authorized redirect URI"
define('GOOGLE_REDIRECT_URI', 'http://localhost/myhr/google_callback.php');

// Only allow this domain:
define('ALLOWED_DOMAIN', 'immaculada.edu.ph');