<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$db_host = 'localhost';
$db_name = 'u82184';
$db_user = 'u82184';
$db_pass = '6010664';

$languages = [];
$error_message = '';

$cookie_errors = [];
if (isset($_COOKIE['form_errors'])) {
    $cookie_errors = json_decode($_COOKIE['form_errors'], true) ?: [];
    setcookie('form_errors', '', time() - 3600, '/', '', true, true);
}

$cookie_old = [];
if (isset($_COOKIE['form_old_values'])) {
    $cookie_old = json_decode($_COOKIE['form_old_values'], true) ?: [];
    setcookie('form_old_values', '', time() - 3600, '/', '', true, true);
}

$saved_data = [];
if (isset($_COOKIE['form_saved_data'])) {
    $saved_data = json_decode($_COOKIE['form_saved_data'], true) ?: [];
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $tables_exist = $pdo->query("SHOW TABLES LIKE 'programming_languages'")->rowCount() > 0;

    if (!$tables_exist) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS programming_languages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                language_name VARCHAR(50) NOT NULL UNIQUE
            );
            INSERT INTO programming_languages (language_name) VALUES 
            ('Pascal'), ('C'), ('C++'), ('JavaScript'), ('PHP'), 
            ('Python'), ('Java'), ('Haskell'), ('Clojure'), 
            ('Prolog'), ('Scala'), ('Go');
            CREATE TABLE IF NOT EXISTS applications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(150) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                email VARCHAR(100) NOT NULL,
                birth_date DATE NOT NULL,
                gender ENUM('male', 'female', 'other') NOT NULL,
                biography TEXT,
                agreed_to_contract TINYINT(1) DEFAULT 0,
                username VARCHAR(50) UNIQUE,
                password_hash VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS application_languages (
                application_id INT UNSIGNED NOT NULL,
                language_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (application_id, language_id),
                FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
                FOREIGN KEY (language_id) REFERENCES programming_languages(id) ON DELETE CASCADE
            );
        ");
    }

    $stmt = $pdo->query("SELECT id, language_name FROM programming_languages ORDER BY language_name");
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log('DB Error in index.php: ' . $e->getMessage());
    $error_message = "Ошибка подключения к базе данных.";
    $languages = [
        ['id' => 1, 'language_name' => 'Pascal'],
        ['id' => 2, 'language_name' => 'C'],
        ['id' => 3, 'language_name' => 'C++'],
        ['id' => 4, 'language_name' => 'JavaScript'],
        ['id' => 5, 'language_name' => 'PHP'],
        ['id' => 6, 'language_name' => 'Python'],
        ['id' => 7, 'language_name' => 'Java'],
        ['id' => 8, 'language_name' => 'Haskell'],
        ['id' => 9, 'language_name' => 'Clojure'],
        ['id' => 10, 'language_name' => 'Prolog'],
        ['id' => 11, 'language_name' => 'Scala'],
        ['id' => 12, 'language_name' => 'Go'],
    ];
}

