<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/header.php';

// Проверка прав администратора
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT status_id FROM user WHERE id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch();

if (!$user || $user['status_id'] != 1) {
    die("У вас нет прав для доступа к этой странице.");
}

// Получение ID книги из URL
$book_id = $_GET['book_id'] ?? null;
if (!$book_id) {
    die("ID книги не указан.");
}

// Получение информации о книге
$sql = "SELECT b.title, b.author, c.URL AS cover_url FROM book b JOIN cover c ON b.cover_id = c.id WHERE b.id = :book_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['book_id' => $book_id]);
$book = $stmt->fetch();

if (!$book) {
    die("Книга не найдена.");
}

// Обработка формы добавления главы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_chapter') {
    $chapter_number = $_POST['chapter_number'] ?? '';
    $chapter_title = $_POST['chapter_title'] ?? '';
    $chapter_content = $_POST['chapter_content'] ?? '';
    $book_id = $_POST['book_id'] ?? 0;

    // Валидация данных
    $errors = [];
    if (!is_numeric($chapter_number) || $chapter_number <= 0) {
        $errors[] = "Номер главы должен быть положительным числом.";
    }
    if (empty($chapter_title)) {
        $errors[] = "Название главы не может быть пустым.";
    }
    if (empty($chapter_content)) {
        $errors[] = "Содержание главы не может быть пустым.";
    }

    if (empty($errors)) {
        try {
            // Проверяем, существует ли уже глава с таким номером
            $sql = "SELECT id FROM chapter WHERE book_id = :book_id AND chapter = :chapter_number";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['book_id' => $book_id, 'chapter_number' => $chapter_number]);
            $existing_chapter = $stmt->fetch();

            if ($existing_chapter) {
                $errors[] = "Глава с таким номером уже существует.";
            } else {
                // Генерируем уникальное имя файла в формате .md
                $filename = "chapters/book_{$book_id}_chapter_{$chapter_number}_" . uniqid() . ".md";
                
                // Создаем папку chapters, если ее нет
                if (!is_dir('chapters')) {
                    mkdir('chapters', 0755, true);
                }

                // Преобразуем HTML в Markdown (упрощённая версия)
                $markdown_content = htmlToMarkdown($chapter_content);

                // Сохраняем главу в MD файл
                if (file_put_contents($filename, $markdown_content) === false) {
                    throw new Exception("Не удалось сохранить файл главы.");
                }

                // Сохраняем главу в базу данных
                $sql = "INSERT INTO chapter (book_id, chapter, title, chapter_URL) VALUES (:book_id, :chapter, :title, :chapter_url)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'book_id' => $book_id,
                    'chapter' => $chapter_number,
                    'title' => $chapter_title,
                    'chapter_url' => $filename
                ]);

                $success = "Глава успешно добавлена!";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка базы данных: " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

/**
 * Простая функция преобразования HTML в Markdown
 */
