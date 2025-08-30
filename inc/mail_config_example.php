<?php
// Configurazione SMTP - MODIFICA CON I TUOI DATI
define('SMTP_HOST', 'smtp.gmail.com');        // Server SMTP (esempi sotto)
define('SMTP_PORT', 587);                      // Porta (587 per TLS, 465 per SSL)
define('SMTP_SECURE', 'tls');                  // 'tls' o 'ssl'
define('SMTP_AUTH', true);                     // Autenticazione richiesta
define('SMTP_USERNAME', 'tuoemail@gmail.com'); // Username SMTP
define('SMTP_PASSWORD', 'tua_password');        // Password SMTP
define('SMTP_FROM_EMAIL', 'tuoemail@gmail.com'); // Email mittente
define('SMTP_FROM_NAME', 'Porto di Melide');   // Nome mittente

// Esempi configurazioni comuni:

// GMAIL:
// Host: smtp.gmail.com
// Port: 587 (TLS) o 465 (SSL)
// Nota: Devi abilitare "App meno sicure" o usare una "Password per app"

// OUTLOOK/HOTMAIL:
// Host: smtp-mail.outlook.com
// Port: 587
// Secure: tls

// YAHOO:
// Host: smtp.mail.yahoo.com
// Port: 587 o 465
// Secure: tls o ssl

// HOSTING PROFESSIONALE (es. Infomaniak):
// Host: mail.infomaniak.com
// Port: 587
// Secure: tls

// ARUBA:
// Host: smtps.aruba.it
// Port: 465
// Secure: ssl