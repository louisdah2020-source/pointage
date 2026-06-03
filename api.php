<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'test':
        echo json_encode(['success' => true, 'message' => 'Connexion API OK', 'db_status' => 'Connectée']);
        break;

    case 'getAgents':
        try {
            $stmt = $pdo->query("SELECT * FROM agents ORDER BY name");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'addAgent':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['name']) || empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Nom de l\'agent manquant.']);
            break;
        }
        try {
            // Génération automatique du matricule s'il n'est pas fourni (fallback pour MySQL)
            $matricule = $data['matricule'] ?? ('MAT-' . date('Ymd') . rand(100, 999));
            $sql = "INSERT INTO agents (name, matricule, salaire_base) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['name'], $matricule, $data['salaire_base'] ?? 0]);
            echo json_encode(['success' => true, 'message' => 'Agent ajouté avec succès.']);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // MySQL integrity constraint violation (e.g., duplicate entry for unique key)
                echo json_encode(['success' => false, 'message' => 'Cet agent existe déjà.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout de l\'agent: ' . $e->getMessage()]);
            }
        }
        break;

    case 'deleteAgent':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['name']) || empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Nom de l\'agent manquant.']);
            break;
        }
        try {
            $sql = "DELETE FROM agents WHERE name = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['name']]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Agent supprimé avec succès.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Agent non trouvé.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression de l\'agent: ' . $e->getMessage()]);
        }
        break;

    case 'getManagers':
        try {
            $stmt = $pdo->query("SELECT name FROM managers ORDER BY name");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'addManager':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['name']) || empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Nom du manager manquant.']);
            break;
        }
        try {
            $sql = "INSERT INTO managers (name) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['name']]);
            echo json_encode(['success' => true, 'message' => 'Manager ajouté avec succès.']);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // MySQL integrity constraint violation (e.g., duplicate entry for unique key)
                echo json_encode(['success' => false, 'message' => 'Ce manager existe déjà.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du manager: ' . $e->getMessage()]);
            }
        }
        break;

    case 'deleteManager':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['name']) || empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Nom du manager manquant.']);
            break;
        }
        try {
            $sql = "DELETE FROM managers WHERE name = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['name']]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Manager supprimé avec succès.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Manager non trouvé.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression du manager: ' . $e->getMessage()]);
        }
        break;

    case 'getPointages':
        try {
            $stmt = $pdo->query("SELECT * FROM pointages ORDER BY iso_date DESC, id DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'savePointage':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['id'])) {
                // Update
                $sql = "UPDATE pointages SET arrivee=?, pause=?, retour=?, depart=?, status=?, total=?, motif=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['arrivee'], $data['pause'], $data['retour'], $data['depart'], $data['status'], $data['total'], $data['motif'], $data['id']]);
            } else {
                // Insert
                $sql = "INSERT INTO pointages (date, iso_date, name, arrivee, pause, retour, depart, status, total, motif) VALUES (?,?,?,?,?,?,?,?,?,?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['date'], $data['iso_date'], $data['name'], $data['arrivee'], $data['pause'], $data['retour'], $data['depart'], $data['status'], $data['total'], $data['motif'] ?? '']);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'getLeaves':
        try {
            $name = $_GET['name'] ?? null;
            if ($name) {
                $stmt = $pdo->prepare("SELECT * FROM demandes_conges WHERE agent_name = ? ORDER BY created_at DESC");
                $stmt->execute([$name]);
            } else {
                $stmt = $pdo->query("SELECT * FROM demandes_conges ORDER BY created_at DESC");
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'submitLeave':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "INSERT INTO demandes_conges (agent_name, type, date_debut, date_fin, motif, statut) VALUES (?,?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['agent_name'], $data['type'], $data['date_debut'], $data['date_fin'], $data['motif'], $data['statut']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'updateLeaveStatus':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE demandes_conges SET statut = ? WHERE id = ?");
            $stmt->execute([$data['statut'], $data['id']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'ackLeave':
        try {
            $id = $_GET['id'];
            $stmt = $pdo->prepare("UPDATE demandes_conges SET acknowledged_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'upsertPerformance':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->beginTransaction();
            foreach ($data as $row) {
                $sql = "INSERT INTO agent_performance_stats (agent_name, date, dons, refus_arg, indecis, del) 
                        VALUES (?,?,?,?,?,?) 
                        ON DUPLICATE KEY UPDATE dons=VALUES(dons), refus_arg=VALUES(refus_arg), indecis=VALUES(indecis), del=VALUES(del)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$row['agent_name'], $row['date'], $row['dons'], $row['refus_arg'], $row['indecis'], $row['del']]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'getAdjustments':
        try {
            $stmt = $pdo->prepare("SELECT * FROM primes_retenues WHERE mois = ? AND annee = ?");
            $stmt->execute([$_GET['mois'], $_GET['annee']]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'saveAdjustment':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "INSERT INTO primes_retenues (agent_name, mois, annee, montant_prime, montant_retenue) 
                    VALUES (?,?,?,?,?) 
                    ON DUPLICATE KEY UPDATE montant_prime=VALUES(montant_prime), montant_retenue=VALUES(montant_retenue)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['agent_name'], $data['mois'], $data['annee'], 
                $data['montant_prime'] ?? 0, $data['montant_retenue'] ?? 0
            ]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'getPerformance':
        try {
            $name = $_GET['name'];
            $date = $_GET['date'];
            $stmt = $pdo->prepare("SELECT * FROM agent_performance_stats WHERE agent_name = ? AND date = ?");
            $stmt->execute([$name, $date]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
        break;
}