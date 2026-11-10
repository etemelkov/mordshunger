<?php

declare(strict_types=1);

// Technische Fehler nicht öffentlich anzeigen.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// Nur GET-Anfragen zulassen.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');

    echo json_encode([
        'error' => 'Diese Anfrage wird nicht unterstützt.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    // Öffentlichen Verbindungscode laden.
    // Dieser liest die Zugangsdaten aus dem privaten Ordner.
    require_once __DIR__ . '/config.php';

    $pdo = connectDatabase();

    // Nur aktive Rätsel aus deiner Tabelle laden.
    $sql = '
        SELECT
            `id`,
            `title`,
            `text`,
            `question`,
            `answers`,
            `correct`,
            `hint`,
            `explanation`
        FROM `Raetsel`
        WHERE `active` = :active
        ORDER BY `id` ASC
    ';

    $statement = $pdo->prepare($sql);
    $statement->bindValue(':active', 1, PDO::PARAM_INT);
    $statement->execute();

    $riddles = [];
    $seenIds = [];

    while ($row = $statement->fetch()) {
        // Zahlen aus der Datenbank prüfen und umwandeln.
        $id = filter_var(
            $row['id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $correct = filter_var(
            $row['correct'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]]
        );

        // Antworten sind in der Datenbank als JSON gespeichert.
        $answers = json_decode(
            (string) $row['answers'],
            true,
            32,
            JSON_THROW_ON_ERROR
        );

        if (
            $id === false
            || isset($seenIds[$id])
            || !is_array($answers)
            || !array_is_list($answers)
            || count($answers) < 2
            || $correct === false
            || $correct >= count($answers)
        ) {
            throw new UnexpectedValueException(
                'Ungültige Rätseldaten.'
            );
        }

        // Jede Antwort muss ein nicht leerer Text sein.
        foreach ($answers as $answer) {
            if (!is_string($answer) || trim($answer) === '') {
                throw new UnexpectedValueException(
                    'Ungültige Antwort.'
                );
            }
        }

        // Pflichttexte prüfen.
        foreach (['title', 'text', 'question'] as $field) {
            if (
                !is_string($row[$field])
                || trim($row[$field]) === ''
            ) {
                throw new UnexpectedValueException(
                    'Ein Rätseltext fehlt.'
                );
            }
        }

        $seenIds[$id] = true;

        $riddles[] = [
            'id' => $id,
            'title' => $row['title'],
            'text' => $row['text'],
            'question' => $row['question'],
            'answers' => $answers,
            'correct' => $correct,
            'hint' => (string) ($row['hint'] ?? ''),
            'explanation' => (string) ($row['explanation'] ?? ''),
        ];
    }

    // Rätselliste an das Frontend zurückgeben.
    echo json_encode(
        $riddles,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

} catch (Throwable $error) {
    // Nur bekannte, unvertrauliche Meldungen ins Log schreiben.
    // Keine beliebigen PDO-Meldungen oder Zugangsdaten ausgeben.
    $safeMessages = [
        'Die private Datenbankkonfiguration fehlt.',
        'Die Datenbankkonfiguration ist ungültig.',
        'Die Datenbankkonfiguration ist unvollständig.',
        'Der Datenbankport ist ungültig.',
        'Ungültige Rätseldaten.',
        'Ungültige Antwort.',
        'Ein Rätseltext fehlt.',
    ];

    $detail = in_array(
        $error->getMessage(),
        $safeMessages,
        true
    )
        ? $error->getMessage()
        : 'Keine weiteren sicheren Details verfügbar.';

    error_log(
        '[MORDSHUNGER Raetsel] '
        . get_class($error)
        . ' | '
        . $detail
    );

    // Im Browser nur eine allgemeine Fehlermeldung anzeigen.
    http_response_code(503);

    echo json_encode([
        'error' => 'Die Rätsel konnten gerade nicht geladen werden. Bitte versuche es später erneut.',
    ], JSON_UNESCAPED_UNICODE);
}