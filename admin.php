<?php

$user = 'u82196';
$pass = '4736526';
$db_name = 'u82196';
$host = 'localhost';

$admin_login = 'admin';
// Хеш для пароля "123" (сгенерирован через password_hash)
$admin_pass_hash = '$2y$10$3eYv/O0LpE0N6U0j1K6Z3OBvV02E/nNclgXgOQ1lE.y7wS7Y5yO1a';

if (empty($_SERVER['PHP_AUTH_USER'])) {
    if (preg_match('/Basic\s+(.*)$/i', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '', $matches)) {
        list($user_msg, $pw_msg) = explode(':', base64_decode($matches[1]));
        $_SERVER['PHP_AUTH_USER'] = $user_msg;
        $_SERVER['PHP_AUTH_PW'] = $pw_msg;
    }
}

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== $admin_login || 
    !password_verify($_SERVER['PHP_AUTH_PW'] ?? '', $admin_pass_hash)) {
    
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Доступ запрещен. Введите корректные данные.');
}

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $db = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die('Ошибка: неверный CSRF-токен');
        }

        // УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $db->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM application WHERE id = ?")->execute([$id]);
            header('Location: admin.php');
            exit();
        }

        // РЕДАКТИРОВАНИЕ ДАННЫХ 
        if (isset($_POST['edit_id'])) {
            $id = (int)$_POST['edit_id'];
            $stmt = $db->prepare("UPDATE application SET name=?, email=?, bio=? WHERE id=?");
            $stmt->execute([$_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['bio'] ?? '', $id]);
            header('Location: admin.php');
            exit();
        }
    }

    // СТАТИСТИКА ПО ЯЗЫКАМ
    $sql_stats = "
        SELECT l.name, COUNT(al.application_id) as count 
        FROM languages l
        LEFT JOIN application_languages al ON l.id = al.language_id
        GROUP BY l.id, l.name
    ";
    $stats = $db->query($sql_stats)->fetchAll();

    // СПИСОК ВСЕХ ПОЛЬЗОВАТЕЛЕЙ
    $users = $db->query("SELECT * FROM application ORDER BY id DESC")->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Ошибка базы данных. Администратор уведомлен.');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админка</title>
    <style>
        body { font-family: sans-serif; padding: 30px; }
        .container { max-width: 1000px; margin: auto; padding: 20px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #aaaaaa; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .stats { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .stat-item { background: #e9ecef; padding: 10px 15px; border-radius: 4px; }
        input, textarea { width: 100%; box-sizing: border-box; padding: 5px; }
        .btn-save { background: #28a745; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px; }
        .btn-del { background: none; border: none; color: #dc3545; cursor: pointer; padding: 0; font-size: 0.9em; margin-left: 10px; }
        .inline-form { display: inline; }
    </style>
</head>
<body>
<div class="container">
    <h1>Панель администратора</h1>

    <h3>Статистика по языкам:</h3>
    <div class="stats">
        <?php foreach ($stats as $s): ?>
            <?php if($s['count'] > 0): ?>
                <div class="stat-item">
                    <strong><?php echo htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?>:</strong> 
                    <?php echo (int)$s['count']; ?> чел.
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <h3>Список пользователей:</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>ФИО</th>
            <th>Email</th>
            <th>Биография</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($users as $u): ?>
        <tr>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <td>
                    <?php echo (int)$u['id']; ?>
                    <input type="hidden" name="edit_id" value="<?php echo (int)$u['id']; ?>">
                </td>
                <td><input type="text" name="name" value="<?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></td>
                <td><input type="text" name="email" value="<?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></td>
                <td><textarea name="bio"><?php echo htmlspecialchars($u['bio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></td>
                <td>
                    <button type="submit" class="btn-save">OK</button>
                </form>
                <form method="POST" class="inline-form" onsubmit="return confirm('Удалить пользователя?')">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                    <button type="submit" class="btn-del">Удалить</button>
                </form>
               </td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <br>
    <a href="index.php">← Назад к форме</a>
</div>
</body>
</html>
