<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
header('Content-Type: application/json');

// Récupération des données envoyées par le JavaScript
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$mail = new PHPMailer(true);

try {
    // --- CONFIGURATION SERVEUR SMTP (Exemple avec Gmail) ---
    $mail->isSMTP();
    $mail->Host       = 'mail.mediayab.com';            // Serveur SMTP LWS (ou mailxx.lwspanel.com)
    $mail->SMTPAuth   = true;
    $mail->Username   = 'b.nguessan@mediayab.com';        // Votre email
    $mail->Password   = 'DG-y@b2025';         // Le mot de passe de votre boîte mail LWS
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;      // SSL est souvent plus stable avec LWS
    $mail->Port       = 465;                              // Port standard pour SSL chez LWS
    $mail->setFrom('b.nguessan@mediayab.com', 'Système Pointage');

    // Destinataires
    foreach($data['recipients'] as $email) {    $mail->addAddress(trim($email));
    }

    // --- EMBARQUER LE LOGO ---
    if (file_exists('logo.jpg')) {
        $mail->addEmbeddedImage('logo.jpg', 'logo_entreprise');
    }

    if (isset($data['isSummary']) && $data['isSummary']) {
        // --- MODE RÉCAPITULATIF JOURNALIER ---
        $mail->Subject = "Récapitulatif Journalier des Pointages - " . $data['date'];
        
        $rows = "";
        foreach($data['items'] as $item) {
            $statusColor = ($item['status'] === 'PRESENT') ? '#00b894' : '#d9534f';
            $rows .= '
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px; font-weight: bold;">' . htmlspecialchars($item['name']) . '</td>
                <td style="padding: 10px;">' . htmlspecialchars($item['arrivee']) . '</td>
                <td style="padding: 10px;">' . htmlspecialchars($item['depart']) . '</td>
                <td style="padding: 10px; font-weight: bold;">' . htmlspecialchars($item['total']) . 'h</td>
                <td style="padding: 10px; color: '.$statusColor.'; font-weight: bold;">' . htmlspecialchars($item['status']) . '</td>
            </tr>';
        }

        $body = '
        <div style="font-family: Arial, sans-serif; color: #333; max-width: 800px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden;">
            <div style="background-color: #2e3192; color: #ffffff; padding: 20px; text-align: center;">
                <img src="cid:logo_entreprise" alt="Logo" style="max-width: 120px; margin-bottom: 10px;">
                <h2 style="margin: 0;">Rapport Journalier - ' . $data['date'] . '</h2>
            </div>
            <div style="padding: 20px;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background-color: #f8f9fa;">
                            <th style="padding: 10px; border-bottom: 2px solid #2e3192;">Agent</th>
                            <th style="padding: 10px; border-bottom: 2px solid #2e3192;">Arrivée</th>
                            <th style="padding: 10px; border-bottom: 2px solid #2e3192;">Départ</th>
                            <th style="padding: 10px; border-bottom: 2px solid #2e3192;">Total</th>
                            <th style="padding: 10px; border-bottom: 2px solid #2e3192;">Statut</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        </div>';
    } else {
        // --- MODE ALERTE INDIVIDUELLE (EXISTANT) ---
        $mail->Subject = "Alerte Pointage : " . htmlspecialchars($data['statusType']) . " - " . htmlspecialchars($data['name']);
        $body = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden;">
            <div style="background-color: #2e3192; color: #ffffff; padding: 20px; text-align: center;">
                <img src="cid:logo_entreprise" alt="Logo Entreprise" style="max-width: 150px; margin-bottom: 10px;">
                <h2 style="margin: 0; font-size: 24px;">Alerte Pointage Système</h2>
            </div>
            <div style="padding: 20px;">
                <p>Une nouvelle alerte de pointage a été enregistrée :</p>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr><td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Agent :</td><td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($data['name']) . '</td></tr>
                    <tr><td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Heure :</td><td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($data['time']) . '</td></tr>
                    <tr><td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Statut :</td><td style="padding: 8px; border: 1px solid #ddd; color: #d9534f; font-weight: bold;">' . htmlspecialchars($data['statusType']) . '</td></tr>';
        if (!empty($data['motif'])) {
            $body .= '<tr><td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Motif :</td><td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($data['motif']) . '</td></tr>';
        }
        $body .= '</table></div></div>';
    }

    $mail->Body = $body;
    $mail->isHTML(true);

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Email envoyé avec succès']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Erreur : {$mail->ErrorInfo}"]);
}