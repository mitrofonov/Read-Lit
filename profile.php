<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/header.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Получение данных пользователя
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT nickname as username, email, `registration date` as created_at FROM user WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Обработка изменения никнейма
$username_error = '';
$username_success = '';
if (isset($_POST['update_username'])) {
    $new_username = trim($_POST['new_username']);
    
    if (empty($new_username)) {
        $username_error = 'Введите новый никнейм';
    } elseif (strlen($new_username) < 3) {
        $username_error = 'Никнейм должен содержать минимум 3 символа';
    } else {
        // Проверка на уникальность никнейма
        $stmt = $pdo->prepare("SELECT id FROM user WHERE nickname = ? AND id != ?");
        $stmt->execute([$new_username, $user_id]);
        if ($stmt->fetch()) {
            $username_error = 'Этот никнейм уже занят';
        } else {
            // Обновление никнейма
            $stmt = $pdo->prepare("UPDATE user SET nickname = ? WHERE id = ?");
            if ($stmt->execute([$new_username, $user_id])) {
                $username_success = 'Никнейм успешно изменен!';
                $user['username'] = $new_username;
            } else {
                $username_error = 'Ошибка при обновлении никнейма';
            }
        }
    }
}

// Обработка изменения email
$email_error = '';
$email_success = '';
if (isset($_POST['update_email'])) {
    $new_email = trim($_POST['new_email']);
    $current_password = $_POST['email_password'];
    
    // Проверка текущего пароля
    $stmt = $pdo->prepare("SELECT password FROM user WHERE id = ?");
    $stmt->execute([$user_id]);
    $db_password = $stmt->fetchColumn();
    
    if (!password_verify($current_password, $db_password)) {
        $email_error = 'Неверный пароль';
    } elseif (empty($new_email)) {
        $email_error = 'Введите новый email';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $email_error = 'Некорректный формат email';
    } else {
        // Проверка на уникальность email
        $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ? AND id != ?");
        $stmt->execute([$new_email, $user_id]);
        if ($stmt->fetch()) {
            $email_error = 'Этот email уже занят';
        } else {
            // Обновление email
            $stmt = $pdo->prepare("UPDATE user SET email = ? WHERE id = ?");
            if ($stmt->execute([$new_email, $user_id])) {
                $email_success = 'Email успешно изменен!';
                $user['email'] = $new_email;
            } else {
                $email_error = 'Ошибка при обновлении email';
            }
        }
    }
}

// Обработка изменения пароля
$password_error = '';
$password_success = '';
if (isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Проверка текущего пароля
    $stmt = $pdo->prepare("SELECT password FROM user WHERE id = ?");
    $stmt->execute([$user_id]);
    $db_password = $stmt->fetchColumn();
    
    if (!password_verify($current_password, $db_password)) {
        $password_error = 'Текущий пароль введен неверно';
    } elseif (empty($new_password)) {
        $password_error = 'Введите новый пароль';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'Пароль должен содержать минимум 6 символов';
    } elseif ($new_password !== $confirm_password) {
        $password_error = 'Пароли не совпадают';
    } else {
        // Обновление пароля
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed_password, $user_id])) {
            $password_success = 'Пароль успешно изменен!';
        } else {
            $password_error = 'Ошибка при изменении пароля';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Nunito+Sans&display=swap" rel="stylesheet">
    <link href="./css/main.css" rel="stylesheet">
    <link href="./css/profile.css" rel="stylesheet">
    <title>Личный кабинет - Read-Lit</title>
</head>
<body>
<div class="fix-size">
    <div class="container">
        <div class="profile-container">

            <div class="profile-section">
                <div class="profile-header">
                    <div class="profile-info">
                        <h2><?= htmlspecialchars($user['username']) ?></h2>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                        <p>На сайте с: <?= date('d.m.Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
            </div>

            <div class="profile-section">
                <h2>Изменение никнейма</h2>
                <form method="POST" class="profile-form">
                    <div class="form-group">
                        <label for="new_username">Новый никнейм</label>
                        <input type="text" id="new_username" name="new_username" 
                               value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    <?php if ($username_error): ?>
                        <div class="error-message"><?= $username_error ?></div>
                    <?php endif; ?>
                    <?php if ($username_success): ?>
                        <div class="success-message"><?= $username_success ?></div>
                    <?php endif; ?>
                    <button type="submit" name="update_username" class="save-button">Сохранить</button>
                </form>
            </div>

            <div class="profile-section">
                <h2>Изменение email</h2>
                <form method="POST" class="profile-form">
                    <div class="form-group">
                        <label for="new_email">Новый email</label>
                        <input type="email" id="new_email" name="new_email" 
                               value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email_password">Текущий пароль</label>
                        <input type="password" id="email_password" name="email_password" required>
                    </div>
                    <?php if ($email_error): ?>
                        <div class="error-message"><?= $email_error ?></div>
                    <?php endif; ?>
                    <?php if ($email_success): ?>
                        <div class="success-message"><?= $email_success ?></div>
                    <?php endif; ?>
                    <button type="submit" name="update_email" class="save-button">Изменить email</button>
                </form>
            </div>

            <div class="profile-section">
                <h2>Изменение пароля</h2>
                <form method="POST" class="profile-form">
                    <div class="form-group">
                        <label for="current_password">Текущий пароль</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Новый пароль</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Подтвердите новый пароль</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <?php if ($password_error): ?>
                        <div class="error-message"><?= $password_error ?></div>
                    <?php endif; ?>
                    <?php if ($password_success): ?>
                        <div class="success-message"><?= $password_success ?></div>
                    <?php endif; ?>
                    <button type="submit" name="update_password" class="save-button">Изменить пароль</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
</body>
</html> 