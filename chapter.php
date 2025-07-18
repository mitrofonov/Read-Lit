<?php
ob_start();
session_start();

require_once 'includes/config.php';
require_once 'includes/header.php';

// Подключаем библиотеку Parsedown для преобразования Markdown в HTML
require_once 'includes/Parsedown.php';
$parsedown = new Parsedown();

// Получение ID книги и номера главы из URL
$book_id = $_GET['id'] ?? null;
$chapter_number = $_GET['chapter'] ?? null;

if (!$book_id || !$chapter_number) {
    die("Book ID or Chapter number is missing.");
}

// Получение информации о книге
$sqlBook = "
    SELECT 
        b.id, 
        b.title, 
        b.author, 
        b.time, 
        g.genre AS main_genre, 
        c.URL AS cover_url,
        sb.`book status` AS book_status,
        (SELECT COUNT(*) FROM chapter ch WHERE ch.book_id = b.id) AS chapter_count
    FROM book b
    JOIN genre g ON b.`main genre` = g.id
    JOIN cover c ON b.cover_id = c.id
    JOIN status_book sb ON b.status = sb.id
    WHERE b.id = :book_id
";
$stmtBook = $pdo->prepare($sqlBook);
$stmtBook->execute([':book_id' => $book_id]);
$book = $stmtBook->fetch();

if (!$book) {
    die("Book not found.");
}

// Получение информации о главе
$sqlChapter = "
    SELECT 
        chapter, 
        title,
        chapter_URL 
    FROM chapter 
    WHERE book_id = :book_id AND chapter = :chapter
";
$stmtChapter = $pdo->prepare($sqlChapter);
$stmtChapter->execute([':book_id' => $book_id, ':chapter' => $chapter_number]);
$chapter = $stmtChapter->fetch();

if (!$chapter) {
    die("Chapter not found.");
}

// Получение содержимого главы из MD файла и преобразование в HTML
$chapter_content = file_get_contents($chapter['chapter_URL']);
if ($chapter_content === false) {
    die("Ошибка загрузки содержимого главы.");
}

// Преобразуем Markdown в HTML
$chapter_html = $parsedown->text($chapter_content);

// Обновляем последнюю прочитанную главу в библиотеке
if (isset($_SESSION['user_id']) && isset($_GET['id']) && isset($_GET['chapter'])) {
    $book_id = $_GET['id'];
    $chapter_num = $_GET['chapter'];
    
    // Проверяем, есть ли книга уже в библиотеке
    $sql = "SELECT * FROM user_library WHERE user_id = :user_id AND book_id = :book_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user_id'], 'book_id' => $book_id]);
    
    if ($stmt->fetch()) {
        // Обновляем последнюю главу
        $sql = "UPDATE user_library SET last_chapter = :chapter, last_visited = NOW() 
                WHERE user_id = :user_id AND book_id = :book_id";
    } else {
        // Добавляем новую запись
        $sql = "INSERT INTO user_library (user_id, book_id, last_chapter, last_visited) 
                VALUES (:user_id, :book_id, :chapter, NOW())";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'book_id' => $book_id,
        'chapter' => $chapter_num
    ]);
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Nunito+Sans&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Lora&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="./css/main.css" rel="stylesheet">
    <link href="./css/chapter.css" rel="stylesheet">
    <title><?= htmlspecialchars($book['title']) ?> - Chapter <?= $chapter_number ?></title>
</head>

<body>

