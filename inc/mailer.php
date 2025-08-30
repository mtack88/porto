<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';

/**
 * Crea e configura un'istanza di PHPMailer
 */
function create_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    
    // Configurazione server
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = SMTP_AUTH;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;
    
    // Configurazione mittente
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    
    // Configurazione generale
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    
    // Debug (disattiva in produzione)
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    
    return $mail;
}

/**
 * Invia email di verifica annuale
 */
function send_verification_email(array $recipient): bool {
    try {
        $mail = create_mailer();
        
        // Destinatario
        $mail->addAddress($recipient['email'], $recipient['cognome'] . ' ' . $recipient['nome']);
        
        // Oggetto
        $mail->Subject = 'Verifica annuale lista d\'attesa - Porto di Melide';
        
        // Genera link di conferma con token
        $token = base64_encode($recipient['id'] . '|' . date('Y') . '|' . $recipient['email']);
        $confirm_link = 'https://porto.maurotacchella.ch/app/waiting/confirm.php?token=' . $token;
        
        // Corpo HTML
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f8f9fa; padding: 20px; margin-top: 20px; }
                .button { display: inline-block; padding: 12px 30px; background-color: #28a745; 
                         color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 20px; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Porto di Melide</h1>
                    <h2>Verifica annuale lista d\'attesa</h2>
                </div>
                
                <div class="content">
                    <p>Gentile <strong>' . htmlspecialchars($recipient['cognome'] . ' ' . $recipient['nome']) . '</strong>,</p>
                    
                    <p>È trascorso un anno dalla sua ultima conferma di interesse per un posto barca presso il Porto di Melide.</p>
                    
                    <p>Per mantenere la sua posizione nella lista d\'attesa, la preghiamo di confermare il suo interesse 
                    cliccando sul pulsante sottostante:</p>
                    
                    <center>
                        <a href="' . $confirm_link . '" class="button">
                            CONFERMA IL MIO INTERESSE
                        </a>
                    </center>
                    
                    <div class="info-box">
                        <strong>I suoi dati attuali:</strong><br>
                        Tipologia: ' . $recipient['tipologia'] . '<br>
                        Luogo di residenza: ' . htmlspecialchars($recipient['luogo']) . '<br>
                        Data iscrizione: ' . format_date_from_ymd($recipient['data_iscrizione']) . '<br>
                    </div>
                    
                    <p><strong>Importante:</strong> Se non conferma entro 30 giorni, la sua iscrizione verrà disattivata 
                    e perderà la posizione in lista.</p>
                    
                    <p>Se i suoi dati sono cambiati o desidera cancellarsi dalla lista, può rispondere a questa email 
                    o contattarci telefonicamente.</p>
                    
                    <p>Cordiali saluti,<br>
                    Cancelleria comunale</p>
                </div>
                
                <div class="footer">
                    <p>Questa email è stata inviata a ' . htmlspecialchars($recipient['email']) . '<br>
                    Comune di Melide - Via S. Franscini 6, 6815 Melide<br>
                    Tel: +41 91 640 10 70</p>
                    
                    <p>Se non riesce a cliccare sul pulsante, copi e incolli questo link nel browser:<br>
                    <small>' . $confirm_link . '</small></p>
                </div>
            </div>
        </body>
        </html>';
        
        // Corpo testo alternativo
        $mail->AltBody = "
        Gentile {$recipient['cognome']} {$recipient['nome']},
        
        È trascorso un anno dalla sua ultima conferma per la lista d'attesa del Porto di Melide.
        
        Per confermare il suo interesse, visiti questo link:
        $confirm_link
        
        Se non conferma entro 30 giorni, la sua iscrizione verrà disattivata.
        
        Cordiali saluti,
        Cancelleria comunale
        ";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Errore invio email a {$recipient['email']}: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Invia email di benvenuto per nuova iscrizione
 */
function send_welcome_email(array $recipient): bool {
    try {
        $mail = create_mailer();
        
        $mail->addAddress($recipient['email'], $recipient['cognome'] . ' ' . $recipient['nome']);
        $mail->Subject = 'Conferma iscrizione lista d\'attesa - Porto di Melide';
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f8f9fa; padding: 20px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Iscrizione confermata!</h1>
                </div>
                
                <div class="content">
                    <p>Gentile <strong>' . htmlspecialchars($recipient['cognome'] . ' ' . $recipient['nome']) . '</strong>,</p>
                    
                    <p>La sua iscrizione alla lista d\'attesa per un posto ' . strtolower($recipient['tipologia']) . ' 
                    presso il Porto di Melide è stata registrata con successo.</p>
                    
                    <p><strong>Riepilogo iscrizione:</strong></p>
                    <ul>
                        <li>Tipologia: ' . $recipient['tipologia'] . '</li>
                        <li>Data iscrizione: ' . format_date_from_ymd($recipient['data_iscrizione']) . '</li>
                        <li>Luogo residenza: ' . htmlspecialchars($recipient['luogo']) . '</li>
                    </ul>
                    
                    <p>La contatteremo non appena si libererà un posto adatto alle sue esigenze.</p>
                    
                    <p>Cordiali saluti,<br>
                    Cancelleria comunale</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Errore invio email benvenuto: {$mail->ErrorInfo}");
        return false;
    }
}