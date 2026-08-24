<?php
session_start();

// 1. SQLite Datenbank-Verbindung herstellen (Datei: prime_hosting.db)
try {
    $db = new PDO('sqlite:' . __DIR__ . '/prime_hosting.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Tabellen automatisch erstellen, falls sie nicht existieren
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS servers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            ip TEXT NOT NULL,
            slots TEXT NOT NULL,
            ram TEXT NOT NULL,
            cpu TEXT NOT NULL,
            status TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");

    // Beispiel-User anlegen (nur wenn noch keine User existieren)
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        // Test-Benutzer: Username 'kunderp', Passwort 'pass123'
        $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (:user, :pass)");
        $stmt->execute(['user' => 'kunderp', 'pass' => password_hash('pass123', PASSWORD_DEFAULT)]);
        $userId = $db->lastInsertId();

        // Server für Test-Benutzer eintragen
        $stmt = $db->prepare("INSERT INTO servers (user_id, name, ip, slots, ram, cpu, status) 
                              VALUES (:user_id, :name, :ip, :slots, :ram, :cpu, :status)");
        $stmt->execute([
            'user_id' => $userId,
            'name' => 'Prime-RP Server #1',
            'ip' => '5.180.20.12:30120',
            'slots' => '64 Slots',
            'ram' => '16 GB RAM',
            'cpu' => '2 Kerne',
            'status' => 'Online'
        ]);
    }

} catch (PDOException $e) {
    die("Datenbank-Fehler: " . $e->getMessage());
}

// 2. Login Verarbeiten
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
    } else {
        $error = 'Ungültige Anmeldedaten!';
    }
}

// 3. Logout Verarbeiten
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 4. Server des Nutzers abrufen
$userServers = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT * FROM servers WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $userServers = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime-Hosting | Kunden-Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #0f172a; color: #f8fafc; line-height: 1.6; }
        
        header { display: flex; justify-content: space-between; align-items: center; padding: 20px 10%; background-color: #1e293b; border-bottom: 2px solid #3b82f6; }
        .logo { font-size: 24px; font-weight: bold; color: #3b82f6; text-transform: uppercase; text-decoration: none; }
        .logo span { color: #f8fafc; }
        
        .container { max-width: 1000px; margin: 50px auto; padding: 0 20px; }
        .login-card { background-color: #1e293b; border: 1px solid #334155; padding: 30px; border-radius: 12px; max-width: 400px; margin: 0 auto; }
        .login-card h2 { margin-bottom: 20px; color: #3b82f6; }
        
        input { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 6px; border: 1px solid #334155; background-color: #0f172a; color: white; }
        button, .btn-logout { background-color: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        button:hover, .btn-logout:hover { background-color: #2563eb; }
        
        .error { color: #ef4444; margin-bottom: 15px; }

        .server-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .server-card { background-color: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 20px; position: relative; }
        .status-badge { position: absolute; top: 15px; right: 15px; background-color: #22c55e; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .server-info { margin-top: 15px; list-style: none; font-size: 14px; color: #cbd5e1; }
        .server-info li { margin-bottom: 5px; }
    </style>
</head>
<body>

    <header>
        <a href="index.html" class="logo">Prime-<span>Hosting</span></a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="login.php?action=logout" class="btn-logout">Abmelden</a>
        <?php endif; ?>
    </header>

    <div class="container">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Login Formular -->
            <div class="login-card">
                <h2>Kunden-Login</h2>
                <?php if ($error): ?>
                    <p class="error"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <form method="POST">
                    <label>Benutzername</label>
                    <input type="text" name="username" required placeholder="kunderp">
                    
                    <label>Passwort</label>
                    <input type="password" name="password" required placeholder="pass123">
                    
                    <button type="submit">Anmelden</button>
                </form>
            </div>

        <?php else: ?>
            <!-- Dashboard -->
            <h2>Willkommen zurück, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <p style="color: #94a3b8; margin-bottom: 30px;">Hier findest du deine freigeschalteten FiveM-Server.</p>

            <h3>Deine aktiven Server</h3>
            <?php if (empty($userServers)): ?>
                <p style="margin-top: 15px; color: #cbd5e1;">Dir wurden noch keine Server zugewiesen.</p>
            <?php else: ?>
                <div class="server-grid">
                    <?php foreach ($userServers as $server): ?>
                        <div class="server-card">
                            <span class="status-badge"><?php echo htmlspecialchars($server['status']); ?></span>
                            <h4 style="color: #3b82f6; font-size: 20px;"><?php echo htmlspecialchars($server['name']); ?></h4>
                            <ul class="server-info">
                                <li><strong>IP-Adresse:</strong> <?php echo htmlspecialchars($server['ip']); ?></li>
                                <li><strong>Slots:</strong> <?php echo htmlspecialchars($server['slots']); ?></li>
                                <li><strong>Hardware:</strong> <?php echo htmlspecialchars($server['ram']); ?> / <?php echo htmlspecialchars($server['cpu']); ?></li>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</body>
</html>
