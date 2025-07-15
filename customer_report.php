<?php
function getActiveCustomers(PDO $pdo, $startDate = null, $endDate = null) {
    // Default to 1 year back to today
    if ($endDate === null) {
        $endDate = date('Y-m-d');
    }
    if ($startDate === null) {
        $startDate = date('Y-m-d', strtotime('-1 year', strtotime($endDate)));
    }

    // Fetch last login per user within the period
    $loginSql = "SELECT name AS username, MAX(CONCAT(date, ' ', time)) AS last_login
                 FROM logins
                 WHERE date BETWEEN :start AND :end
                 GROUP BY name";
    $loginStmt = $pdo->prepare($loginSql);
    $loginStmt->execute([':start' => $startDate, ':end' => $endDate]);
    $logins = $loginStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$logins) {
        return [];
    }

    // Map last login times
    $loginMap = [];
    foreach ($logins as $row) {
        $loginMap[$row['username']] = $row['last_login'];
    }

    // Get balances for all users with positive total
    $placeholders = str_repeat('?,', count($loginMap) - 1) . '?';
    $balanceSql = "SELECT user AS username, SUM(amount) AS balance
                   FROM accounts
                   WHERE user IN ($placeholders)
                   GROUP BY user
                   HAVING SUM(amount) > 0";
    $balanceStmt = $pdo->prepare($balanceSql);
    $balanceStmt->execute(array_keys($loginMap));
    $balances = $balanceStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$balances) {
        return [];
    }

    $balanceMap = [];
    foreach ($balances as $row) {
        $balanceMap[$row['username']] = $row['balance'];
    }

    // Fetch user details
    $placeholders = str_repeat('?,', count($balanceMap) - 1) . '?';
    $userSql = "SELECT username, firstname, lastname
                 FROM users
                 WHERE username IN ($placeholders)";
    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute(array_keys($balanceMap));
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($users as $user) {
        $username = $user['username'];
        if (isset($balanceMap[$username]) && isset($loginMap[$username])) {
            $result[] = [
                'username' => $username,
                'firstname' => $user['firstname'],
                'lastname' => $user['lastname'],
                'last_login' => $loginMap[$username],
                'balance' => $balanceMap[$username],
            ];
        }
    }

    return $result;
}

// Example usage
/*
try {
    $pdo = new PDO('mysql:host=localhost;dbname=dbname', 'user', 'pass');
    $customers = getActiveCustomers($pdo);
    foreach ($customers as $c) {
        echo $c['username'] . " " . $c['firstname'] . " " . $c['lastname'] . " " . $c['last_login'] . " " . $c['balance'] . "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
*/
?>
