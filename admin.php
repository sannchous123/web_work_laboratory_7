<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$db_host = 'localhost';
$db_name = 'u82184';
$db_user = 'u82184';
$db_pass = '6010664';

$admin_login = 'admin';
$admin_password = 'admin123';

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== $admin_login || 
    $_SERVER['PHP_AUTH_PW'] !== $admin_password) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    die('Доступ запрещён.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$allowed_actions = ['list', 'edit', 'stats'];
$action = in_array(($_GET['action'] ?? 'list'), $allowed_actions) ? $_GET['action'] : 'list';
$edit_id = $_GET['id'] ?? null;

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die('Ошибка проверки формы.');
        }

        $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $stmt->execute([$_POST['delete_id']]);
        $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        $message = 'Запись удалена.';
        header('Location: admin.php?message=' . urlencode($message));
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_id'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die('Ошибка проверки формы.');
        }

        $id = $_POST['edit_id'];
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
        }

        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $errors[] = 'Email обязателен.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email: неверный формат.';
        }

        $birth_date = $_POST['birth_date'] ?? '';
        if (empty($birth_date)) {
            $errors[] = 'Дата рождения обязательна.';
        }

        $gender = $_POST['gender'] ?? '';
        if (!in_array($gender, ['male', 'female', 'other'])) {
            $errors[] = 'Выберите пол.';
        }

        $languages = $_POST['languages'] ?? [];
        if (empty($languages)) {
            $errors[] = 'Выберите язык.';
        }

        $biography = trim($_POST['biography'] ?? '');

        if (empty($errors)) {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE applications SET full_name = ?, phone = ?, email = ?, birth_date = ?, gender = ?, biography = ? WHERE id = ?");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $id]);

            $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($languages as $lang_id) {
                $stmt->execute([$id, $lang_id]);
            }

            $pdo->commit();
            $message = 'Данные обновлены.';
            header('Location: admin.php?message=' . urlencode($message));
            exit();
        } else {
            $message = implode('<br>', $errors);
            $action = 'edit';
            $edit_id = $id;
        }
    }

    if ($action == 'edit' && $edit_id) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
        $stmt->execute([$edit_id]);
        $edit_languages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $stmt = $pdo->query("SELECT id, language_name FROM programming_languages ORDER BY language_name");
    $all_languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT a.id, a.full_name, a.phone, a.email, a.birth_date, a.gender, a.biography, a.created_at, a.updated_at,
               GROUP_CONCAT(pl.language_name SEPARATOR ', ') as languages
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        LEFT JOIN programming_languages pl ON al.language_id = pl.id
        GROUP BY a.id
        ORDER BY a.created_at DESC
    ");
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT pl.language_name, COUNT(al.application_id) as count
        FROM programming_languages pl
        LEFT JOIN application_languages al ON pl.id = al.language_id
        GROUP BY pl.id, pl.language_name
        ORDER BY count DESC
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM applications");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

} catch (PDOException $e) {
    error_log('DB Error in admin.php: ' . $e->getMessage());
    die('Ошибка базы данных.');
}

if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message'], ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 13px; }
        th { background: #f0f0f0; }
        .message { padding: 10px; margin: 10px 0; }
        .error { background: #ffe0e0; color: red; }
        .success { background: #e0ffe0; color: green; }
        .menu { margin: 20px 0; }
        .menu a { margin-right: 15px; }
        textarea, input, select { width: 300px; }
    </style>
</head>
<body>
    <h1>Админ-панель</h1>
    
    <div class="menu">
        <a href="admin.php?action=list">Все записи</a>
        <a href="admin.php?action=stats">Статистика</a>
        <a href="index.php">На главную</a>
    </div>

    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'обязательно') !== false || strpos($message, 'только') !== false || strpos($message, 'не более') !== false ? 'error' : 'success' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($action == 'edit' && isset($edit_data)): ?>
        <h2>Редактирование записи #<?= htmlspecialchars($edit_data['id'], ENT_QUOTES, 'UTF-8') ?></h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="edit_id" value="<?= htmlspecialchars($edit_data['id'], ENT_QUOTES, 'UTF-8') ?>">
            <p><label>ФИО: <input type="text" name="full_name" value="<?= htmlspecialchars($edit_data['full_name'], ENT_QUOTES, 'UTF-8') ?>" required></label></p>
            <p><label>Телефон: <input type="tel" name="phone" value="<?= htmlspecialchars($edit_data['phone'], ENT_QUOTES, 'UTF-8') ?>" required></label></p>
            <p><label>E-mail: <input type="email" name="email" value="<?= htmlspecialchars($edit_data['email'], ENT_QUOTES, 'UTF-8') ?>" required></label></p>
            <p><label>Дата рождения: <input type="date" name="birth_date" value="<?= htmlspecialchars($edit_data['birth_date'], ENT_QUOTES, 'UTF-8') ?>" required></label></p>
            <p>
                Пол:
                <label><input type="radio" name="gender" value="male" <?= $edit_data['gender'] == 'male' ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= $edit_data['gender'] == 'female' ? 'checked' : '' ?>> Женский</label>
                <label><input type="radio" name="gender" value="other" <?= $edit_data['gender'] == 'other' ? 'checked' : '' ?>> Другой</label>
            </p>
            <p>
                <label>Языки:<br>
                    <select name="languages[]" multiple size="6" required>
                        <?php foreach ($all_languages as $lang): ?>
                            <option value="<?= htmlspecialchars($lang['id'], ENT_QUOTES, 'UTF-8') ?>" <?= in_array($lang['id'], $edit_languages) ? 'selected' : '' ?>><?= htmlspecialchars($lang['language_name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </p>
            <p><label>Биография:<br><textarea name="biography" rows="4"><?= htmlspecialchars($edit_data['biography'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></label></p>
            <p><button type="submit">Сохранить</button> <a href="admin.php">Отмена</a></p>
        </form>
    <?php elseif ($action == 'stats'): ?>
        <h2>Статистика по языкам программирования</h2>
        <p>Всего пользователей: <strong><?= htmlspecialchars($total, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <table>
            <tr><th>Язык</th><th>Количество пользователей</th></tr>
            <?php foreach ($stats as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['language_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['count'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <h2>Все записи (<?= count($applications) ?>)</h2>
        <?php if (empty($applications)): ?>
            <p>Нет данных.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Телефон</th>
                    <th>E-mail</th>
                    <th>Дата рождения</th>
                    <th>Пол</th>
                    <th>Языки</th>
                    <th>Биография</th>
                    <th>Создан</th>
                    <th>Обновлён</th>
                    <th>Действия</th>
                </tr>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?= htmlspecialchars($app['id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($app['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($app['phone'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($app['birth_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php
                            if ($app['gender'] == 'male') echo 'Мужской';
                            elseif ($app['gender'] == 'female') echo 'Женский';
                            else echo 'Другой';
                            ?>
                        </td>
                        <td><?= htmlspecialchars($app['languages'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(substr($app['biography'] ?? '', 0, 50), ENT_QUOTES, 'UTF-8') ?><?= strlen($app['biography'] ?? '') > 50 ? '...' : '' ?></td>
                        <td><?= htmlspecialchars($app['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($app['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="admin.php?action=edit&id=<?= urlencode($app['id']) ?>">Ред.</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить запись?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="delete_id" value="<?= htmlspecialchars($app['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit">Удал.</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
