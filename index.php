<?php
header('Content-Type: text/html; charset=UTF-8');

$user = 'u82196';
$pass = '4736526';
$db_name = 'u82196';
$host = 'localhost';

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $messages = array();

    if (!empty($_COOKIE['save'])) {
        setcookie('save', '', time() - 3600);
        $messages[] = '<div class="success-msg">Спасибо, результаты сохранены.</div>';
        if (!empty($_COOKIE['login']) && !empty($_COOKIE['pass'])) {
            $messages[] = sprintf(
                '<div class="success-msg">Вы можете <a href="login.php">войти</a> с логином <strong>%s</strong> и паролем <strong>%s</strong> для изменения данных.</div>',
                htmlspecialchars($_COOKIE['login'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($_COOKIE['pass'], ENT_QUOTES, 'UTF-8')
            );
            setcookie('login', '', time() - 3600);
            setcookie('pass', '', time() - 3600);
        }
    }

    $errors = array();
    $fields = ['fullName', 'email', 'gender', 'languages', 'bio', 'privacy'];
    foreach ($fields as $f) {
        $errors[$f] = !empty($_COOKIE[$f . '_error']);
    }

    if ($errors['fullName']) {
        setcookie('fullName_error', '', time() - 3600);
        $messages[] = '<div class="error-msg">Имя заполнено неверно или пустое.</div>';
    }
    if ($errors['email']) {
        setcookie('email_error', '', time() - 3600);
        $messages[] = '<div class="error-msg">Email указан некорректно.</div>';
    }
    if ($errors['languages']) {
        setcookie('languages_error', '', time() - 3600);
        $messages[] = '<div class="error-msg">Выберите хотя бы один язык программирования.</div>';
    }
    if ($errors['bio']) {
        setcookie('bio_error', '', time() - 3600);
        $messages[] = '<div class="error-msg">Расскажите что-нибудь о себе в биографии.</div>';
    }

    $values = array();
    $all_fields = ['fullName', 'email', 'number', 'bdate', 'gender', 'bio', 'privacy'];
    foreach ($all_fields as $f) {
        $values[$f] = empty($_COOKIE[$f . '_value']) ? '' : $_COOKIE[$f . '_value'];
    }
    $values['languages'] = empty($_COOKIE['languages_value']) ? [] : explode(',', $_COOKIE['languages_value']);

    if (empty($errors) && !empty($_SESSION['login'])) {
        try {
            $db = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $stmt = $db->prepare("SELECT * FROM application WHERE login = ?");
            $stmt->execute([$_SESSION['login']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $values['fullName'] = $row['name'];
                $values['email']    = $row['email'];
                $values['number']   = $row['number'];
                $values['bdate']    = $row['bday'];
                $values['gender']   = $row['sex'];
                $values['bio']      = $row['bio'];
                
                $stmt_langs = $db->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
                $stmt_langs->execute([$row['id']]);
                $values['languages'] = $stmt_langs->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            print('Ошибка базы данных.');
            exit();
        }
    }
    include('form.php');
} 
else {
    // МЕТОД POST
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Ошибка: неверный CSRF-токен');
    }

    $errors = FALSE;

    if (empty($_POST['fullName'])) {
        setcookie('fullName_error', '1', time() + 24 * 3600);
        $errors = TRUE;
    }
    setcookie('fullName_value', $_POST['fullName'] ?? '', time() + 30 * 24 * 3600);

    if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        setcookie('email_error', '1', time() + 24 * 3600);
        $errors = TRUE;
    }
    setcookie('email_value', $_POST['email'] ?? '', time() + 30 * 24 * 3600);

    if (empty($_POST['bio'])) {
        setcookie('bio_error', '1', time() + 24 * 3600);
        $errors = TRUE;
    }
    setcookie('bio_value', $_POST['bio'] ?? '', time() + 30 * 24 * 3600);

    if (empty($_POST['languages']) || !is_array($_POST['languages'])) {
        setcookie('languages_error', '1', time() + 24 * 3600);
        $errors = TRUE;
    } else {
        setcookie('languages_value', implode(',', $_POST['languages']), time() + 30 * 24 * 3600);
    }

    setcookie('number_value', $_POST['number'] ?? '', time() + 30 * 24 * 3600);
    setcookie('bdate_value', $_POST['bdate'] ?? '', time() + 30 * 24 * 3600);
    setcookie('gender_value', $_POST['gender'] ?? '', time() + 30 * 24 * 3600);
    setcookie('privacy_value', $_POST['privacy'] ?? '', time() + 30 * 24 * 3600);

    if ($errors) {
        header('Location: index.php');
        exit();
    }
    else {
        setcookie('fullName_error', '', time() - 3600);
        setcookie('email_error', '', time() - 3600);
        setcookie('languages_error', '', time() - 3600);
        setcookie('bio_error', '', time() - 3600);

        try {
            $db = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            if (!empty($_SESSION['login'])) {
                $login = $_SESSION['login'];
                $stmt = $db->prepare("SELECT id FROM application WHERE login = ?");
                $stmt->execute([$login]);
                $user_id = $stmt->fetchColumn();

                if ($user_id) {
                    $sql = "UPDATE application SET
                            name = ?,
                            email = ?,
                            number = ?,
                            bday = ?,
                            sex = ?,
                            bio = ?
                            WHERE id = ?";

                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        $_POST['fullName'],
                        $_POST['email'],
                        $_POST['number'],
                        $_POST['bdate'],
                        $_POST['gender'],
                        $_POST['bio'],
                        $user_id
                    ]);

                    $stmt = $db->prepare("DELETE FROM application_languages WHERE application_id = ?");
                    $stmt->execute([$user_id]);

                    $stmt_l = $db->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                    foreach ($_POST['languages'] as $lang_id) {
                        $stmt_l->execute([$user_id, $lang_id]);
                    }
                }
            }
            else {
                // НОВЫЙ ПОЛЬЗОВАТЕЛЬ
                $login = uniqid('user', true);
                $pass_plain = rand(100000, 999999);
                $pass_hash = password_hash($pass_plain, PASSWORD_BCRYPT);

                setcookie('login', $login, time() + 30 * 24 * 3600);
                setcookie('pass', $pass_plain, time() + 30 * 24 * 3600);

                $stmt = $db->prepare("INSERT INTO application (name, email, number, bday, sex, bio, login, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['fullName'], $_POST['email'], $_POST['number'], $_POST['bdate'], $_POST['gender'], $_POST['bio'], $login, $pass_hash]);

                $id = $db->lastInsertId();

                $stmt_l = $db->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                foreach ($_POST['languages'] as $l) {
                    $stmt_l->execute([$id, $l]);
                }
            }

            setcookie('save', '1');
            header('Location: ./');
            exit();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            die("Ошибка обработки запроса.");
        }
    }
}
