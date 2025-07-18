<?php
session_start();
require_once 'config.php'; // Файл с подключением к БД

// Обработка входа
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['status_id'] = $user['status_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['nickname'] = $user['nickname'];
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        $login_error = "Неверный email или пароль";
    }
}

// Обработка регистрации
if (isset($_POST['register'])) {
    $email = trim($_POST['email']);
    $nickname = trim($_POST['nickname']);
    $password = trim($_POST['password']);
    
    // Проверка на существующего пользователя
    $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ? OR nickname = ?");
    $stmt->execute([$email, $nickname]);
    
    if ($stmt->fetch()) {
        $register_error = "Пользователь с таким email или никнеймом уже существует";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO user (nickname, email, password, status_id) VALUES (?, ?, ?, 0)");
        if ($stmt->execute([$nickname, $email, $hashed_password])) {
            $user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['status_id'] = 0;
            $_SESSION['email'] = $email;
            $_SESSION['nickname'] = $nickname;
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $register_error = "Ошибка при регистрации";
        }
    }
}

// Обработка выхода
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Nunito+Sans:400,600,700&display=swap" rel="stylesheet">
    <link href="./css/header.css" rel="stylesheet">
        <link href="./css/main.css" rel="stylesheet">


    <title>Read-Lit</title>
    <style>
        /* Ваши существующие стили остаются без изменений */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 350px;
            position: relative;
            animation: modalopen 0.3s;
        }

        @keyframes modalopen {
            from {opacity: 0; transform: translateY(-50px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 22px;
            color: #777;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover {
            color: #333;
        }

        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
            min-height: 20px;
        }

        .modal input {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .modal button {
            width: 100%;
            padding: 12px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .modal button:hover {
            background-color: #2980b9;
        }

        .links {
            margin-top: 15px;
            text-align: center;
            font-size: 14px;
            color: #777;
        }

        .links a {
            color: #3498db;
            text-decoration: none;
        }

        @media (max-width: 480px) {
            .modal-content {
                width: 90%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-container">
        <div class="logo">
            <a href="index.php" class="logo-text">Read-Lit</a>
        </div>
        
        <div class="mobile-menu-toggle">
            <span></span>
            <span></span>
            <span></span>
        </div>
        
        <nav class="desktop-navigation">
            <div class="nav-item">
                <a href="books.php">Книги</a>
            </div>
            <div class="nav-item">
                <a href="rating.php">Рейтинг</a>
            </div>
            <div class="nav-item">
                <a href="library.php">Моя библиотека</a>
            </div>
            <?php if (isset($_SESSION['user_id']) && $_SESSION['status_id'] == 1): ?>
                <div class="nav-item">
                    <a href="book1.php">Добавить книгу</a>
                </div>
            <?php endif; ?>
            <div class="nav-auth">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php" class="auth-link">Профиль</a>
                    <a href="?logout=1" class="auth-link">Выход</a>
                <?php else: ?>
                    <a href="#" id="login-link" class="auth-link">Вход</a>
                    <a href="#" id="register-link" class="auth-link">Регистрация</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
    
    <div class="mobile-menu">
        <nav class="mobile-navigation">
            <div class="nav-item">
                <a href="books.php">Книги</a>
            </div>
            <div class="nav-item">
                <a href="rating.php">Рейтинг</a>
            </div>

            <?php if (isset($_SESSION['user_id']) && $_SESSION['status_id'] == 1): ?>
                <div class="nav-item">
                    <a href="book1.php">Добавить книгу</a>
                </div>
            <?php endif; ?>
            <div class="nav-auth">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="nav-item">
        <a href="library.php">Моя библиотека</a>
    </div>
                    <a href="profile.php" class="auth-link">Профиль</a>
                    <a href="?logout=1" class="auth-link">Выход</a>
                <?php else: ?>
                    <a href="#" id="mobile-login-link" class="auth-link">Вход</a>
                    <a href="#" id="mobile-register-link" class="auth-link">Регистрация</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<!-- Модальное окно входа -->
<div id="login-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Авторизация</h2>
        <div id="login-error" class="error"><?php echo isset($login_error) ? $login_error : ''; ?></div>
        <form id="login-form" method="POST" action="">
            <input type="hidden" name="login" value="1">
            <input type="email" id="email" name="email" placeholder="Email" required>
            <input type="password" id="password" name="password" placeholder="Пароль" required>
            <button type="submit">Войти</button>
            <div class="links">
                <p>Еще не зарегистрированы? <a href="#" id="show-register">Регистрация</a></p>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно регистрации -->
<div id="register-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Регистрация</h2>
        <div id="register-error" class="error"><?php echo isset($register_error) ? $register_error : ''; ?></div>
        <form id="register-form" method="POST" action="">
            <input type="hidden" name="register" value="1">
            <input type="email" id="reg-email" name="email" placeholder="Email" required>
            <input type="text" id="reg-nickname" name="nickname" placeholder="Никнейм" required>
            <input type="password" id="reg-password" name="password" placeholder="Пароль" required>
            <button type="submit">Зарегистрироваться</button>
            <div class="links">
                <p>Уже зарегистрированы? <a href="#" id="show-login">Авторизация</a></p>
            </div>
        </form>
    </div>
</div>

<script>
    // Мобильное меню
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    
    mobileMenuToggle.addEventListener('click', function() {
        this.classList.toggle('active');
        mobileMenu.classList.toggle('active');
    });
    
    // Закрытие меню при клике на ссылку
    document.querySelectorAll('.mobile-menu a').forEach(link => {
        if (!link.classList.contains('auth-link')) {
            link.addEventListener('click', () => {
                mobileMenuToggle.classList.remove('active');
                mobileMenu.classList.remove('active');
            });
        }
    });
    
    // Модальные окна
    const loginModal = document.getElementById('login-modal');
    const registerModal = document.getElementById('register-modal');
    const loginLink = document.getElementById('login-link');
    const registerLink = document.getElementById('register-link');
    const mobileLoginLink = document.getElementById('mobile-login-link');
    const mobileRegisterLink = document.getElementById('mobile-register-link');
    const showLogin = document.getElementById('show-login');
    const showRegister = document.getElementById('show-register');
    const closeButtons = document.getElementsByClassName('close');
    
    // Показать модальное окно, если есть ошибки
    <?php if (isset($login_error)): ?>
        loginModal.style.display = 'flex';
    <?php elseif (isset($register_error)): ?>
        registerModal.style.display = 'flex';
    <?php endif; ?>
    
    // Открытие модальных окон
    if (loginLink) loginLink.addEventListener('click', function(e) {
        e.preventDefault();
        loginModal.style.display = 'flex';
        registerModal.style.display = 'none';
    });
    
    if (registerLink) registerLink.addEventListener('click', function(e) {
        e.preventDefault();
        registerModal.style.display = 'flex';
        loginModal.style.display = 'none';
    });
    
    if (mobileLoginLink) mobileLoginLink.addEventListener('click', function(e) {
        e.preventDefault();
        loginModal.style.display = 'flex';
        registerModal.style.display = 'none';
        mobileMenuToggle.classList.remove('active');
        mobileMenu.classList.remove('active');
    });
    
    if (mobileRegisterLink) mobileRegisterLink.addEventListener('click', function(e) {
        e.preventDefault();
        registerModal.style.display = 'flex';
        loginModal.style.display = 'none';
        mobileMenuToggle.classList.remove('active');
        mobileMenu.classList.remove('active');
    });
    
    // Переключение между окнами
    showRegister.addEventListener('click', function(e) {
        e.preventDefault();
        loginModal.style.display = 'none';
        registerModal.style.display = 'flex';
    });
    
    showLogin.addEventListener('click', function(e) {
        e.preventDefault();
        registerModal.style.display = 'none';
        loginModal.style.display = 'flex';
    });
    
    // Закрытие модальных окон
    for (let i = 0; i < closeButtons.length; i++) {
        closeButtons[i].addEventListener('click', function() {
            loginModal.style.display = 'none';
            registerModal.style.display = 'none';
        });
    }
    
    // Закрытие при клике вне модального окна
    window.addEventListener('click', function(event) {
        if (event.target === loginModal) {
            loginModal.style.display = 'none';
        }
        if (event.target === registerModal) {
            registerModal.style.display = 'none';
        }
    });
</script>