<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$db_host = 'localhost';
$db_name = 'u82184';
$db_user = 'u82184';
$db_pass = '6010664';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: index.php');
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Ошибка проверки формы. Пожалуйста, обновите страницу и попробуйте снова.');
}

$errors = [];

$full_name = trim($_POST['full_name'] ?? '');
if (empty($full_name)) {
    $errors['full_name'] = 'ФИО обязательно.';
} elseif (strlen($full_name) > 150) {
    $errors['full_name'] = 'ФИО не более 150 символов.';
} elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $full_name)) {
    $errors['full_name'] = 'Только буквы, пробелы, дефис.';
}

$phone = trim($_POST['phone'] ?? '');
if (empty($phone)) {
    $errors['phone'] = 'Телефон обязателен.';
} elseif (!preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
    $errors['phone'] = 'Только цифры, плюс, скобки, пробелы, дефис.';
} else {
    $digitsOnly = preg_replace('/[^\d]/', '', $phone);
    if (strlen($digitsOnly) < 10 || strlen($digitsOnly) > 15) {
        $errors['phone'] = 'От 10 до 15 цифр.';
    }
}

$email = trim($_POST['email'] ?? '');
if (empty($email)) {
    $errors['email'] = 'Email обязателен.';
} elseif (strlen($email) > 100) {
    $errors['email'] = 'Email не более 100 символов.';
} elseif (!preg_match('/^[a-zA-Z0-9._@\-]+$/', $email)) {
    $errors['email'] = 'Латинские буквы, цифры, точка, дефис, подчёркивание, собака.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Неверный формат email.';
}

$birth_date = $_POST['birth_date'] ?? '';
if (empty($birth_date)) {
    $errors['birth_date'] = 'Дата рождения обязательна.';
} elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
    $errors['birth_date'] = 'Формат ГГГГ-ММ-ДД.';
} else {
    $timestamp = strtotime($birth_date);
    if (!$timestamp) {
        $errors['birth_date'] = 'Некорректная дата.';
    } else {
        $age = date('Y') - date('Y', $timestamp);
        if (date('md') < date('md', $timestamp)) $age--;
        if ($age < 16 || $age > 120) {
            $errors['birth_date'] = 'Возраст от 16 до 120 лет.';
        }
    }
}

$gender = $_POST['gender'] ?? '';
if (!in_array($gender, ['male', 'female', 'other'])) {
    $errors['gender'] = 'Выберите пол.';
}

$languages = $_POST['languages'] ?? [];
if (empty($languages)) {
    $errors['languages'] = 'Выберите язык.';
} elseif (!is_array($languages)) {
    $errors['languages'] = 'Некорректные данные.';
} else {
    foreach ($languages as $lang) {
        if (!preg_match('/^\d+$/', (string)$lang)) {
            $errors['languages'] = 'Некорректный язык.';
            break;
        }
    }
}

$biography = trim($_POST['biography'] ?? '');
if (!empty($biography) && !preg_match('/^[a-zA-Zа-яА-ЯёЁ0-9\s\.\,\!\?\-\:\;\"\'\(\)\n\r]+$/u', $biography)) {
    $errors['biography'] = 'Буквы, цифры, пробелы, знаки препинания.';
}

$agreed = $_POST['agreed_to_contract'] ?? '';
if ($agreed != '1') {
    $errors['agreed_to_contract'] = 'Примите условия контракта.';
}

if (!empty($errors)) {
    setcookie('form_errors', json_encode($errors), 0, '/', '', true, true);
    setcookie('form_old_values', json_encode($_POST), 0, '/', '', true, true);
    header('Location: index.php');
    exit();
}

function generateUsername($full_name) {
    $translit = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
        'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K',
        'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R',
        'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya'
    ];
    $transliterated = strtr($full_name, $translit);
    $transliterated = preg_replace('/[^a-zA-Z0-9]/', '_', $transliterated);
    $transliterated = preg_replace('/_+/', '_', $transliterated);
    $transliterated = trim($transliterated, '_');
    $transliterated = strtolower($transliterated);
    $random = substr(md5(uniqid()), 0, 4);
    return substr($transliterated, 0, 45) . '_' . $random;
}

function generatePassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $username = generateUsername($full_name);
    $password = generatePassword();
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, agreed_to_contract, username, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, 1, $username, $password_hash]);
    $application_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
    foreach ($languages as $lang_id) {
        $stmt->execute([$application_id, $lang_id]);
    }

    $pdo->commit();

    $saved_data = [
        'full_name' => $full_name,
        'phone' => $phone,
        'email' => $email,
        'birth_date' => $birth_date,
        'gender' => $gender,
        'languages' => $languages,
        'biography' => $biography,
        'agreed_to_contract' => '1'
    ];
    setcookie('form_saved_data', json_encode($saved_data), time() + 365 * 24 * 3600, '/', '', true, true);
    setcookie('form_errors', '', time() - 3600, '/', '', true, true);
    setcookie('form_old_values', '', time() - 3600, '/', '', true, true);

    $_SESSION['success_message'] = "Данные сохранены. Логин: {$username}, Пароль: {$password}";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('DB Error in form.php: ' . $e->getMessage());
    setcookie('form_errors', json_encode(['database' => 'Ошибка сохранения. Попробуйте позже.']), 0, '/', '', true, true);
    setcookie('form_old_values', json_encode($_POST), 0, '/', '', true, true);
}

header('Location: index.php');
exit();
