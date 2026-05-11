<?php
header('Content-Type: application/json');
$host = 'localhost';
$dbname = 'flappy_bird';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    die(json_encode(['error' => 'DB Connection Failed']));
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $body = json_decode(file_get_contents('php://input'), true);
    $name = trim($body['username'] ?? 'Guest');
    $score = (int)($body['score'] ?? 0);

    try {
        $pdo->beginTransaction();

    
        $stmt = $pdo->prepare('SELECT id, high_score FROM players WHERE username = ?');
        $stmt->execute([$name]);
        $player = $stmt->fetch();

        if (!$player) {
            $stmt = $pdo->prepare('INSERT INTO players (username, high_score) VALUES (?, ?)');
            $stmt->execute([$name, $score]);
            $playerId = $pdo->lastInsertId();
        } else {
            $playerId = $player['id'];
          
            if ($score > $player['high_score']) {
                $pdo->prepare('UPDATE players SET high_score = ? WHERE id = ?')
                    ->execute([$score, $playerId]);
            }
        }

  
        $stmt = $pdo->prepare('INSERT INTO game_history (player_id, score) VALUES (?, ?)');
        $stmt->execute([$playerId, $score]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
} 

if ($action === 'leaderboard') {

    $stmt = $pdo->query('SELECT username, high_score AS best_score FROM players ORDER BY high_score DESC LIMIT 10');
    echo json_encode(['leaderboard' => $stmt->fetchAll()]);
}