<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// admin/import-questions.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $file = $_FILES['csv']['tmp_name'];
    $handle = fopen($file, 'r');
    if ($handle === false) {
        die('❌ Failed to open uploaded file.');
    }

    // Skip header row
    fgetcsv($handle);

    $success = 0;
    $fail = 0;

    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
        [$questionText, $a, $b, $c, $d, $correctOpt, $categoryId, $level, $difficulty] = $data;

        // Insert question into questions table
        $stmt = $pdo->prepare("
            INSERT INTO questions (question_text, category_id, level, difficulty, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $result = $stmt->execute([$questionText, $categoryId, $level, $difficulty]);

        if (!$result) {
            $fail++;
            continue;
        }

        $questionId = $pdo->lastInsertId();

        // Prepare options
        $options = ['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d];

        foreach ($options as $key => $text) {
            $isCorrect = ($key === strtoupper($correctOpt)) ? 1 : 0;

            $stmt = $pdo->prepare("
                INSERT INTO question_options (question_id, option_text, is_correct, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$questionId, $text, $isCorrect]);
        }

        $success++;
    }

    fclose($handle);
    echo "<h3>✅ Import Complete</h3>";
    echo "<p>✔️ Success: $success<br>❌ Failed: $fail</p>";
} else {
?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Import Questions</title>
        <style>
            body { font-family: Arial; margin: 30px; }
            label { font-weight: bold; }
        </style>
    </head>
    <body>
        <h2>📤 Upload CSV to Import Questions</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>CSV File:</label>
            <input type="file" name="csv" accept=".csv" required>
            <br><br>
            <button type="submit">Upload & Import</button>
        </form>
    </body>
    </html>
<?php
}
?>
