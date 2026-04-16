<?php
$file = 'personnel_login.php';
$content = file_get_contents($file);

$content = str_replace(
    "require_once 'db_config.php';",
    "require_once 'db_config.php';\nrequire_once 'rate_limit.php';",
    $content
);

$search = "    if (\$role === 'gate') {";
$replace = "    if (!check_rate_limit('personnel_login', 5, 15)) {\n        \$error = 'Too many failed login attempts. Please try again after 15 minutes.';\n    } else {\n        if (\$role === 'gate') {";
$content = str_replace($search, $replace, $content);

$search2 = "        if (\$role === 'gate') {\n            header(\"Location: gate.php\");";
$replace2 = "        clear_attempts('personnel_login');\n        if (\$role === 'gate') {\n            header(\"Location: gate.php\");";
$content = str_replace($search2, $replace2, $content);

$search3 = "    } else {\n        \$error = \"Invalid username or password.\";\n    }\n}";
$replace3 = "    } else {\n        record_failed_attempt('personnel_login');\n        \$error = \"Invalid username or password.\";\n    }\n    }\n}";
$content = str_replace($search3, $replace3, $content);

file_put_contents($file, $content);

$file2 = 'gate/login.php';
$content2 = file_get_contents($file2);

$content2 = str_replace(
    "require_once '../db_config.php';",
    "require_once '../db_config.php';\nrequire_once '../rate_limit.php';",
    $content2
);

$search4 = "    if (empty(\$username) || empty(\$password)) {\n        \$error = 'Username and password are required.';\n    } else {\n        try {";
$replace4 = "    if (empty(\$username) || empty(\$password)) {\n        \$error = 'Username and password are required.';\n    } else if (!check_rate_limit('gate_login', 5, 15)) {\n        \$error = 'Too many failed login attempts. Please try again after 15 minutes.';\n    } else {\n        try {";
$content2 = str_replace($search4, $replace4, $content2);

$search5 = "            if (\$personnel && password_verify(\$password, \$personnel['password'])) {\n                // Login successful";
$replace5 = "            if (\$personnel && password_verify(\$password, \$personnel['password'])) {\n                clear_attempts('gate_login');\n                // Login successful";
$content2 = str_replace($search5, $replace5, $content2);

$search6 = "            } else {\n                \$error = 'Invalid username or password.';\n            }";
$replace6 = "            } else {\n                record_failed_attempt('gate_login');\n                \$error = 'Invalid username or password.';\n            }";
$content2 = str_replace($search6, $replace6, $content2);

file_put_contents($file2, $content2);
echo "Injected rate limits!";
?>
