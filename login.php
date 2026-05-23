<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$db_host = 'localhost';
$db_name = 'u82184';
$db_user = 'u82184';
$db_pass = '6010664';

$error = '';
$success = '';
$user_data = [];
$user_languages = [];
$languages = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
if (isset($_SESSION['edit_success'])) {
    $success = $_SESSION['edit_success'];
    unset($_SESSION['edit_success']);
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $stmt = $pdo->query("SELECT id, language_name FROM programming_languages ORDER BY language_name");
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('DB Error in login.php: ' . $e->getMessage());
    $error = 'Ошибка базы данных.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password']) && !isset($_POST['update'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['login_error'] = 'Ошибка проверки формы. Обновите страницу.';
        header('Location: login.php');
        exit();
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT id, full_name, username, password_hash FROM applications WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
        } else {
            $_SESSION['login_error'] = 'Неверный логин или пароль.';
        }
        header('Location: login.php');
        exit();
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        $_SESSION['login_error'] = 'Ошибка при входе.';
        header('Location: login.php');
        exit();
    }
}

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_languages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('Load user data error: ' . $e->getMessage());
        $error = 'Ошибка загрузки данных.';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Ошибка проверки формы. Обновите страницу.';
    } else {
        $errors = [];

        $full_name = trim($_POST['full_name'] ?? '');
        if (empty($full_name)) {
            $errors[] = 'ФИО обязательно.';
        } elseif (strlen($full_name) > 150) {
            $errors[] = 'ФИО не более 150 символов.';
        } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $full_name)) {
            $errors[] = 'ФИО: только буквы, пробелы, дефис.';
        }

        $phone = trim($_POST['phone'] ?? '');
        if (empty($phone)) {
            $errors[] = 'Телефон обязателен.';
        } elseif (!preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
            $errors[] = 'Телефон: только цифры, плюс, скобки, пробелы, дефис.';
        } else {
            $digitsOnly = preg_replace('/[^\d]/', '', $phone);
            if (strlen($digitsOnly) < 10 || strlen($digitsOnly) > 15) {
                $errors[] = 'Телефон: от 10 до 15 цифр.';
            }
        }

        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $errors[] = 'Email обязателен.';
        } elseif (!preg_match('/^[a-zA-Z0-9._@\-]+$/', $email)) {
            $errors[] = 'Email: латинские буквы, цифры, точка, дефис, подчёркивание, собака.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email: неверный формат.';
        }

        $birth_date = $_POST['birth_date'] ?? '';
        if (empty($birth_date)) {
            $errors[] = 'Дата рождения обязательна.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
            $errors[] = 'Дата: формат ГГГГ-ММ-ДД.';
        } else {
            $timestamp = strtotime($birth_date);
            $age = date('Y') - date('Y', $timestamp);
            if (date('md') < date('md', $timestamp)) $age--;
            if ($age < 16 || $age > 120) {
                $errors[] = 'Возраст: от 16 до 120 лет.';
            }
        }

        $gender = $_POST['gender'] ?? '';
        if (!in_array($gender, ['male', 'female', 'other'])) {
            $errors[] = 'Выберите пол.';
        }

        $new_languages = $_POST['languages'] ?? [];
        if (empty($new_languages)) {
            $errors[] = 'Выберите язык.';
        }

        $biography = trim($_POST['biography'] ?? '');
        if (!empty($biography) && !preg_match('/^[a-zA-Zа-яА-ЯёЁ0-9\s\.\,\!\?\-\:\;\"\'\(\)\n\r]+$/u', $biography)) {
            $errors[] = 'Биография: буквы, цифры, пробелы, знаки препинания.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE applications SET full_name = ?, phone = ?, email = ?, birth_date = ?, gender = ?, biography = ? WHERE id = ?");
                $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $_SESSION['user_id']]);

                $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
                $stmt->execute([$_SESSION['user_id']]);

                $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                foreach ($new_languages as $lang_id) {
                    $stmt->execute([$_SESSION['user_id'], $lang_id]);
                }

                $pdo->commit();
                $_SESSION['edit_success'] = 'Данные обновлены.';
                header('Location: login.php');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Update error: ' . $e->getMessage());
                $error = 'Ошибка сохранения.';
            }
        } else {
            $error = implode('<br>', $errors);
            $user_data = array_merge($user_data, $_POST);
            $user_languages = $new_languages;
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Личный кабинет</title>
    <style>
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Личный кабинет</h1>
    <p><a href="index.php">На главную</a></p>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <h2>Вход</h2>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <p><label>Логин: <input type="text" name="username" required></label></p>
            <p><label>Пароль: <input type="password" name="password" required></label></p>
            <p><button type="submit">Войти</button></p>
        </form>
    <?php else: ?>
        <p>Вы вошли как: <strong><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></strong> | <a href="?logout=1">Выйти</a></p>

        <?php if ($success): ?>
            <p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <h2>Редактирование анкеты</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <p><label>ФИО: <input type="text" name="full_name" value="<?= htmlspecialchars($user_data['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label></p>
            <p><label>Телефон: <input type="tel" name="phone" value="<?= htmlspecialchars($user_data['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label></p>
            <p><label>E-mail: <input type="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label></p>
            <p><label>Дата рождения: <input type="date" name="birth_date" value="<?= htmlspecialchars($user_data['birth_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label></p>
            <p>
                Пол:
                <label><input type="radio" name="gender" value="male" <?= ($user_data['gender'] ?? '') == 'male' ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= ($user_data['gender'] ?? '') == 'female' ? 'checked' : '' ?>> Женский</label>
                <label><input type="radio" name="gender" value="other" <?= ($user_data['gender'] ?? '') == 'other' ? 'checked' : '' ?>> Другой</label>
            </p>
            <p>
                <label>Языки программирования:<br>
                    <select name="languages[]" multiple size="6" required>
                        <?php foreach ($languages as $lang): ?>
                            <option value="<?= htmlspecialchars($lang['id'], ENT_QUOTES, 'UTF-8') ?>" <?= in_array($lang['id'], $user_languages) ? 'selected' : '' ?>><?= htmlspecialchars($lang['language_name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </p>
            <p><label>Биография:<br><textarea name="biography" rows="4"><?= htmlspecialchars($user_data['biography'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></label></p>
            <p><button type="submit" name="update" value="1">Сохранить изменения</button></p>
        </form>
    <?php endif; ?>
</body>
</html>
