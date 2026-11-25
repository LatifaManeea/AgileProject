<?php
// Database settings.
// Override them with environment variables (DB_HOST, DB_PORT, DB_NAME, DB_USER,
// DB_PASS) to run the system in another environment without editing code.
// The defaults match the original MAMP setup.
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_port = getenv('DB_PORT') ?: '8889';
$db_name = getenv('DB_NAME') ?: 'WebProject';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root';