<div class="fix-size">
    <div class="container">
        <!-- Панель настроек -->
        <div class="settings-panel" id="settingsPanel">
            <h3><i class="fas fa-cog"></i> Настройки чтения</h3>
            
            <!-- Настройка шрифта -->
            <div class="settings-group">
                <span class="config">Шрифт</span>
                <div class="settings-buttons">
                    <button class="settings-btn font-btn" data-font="'Nunito Sans', sans-serif" onclick="setFont(this, 'Nunito Sans')">Nunito</button>
                    <button class="settings-btn font-btn" data-font="'Roboto', sans-serif" onclick="setFont(this, 'Roboto')">Roboto</button>
                    <button class="settings-btn font-btn" data-font="'Lora', serif" onclick="setFont(this, 'Lora')">Lora</button>
                </div>
            </div>

            <!-- Настройка размера шрифта -->
            <div class="settings-group">
                <span class="config">Размер шрифта</span>
                <div class="settings-buttons">
                    <button class="settings-btn" onclick="changeFontSize(-1)"><i class="fas fa-minus"></i></button>
                    <span class="settings-value" id="font-size-display">16px</span>
                    <button class="settings-btn" onclick="changeFontSize(1)"><i class="fas fa-plus"></i></button>
                </div>
            </div>

            <!-- Настройка высоты строки -->
            <div class="settings-group">
                <span class="config">Высота строки</span>
                <div class="settings-buttons">
                    <button class="settings-btn" onclick="changeLineHeight(-0.1)"><i class="fas fa-minus"></i></button>
                    <span class="settings-value" id="line-height-display">1.6</span>
                    <button class="settings-btn" onclick="changeLineHeight(0.1)"><i class="fas fa-plus"></i></button>
                </div>
            </div>

            <!-- Темная тема -->
            <div class="settings-group">
                <span class="config">Тема</span>
                <div class="settings-buttons">
                    <button class="settings-btn theme-btn" onclick="toggleDarkMode(false)">Светлая</button>
                    <button class="settings-btn theme-btn" onclick="toggleDarkMode(true)">Темная</button>
                </div>
            </div>

            <!-- Кнопка закрытия -->
            <button class="close-settings" onclick="toggleSettings()">
                <i class="fas fa-times"></i> Закрыть
            </button>
        </div>

        <div class="content">
            <!-- Верхняя панель с заголовком и кнопками -->
            <div class="chapter-header">
                <div class="chapter-controls">
                    <button class="settings-button" onclick="toggleSettings()">
                        <i class="fas fa-cog"></i> Настройки
                    </button>
                </div>
                
                <div class="chapter-title">
                    <h1><?= htmlspecialchars($book['title']) ?></h1>
                    <h2>Chapter <?= $chapter_number ?>: <?= htmlspecialchars($chapter['title'] ?? '') ?></h2>
                </div>
                
                <div class="chapter-controls">
                    <a href="book.php?id=<?= $book_id ?>" class="content-button">
                        <i class="fas fa-list-ul"></i> К содержанию
                    </a>
                </div>
            </div>

            <!-- Навигация между главами -->
            <div class="chapter-navigation">
                <?php if ($chapter_number > 1): ?>
                    <a href="chapter.php?id=<?= $book_id ?>&chapter=<?= $chapter_number - 1 ?>" class="prev-chapter">
                        <i class="fas fa-arrow-left"></i> Предыдущая глава
                    </a>
                <?php endif; ?>
                <?php if ($chapter_number < $book['chapter_count']): ?>
                    <a href="chapter.php?id=<?= $book_id ?>&chapter=<?= $chapter_number + 1 ?>" class="next-chapter">
                        Следующая глава <i class="fas fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Содержимое главы -->
            <div class="chapter-content">
                <div class="chapter-text">
                    <?= $chapter_html ?>
                </div>
            </div>

            <!-- Навигация между главами -->
            <div class="chapter-navigation">
                <?php if ($chapter_number > 1): ?>
                    <a href="chapter.php?id=<?= $book_id ?>&chapter=<?= $chapter_number - 1 ?>" class="prev-chapter">
                        <i class="fas fa-arrow-left"></i> Предыдущая глава
                    </a>
                <?php endif; ?>
                <?php if ($chapter_number < $book['chapter_count']): ?>
                    <a href="chapter.php?id=<?= $book_id ?>&chapter=<?= $chapter_number + 1 ?>" class="next-chapter">
                        Следующая глава <i class="fas fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Функция для переключения видимости панели настроек
    function toggleSettings() {
        const panel = document.getElementById('settingsPanel');
        panel.classList.toggle('active');
    }

    // Загрузка сохраненных настроек
    function loadSettings() {
        // Шрифт
        const savedFont = localStorage.getItem('readerFont');
        if (savedFont) {
            document.querySelector('.chapter-text').style.fontFamily = savedFont;
            // Активируем соответствующую кнопку
            document.querySelectorAll('.font-btn').forEach(btn => {
                if (btn.getAttribute('data-font') === savedFont) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }

        // Размер шрифта
        const savedFontSize = localStorage.getItem('readerFontSize');
        if (savedFontSize) {
            document.querySelector('.chapter-text').style.fontSize = savedFontSize + 'px';
            document.getElementById('font-size-display').textContent = savedFontSize + 'px';
            currentFontSize = parseInt(savedFontSize);
        }

        // Высота строки
        const savedLineHeight = localStorage.getItem('readerLineHeight');
        if (savedLineHeight) {
            document.querySelector('.chapter-text').style.lineHeight = savedLineHeight;
            document.getElementById('line-height-display').textContent = savedLineHeight;
            currentLineHeight = parseFloat(savedLineHeight);
        }

        // Темная тема
        const darkMode = localStorage.getItem('readerDarkMode') === 'true';
        toggleDarkMode(darkMode);
    }

    // Установка шрифта
    function setFont(button, font) {
        const content = document.querySelector('.chapter-text');
        const fontFamily = button.getAttribute('data-font');
        content.style.fontFamily = fontFamily;

        // Удаляем класс active у всех кнопок шрифта
        document.querySelectorAll('.font-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Добавляем класс active к выбранной кнопке
        button.classList.add('active');

        // Сохраняем в localStorage
        localStorage.setItem('readerFont', fontFamily);
    }

    // Изменение размера шрифта
    let currentFontSize = 16;
    const fontSizeDisplay = document.getElementById('font-size-display');

    function changeFontSize(step) {
        currentFontSize += step;
        // Ограничиваем минимальный и максимальный размер
        currentFontSize = Math.max(12, Math.min(24, currentFontSize));
        
        document.querySelector('.chapter-text').style.fontSize = `${currentFontSize}px`;
        fontSizeDisplay.textContent = `${currentFontSize}px`;
        
        // Сохраняем в localStorage
        localStorage.setItem('readerFontSize', currentFontSize);
    }

    // Изменение высоты строки
    let currentLineHeight = 1.6;
    const lineHeightDisplay = document.getElementById('line-height-display');

    function changeLineHeight(step) {
        currentLineHeight = parseFloat((currentLineHeight + step).toFixed(1));
        // Ограничиваем минимальную и максимальную высоту
        currentLineHeight = Math.max(1.0, Math.min(2.5, currentLineHeight));
        
        document.querySelector('.chapter-text').style.lineHeight = currentLineHeight;
        lineHeightDisplay.textContent = currentLineHeight;
        
        // Сохраняем в localStorage
        localStorage.setItem('readerLineHeight', currentLineHeight);
    }

    // Переключение темной темы
    function toggleDarkMode(enable) {
        if (enable) {
            document.body.classList.add('dark-mode');
            document.querySelectorAll('.theme-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent === 'Темная') {
                    btn.classList.add('active');
                }
            });
        } else {
            document.body.classList.remove('dark-mode');
            document.querySelectorAll('.theme-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent === 'Светлая') {
                    btn.classList.add('active');
                }
            });
        }
        localStorage.setItem('readerDarkMode', enable);
    }

    // При загрузке страницы применяем сохраненные настройки
    window.addEventListener('DOMContentLoaded', loadSettings);
</script>
</body>
</html>