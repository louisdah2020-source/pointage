<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'getAgents':
        $stmt = $pdo->query("SELECT name FROM agents ORDER BY name");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        break;

    case 'getManagers':
        $stmt = $pdo->query("SELECT name FROM managers ORDER BY name");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        break;

    case 'getPointages':
        $stmt = $pdo->query("SELECT * FROM pointages ORDER BY iso_date DESC, id DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'savePointage':
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id'])) {
            // Update
            $sql = "UPDATE pointages SET arrivee=?, pause=?, retour=?, depart=?, status=?, total=?, motif=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['arrivee'], $data['pause'], $data['retour'], $data['depart'], $data['status'], $data['total'], $data['motif'], $data['id']]);
        } else {
            // Insert
            $sql = "INSERT INTO pointages (date, iso_date, name, arrivee, pause, retour, depart, status, total) VALUES (?,?,?,?,?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['date'], $data['iso_date'], $data['name'], $data['arrivee'], $data['pause'], $data['retour'], $data['depart'], $data['status'], $data['total']]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'getLeaves':
        $name = $_GET['name'] ?? null;
        if ($name) {
            $stmt = $pdo->prepare("SELECT * FROM demandes_conges WHERE agent_name = ? ORDER BY created_at DESC");
            $stmt->execute([$name]);
        } else {
            $stmt = $pdo->query("SELECT * FROM demandes_conges ORDER BY created_at DESC");
        }
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'submitLeave':
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = "INSERT INTO demandes_conges (agent_name, type, date_debut, date_fin, motif, statut) VALUES (?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$data['agent_name'], $data['type'], $data['date_debut'], $data['date_fin'], $data['motif'], $data['statut']]);
        echo json_encode(['success' => true]);
        break;

    case 'updateLeaveStatus':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("UPDATE demandes_conges SET statut = ? WHERE id = ?");
        $stmt->execute([$data['statut'], $data['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'ackLeave':
        $id = $_GET['id'];
        $stmt = $pdo->prepare("UPDATE demandes_conges SET acknowledged_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'upsertPerformance':
        $data = json_decode(file_get_contents('php://input'), true);
        foreach ($data as $row) {
            $sql = "INSERT INTO agent_performance_stats (agent_name, date, dons, refus_arg, indecis, del) 
                    VALUES (?,?,?,?,?,?) 
                    ON DUPLICATE KEY UPDATE dons=VALUES(dons), refus_arg=VALUES(refus_arg), indecis=VALUES(indecis), del=VALUES(del)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$row['agent_name'], $row['date'], $row['dons'], $row['refus_arg'], $row['indecis'], $row['del']]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'getPerformance':
        $name = $_GET['name'];
        $date = $_GET['date'];
        $stmt = $pdo->prepare("SELECT * FROM agent_performance_stats WHERE agent_name = ? AND date = ?");
        $stmt->execute([$name, $date]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
        break;
}