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
    $mail->Host       = 'smtp.gmail.com';               // Serveur SMTP (ex: mail.votre-domaine.com)
    $mail->SMTPAuth   = true;
    $mail->Username   = 'votre-email@gmail.com';        // Votre email
    $mail->Password   = 'votre-mot-de-passe-application'; // Votre mot de passe (ou mot de passe d'application)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->setFrom('votre-email@gmail.com', 'Système Pointage');

    // Destinataires
    foreach($recipients as $email) {    $mail->addAddress(trim($email));
    }

    // --- EMBARQUER LE LOGO ---
    $mail->addEmbeddedImage('logo.jpg', 'logo_entreprise');

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
                <tr><td style="ing:: 1px solid #ddd; font-weight: bold;">Statut :</td><td style="padding: 8px; border: 1px solid #ddd; color: #d9534f; font-weight: bold;">' . htmlspecialchars($data['statusType']) . '</td></tr>';

    if (!empty($data['motif'])) {
        $body .= '<t