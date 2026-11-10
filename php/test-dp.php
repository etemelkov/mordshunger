<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/config.php';

    if (!function_exists('connectDatabase')) {
        throw new RuntimeException(
            'Die Funktion connectDatabase() fehlt.'
        );
    }

    $pdo = connectDatabase();

    // Prüfen, ob die Datenbank erreichbar ist
    $pdo->query('SELECT 1');

    // Prüfen, ob die Tabelle Raetsel existiert
    $stmt = $pdo->query('SELECT COUNT(*) FROM `Raetsel`');

    echo json_encode([
        'status' => 'success',
        'message' => 'Datenbankverbindung funktioniert!',
        'anzahl_raetsel' => (int) $stmt->fetchColumn()
    ]);

} catch (Throwable $error) {

    error_log(
        '[MORDSHUNGER DB TEST] '
        . get_class($error)
        . ': '
        . $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Test fehlgeschlagen. Bitte PHP-Fehlerlog prüfen.'
    ]);
}