function htmlToMarkdown($html) {
    // Заменяем основные HTML-теги на Markdown-эквиваленты
    $replacements = [
        '/<b>(.*?)<\/b>/' => '**$1**',
        '/<strong>(.*?)<\/strong>/' => '**$1**',
        '/<i>(.*?)<\/i>/' => '*$1*',
        '/<em>(.*?)<\/em>/' => '*$1*',
        '/<u>(.*?)<\/u>/' => '<u>$1</u>', // Сохраняем HTML-тег подчёркивания
        '/<h3>(.*?)<\/h3>/' => "\n### $1\n",
        '/<blockquote>(.*?)<\/blockquote>/' => "\n> $1\n",
        '/<br\s?\/?>/' => "\n",
        '/<p>(.*?)<\/p>/' => "\n$1\n"
    ];
    
    $markdown = preg_replace(array_keys($replacements), array_values($replacements), $html);
    
    // Удаляем оставшиеся HTML-теги, кроме <u>
    $markdown = strip_tags($markdown, '<u>');
    
    return trim($markdown);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить главу - <?= htmlspecialchars($book['title']) ?></title>
    <link href="https://fonts.googleapis.com/css?family=Nunito+Sans&display=swap" rel="stylesheet">
    <link href="./css/main.css" rel="stylesheet">
    <link href="./css/header.css" rel="stylesheet">
    <link href="./css/add_chapter.css" rel="stylesheet">
</head>
<body>
    <div class="add-chapter-container">
        <div class="add-chapter-header">
            <a href="book.php?id=<?= $book_id ?>" class="add-chapter-cover-link">
                <div class="add-chapter-cover" style="background-image: url('<?= htmlspecialchars($book['cover_url']) ?>');"></div>
            </a>
            <div class="add-chapter-book-info">
                <h1 class="add-chapter-book-title"><?= htmlspecialchars($book['title']) ?></h1>
                <p class="add-chapter-book-author"><?= htmlspecialchars($book['author']) ?></p>
            </div>
        </div>

        <h2 class="add-chapter-title">Добавить новую главу</h2>

        <?php if (!empty($errors)): ?>
            <div class="add-chapter-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="add-chapter-success">
                <p><?= htmlspecialchars($success) ?></p>
            </div>
        <?php endif; ?>

        <div class="add-chapter-form">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_chapter">
                <input type="hidden" name="book_id" value="<?= htmlspecialchars($book_id) ?>">

                <div class="add-chapter-form-group">
                    <label for="chapter_number" class="add-chapter-label">Номер главы:</label>
                    <input type="number" id="chapter_number" name="chapter_number" min="1" class="add-chapter-input" required>
                </div>

                <div class="add-chapter-form-group">
                    <label for="chapter_title" class="add-chapter-label">Название главы:</label>
                    <input type="text" id="chapter_title" name="chapter_title" class="add-chapter-input" required>
                </div>

                <div class="add-chapter-form-group">
                    <label for="chapter_content" class="add-chapter-label">Содержание главы (поддерживается Markdown):</label>
                    <div class="add-chapter-toolbar">
                        <button type="button" class="add-chapter-toolbar-btn" onclick="formatText('bold')"><b>B</b></button>
                        <button type="button" class="add-chapter-toolbar-btn" onclick="formatText('italic')"><i>I</i></button>
                        <button type="button" class="add-chapter-toolbar-btn" onclick="formatText('underline')"><u>U</u></button>
                        <button type="button" class="add-chapter-toolbar-btn" onclick="insertText('### ', '')">Заголовок</button>
                        <button type="button" class="add-chapter-toolbar-btn" onclick="insertText('> ', '')">Цитата</button>
                    </div>
                    <textarea id="chapter_content" name="chapter_content" class="add-chapter-textarea" required></textarea>
                </div>

                <button type="submit" class="add-chapter-btn">Добавить главу</button>
            </form>
        </div>
    </div>

    <script>
        // Функции для форматирования текста
        function formatText(format) {
            const textarea = document.getElementById('chapter_content');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            let before = '', after = '';

            switch(format) {
                case 'bold':
                    before = '**';
                    after = '**';
                    break;
                case 'italic':
                    before = '*';
                    after = '*';
                    break;
                case 'underline':
                    before = '<u>';
                    after = '</u>';
                    break;
            }

            const newText = textarea.value.substring(0, start) + before + selectedText + after + 
                           textarea.value.substring(end);
            textarea.value = newText;
            
            // Устанавливаем позицию курсора
            const newCursorPos = start + before.length;
            textarea.setSelectionRange(newCursorPos, newCursorPos);
            textarea.focus();
        }

        function insertText(before, after = '') {
            const textarea = document.getElementById('chapter_content');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            const newText = textarea.value.substring(0, start) + before + selectedText + after + 
                           textarea.value.substring(end);
            textarea.value = newText;
            
            const newPos = start + before.length;
            textarea.selectionStart = textarea.selectionEnd = newPos;
            textarea.focus();
        }

        // Проверяем предпочтения пользователя по теме
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }

        // Слушаем изменения темы
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
        });
    </script>

    <?php
    require_once 'includes/footer.php';
    ?>
</body>
</html>