$merged_old = $cookie_old;
if (empty($merged_old) && !empty($saved_data)) {
    $merged_old = $saved_data;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета разработчика</title>
    <style>
        .error { color: red; }
        .has-error input, .has-error select, .has-error textarea { border: 2px solid red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Анкета разработчика</h1>
    <p><a href="login.php">Войти для редактирования</a></p>

    <?php if (isset($_SESSION['success_message'])): ?>
        <p class="success"><?= htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (!empty($cookie_errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($cookie_errors as $msg): ?>
                    <li><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <p class="error"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form action="form.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <p class="<?= isset($cookie_errors['full_name']) ? 'has-error' : '' ?>">
            <label>ФИО:<br>
                <input type="text" name="full_name" value="<?= htmlspecialchars($merged_old['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <?php if (isset($cookie_errors['full_name'])): ?>
                <br><span class="error"><?= htmlspecialchars($cookie_errors['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <br><small>Буквы русского и латинского алфавита, пробелы, дефис. Максимум 150 символов.</small>
        </p>

        <p class="<?= isset($cookie_errors['phone']) ? 'has-error' : '' ?>">
            <label>Телефон:<br>
                <input type="tel" name="phone" value="<?= htmlspecialchars($merged_old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <?php if (isset($cookie_errors['phone'])): ?>
                <br><span class="error"><?= htmlspecialchars($cookie_errors['phone'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <br><small>Цифры, плюс, скобки, пробелы, дефис. От 10 до 15 цифр.</small>
        </p>

        <p class="<?= isset($cookie_errors['email']) ? 'has-error' : '' ?>">
            <label>E-mail:<br>
                <input type="email" name="email" value="<?= htmlspecialchars($merged_old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <?php if (isset($cookie_errors['email'])): ?>
                <br><span class="error"><?= htmlspecialchars($cookie_errors['email'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <br><small>Латинские буквы, цифры, точка, дефис, подчёркивание, собака. Максимум 100 символов.</small>
        </p>

        <p class="<?= isset($cookie_errors['birth_date']) ? 'has-error' : '' ?>">
            <label>Дата рождения:<br>
                <input type="date" name="birth_date" value="<?= htmlspecialchars($merged_old['birth_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <?php if (isset($cookie_errors['birth_date'])): ?>
                <br><span class="error"><?= htmlspecialchars($cookie_errors['birth_date'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <br><small>Возраст от 16 до 120 лет.</small>
        </p>

        <p class="<?= isset($cookie_errors['gender']) ? 'has-error' : '' ?>">
            Пол:<br>
            <label><input type="radio" name="gender" value="male" <?= (($merged_old['gender'] ?? '') == 'male') ? 'checked' : '' ?>> Мужской</label>
            <label><input type="radio" name="gender" value="female" <?= (($merged_old['gender'] ?? '') == 'female') ? 'checked' : '' ?>> Женский</label>
            <label><input type="radio" name="gender" value="other" <?= (($merged_old['gender'] ?? '') == 'other') ? 'checked' : '' ?>> Другой</label>
            <?php if (isset($cookie_errors['gender'])): ?>
                <br><span class="error"><?= htmlspecialchars($cookie_errors['gender'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </p>

        <p class="<?= isset($cookie_errors['languages']) ? 'has-error' : '' ?>">
            <label>Любимые языки программирования:<br>
                <select name="languages[]" multiple size="6" required>
                    <?php foreach ($languages as $lang): ?>
                        <?php $selected = (isset($merged_old['languages']) && is_array($merged_old['languages']) && in_array($lang['id'], $merged_old['languages'])); ?>
                        <option value="<?= htmlspecialchars($lang['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $selected ? 'selected' : '' ?>><?= htmlspecialchars($lang['language_name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (isset($cookie_errors['languages'])): ?>
                <br><span class="error"><?= htmlspecialchars($cookie_errors['languages'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </p>

        <p class="<?= isset($cookie_errors['biography']) ? 'has-error' : '' ?>">
            <label>Биография:<br>
                <textarea name="biography" rows="4"><?= htmlspecialchars($merged_old['biography'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
            <?php if (isset($cookie_errors['biography'])): ?>
                <br><span class="error"><?= htmlspecialchars($cookie_errors['biography'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <br><small>Буквы, цифры, пробелы, знаки препинания.</small>
        </p>

        <p class="<?= isset($cookie_errors['agreed_to_contract']) ? 'has-error' : '' ?>">
            <label><input type="checkbox" name="agreed_to_contract" value="1" <?= (($merged_old['agreed_to_contract'] ?? '') == '1') ? 'checked' : '' ?>> Я ознакомлен(а) с условиями контракта</label>
            <?php if (isset($cookie_errors['agreed_to_contract'])): ?>
                <br><span class="error"><?= htmlspecialchars($cookie_errors['agreed_to_contract'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </p>

        <p><button type="submit">Сохранить</button></p>
    </form>
</body>
</html>
