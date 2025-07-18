<?php
session_start();


require_once 'includes/config.php';

// Папка для сохранения обложек
$uploadDir = 'covers/';

// Создаем директорию, если ее нет
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0775, true)) {
        error_log("Не удалось создать директорию " . $uploadDir);
        die("Ошибка: Не удалось создать директорию для загрузки обложек.");
    }
}

// Проверка, была ли отправлена форма
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получение данных из формы
    $title = $_POST['title'];
    $author = $_POST['author'];
    $mainGenre = $_POST['main_genre'];
    $secondGenre = $_POST['second_genre'];
    $tag1 = $_POST['tag1'];
    $tag2 = $_POST['tag2'];
    $tag3 = $_POST['tag3'];
    $annotation = $_POST['annotation']; // Получаем описание книги

    // Проверка обязательных полей
    if (empty($title) || empty($author) || empty($mainGenre) || empty($annotation)) {
        die("Ошибка: Не все обязательные поля заполнены.");
    }

    // Обработка загрузки обложки
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover'];

        // Проверка типа файла и расширения
        $allowedTypes = ['image/png'];
        $fileType = mime_content_type($file['tmp_name']);
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileType, $allowedTypes) || $fileExt !== 'png') {
            die("Ошибка: Разрешены только файлы PNG.");
        }

        // Генерация уникального имени файла
        $uniqueFileName = uniqid('cover_', true) . '.png';
        $filePath = $uploadDir . $uniqueFileName;

        // Перемещение файла в папку covers
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            try {
                // Сохранение пути обложки в базу данных
                $stmt = $pdo->prepare("INSERT INTO cover (URL) VALUES (:url)");
                $stmt->execute([':url' => $filePath]);

                // Получение ID последней вставленной обложки
                $coverId = $pdo->lastInsertId();

                // Вставка данных о книге в таблицу `book`
                $stmt = $pdo->prepare("INSERT INTO book (title, author, status, `main genre`, `second genre`, `tag 1`, `tag 2`, `tag 3`, cover_id) 
                                      VALUES (:title, :author, 1, :main_genre, :second_genre, :tag1, :tag2, :tag3, :cover_id)");
                $stmt->execute([
                    ':title' => $title,
                    ':author' => $author,
                    ':main_genre' => $mainGenre,
                    ':second_genre' => $secondGenre,
                    ':tag1' => $tag1,
                    ':tag2' => $tag2,
                    ':tag3' => $tag3,
                    ':cover_id' => $coverId
                ]);

                // Получение ID последней вставленной книги
                $bookId = $pdo->lastInsertId();

                // Вставка описания книги в таблицу `annotation`
                $stmt = $pdo->prepare("INSERT INTO annotation (annotation, book_id) VALUES (:annotation, :book_id)");
                $stmt->execute([
                    ':annotation' => $annotation,
                    ':book_id' => $bookId
                ]);

                echo "Книга успешно добавлена!";
            } catch (PDOException $e) {
                error_log("Ошибка при сохранении в базу данных: " . $e->getMessage());
                unlink($filePath); // Удаляем загруженный файл в случае ошибки
                die("Ошибка при сохранении в базу данных: " . $e->getMessage());
            }
        } else {
            die("Ошибка при перемещении файла.");
        }
    } else {
        die("Ошибка при загрузке файла.");
    }
} else {
    die("Форма не была отправлена.");
}
?>