<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This setup script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/config/database.php';
$db = getDatabaseConnection();

$users = [
    [
        'role' => 'admin',
        'name' => 'Campus Coin Admin',
        'email' => 'admin@campuscoin.test',
        'password' => 'Admin@123',
        'academic_year' => null,
        'monthly_allowance' => 0,
        'monthly_savings_goal' => 0,
    ],
    [
        'role' => 'student',
        'name' => 'Demo Student',
        'email' => 'student@campuscoin.test',
        'password' => 'Student@123',
        'academic_year' => 'First Year',
        'monthly_allowance' => 25000,
        'monthly_savings_goal' => 5000,
    ],
];

$statement = $db->prepare(
    'INSERT INTO users
        (role, name, email, password_hash, academic_year, monthly_allowance, monthly_savings_goal, is_active)
     VALUES
        (:role, :name, :email, :password_hash, :academic_year, :allowance, :goal, 1)
     ON DUPLICATE KEY UPDATE
        role = VALUES(role), name = VALUES(name), password_hash = VALUES(password_hash),
        academic_year = VALUES(academic_year), monthly_allowance = VALUES(monthly_allowance),
        monthly_savings_goal = VALUES(monthly_savings_goal), is_active = 1'
);

foreach ($users as $user) {
    $statement->execute([
        'role' => $user['role'],
        'name' => $user['name'],
        'email' => $user['email'],
        'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
        'academic_year' => $user['academic_year'],
        'allowance' => $user['monthly_allowance'],
        'goal' => $user['monthly_savings_goal'],
    ]);
}

echo "Demo users created successfully.\n";
echo "Student: student@campuscoin.test / Student@123\n";
echo "Admin: admin@campuscoin.test / Admin@123\n";
