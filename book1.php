<?php
session_start();

require_once 'includes/config.php';
require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Nunito+Sans&display=swap" rel="stylesheet">
    <link href="./css/main.css" rel="stylesheet">
    <link href="./css/book_form.css" rel="stylesheet">
    <title>Добавить книгу - Read-Lit</title>
</head>
<body>
    <div class="layout-body container">
        <div class="book-form-container">
            <h1>Добавить книгу</h1>
            <form class="book-form" action="add_book.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Название книги</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="author">Автор книги</label>
                    <input type="text" id="author" name="author" required>
                </div>

                <div class="form-group">
                    <label for="main_genre">Основной жанр</label>
                    <select id="main_genre" name="main_genre" required>
                        <?php
                        $stmt = $pdo->query("SELECT id, genre FROM genre");
                        while ($row = $stmt->fetch()) {
                            echo "<option value='{$row['id']}'>{$row['genre']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="second_genre">Второстепенный жанр</label>
                    <select id="second_genre" name="second_genre">
                        <option value="0">Отсутствует</option>
                        <?php
                        $stmt = $pdo->query("SELECT id, genre FROM genre");
                        while ($row = $stmt->fetch()) {
                            echo "<option value='{$row['id']}'>{$row['genre']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tag1">Тег 1</label>
                    <select id="tag1" name="tag1">
                        <option value="0">Нет</option>
                        <?php
                        $stmt = $pdo->query("SELECT id, teg FROM Teg");
                        while ($row = $stmt->fetch()) {
                            echo "<option value='{$row['id']}'>{$row['teg']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tag2">Тег 2</label>
                    <select id="tag2" name="tag2">
                        <option value="0">Нет</option>
                        <?php
                        $stmt = $pdo->query("SELECT id, teg FROM Teg");
                        while ($row = $stmt->fetch()) {
                            echo "<option value='{$row['id']}'>{$row['teg']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tag3">Тег 3</label>
                    <select id="tag3" name="tag3">
                        <option value="0">Нет</option>
                        <?php
                        $stmt = $pdo->query("SELECT id, teg FROM Teg");
                        while ($row = $stmt->fetch()) {
                            echo "<option value='{$row['id']}'>{$row['teg']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group full-width file-upload">
                    <label class="file-upload-label" for="cover">Выберите обложку (PNG)</label>
                    <input type="file" id="cover" name="cover" accept="image/png" required style="display: none;">
                    <span class="file-name" id="file-name">Файл не выбран</span>
                </div>

                <div class="form-group full-width">
                    <label for="annotation">Описание книги</label>
                    <textarea id="annotation" name="annotation" required></textarea>
                </div>

                <button type="submit" class="submit-btn">Добавить книгу</button>
            </form>
        </div>
    </div>

    <script>
        // Скрипт для отображения имени выбранного файла
        document.getElementById('cover').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Файл не выбран';
            document.getElementById('file-name').textContent = fileName;
        });
    </script>

    <?php
    require_once 'includes/footer.php';
    ?>
</body>
</html>