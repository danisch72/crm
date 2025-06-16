<?php
/**
 * Helper Functions - Funzioni di utilità generale
 * CRM Re.De Consulting
 * 
 * File: /crm/core/functions/helpers.php
 */

// Previeni accesso diretto
if (!defined('CRM_INIT') && !defined('AUTH_INIT')) {
    die('Accesso diretto non consentito');
}

/**
 * ====================================
 * FUNZIONI DI FORMATTAZIONE
 * ====================================
 */

/**
 * Formatta una data in formato italiano
 * @param string $date Data in formato MySQL
 * @param string $format Formato output (default: d/m/Y)
 * @return string Data formattata
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date == '0000-00-00') {
        return '';
    }
    return date($format, strtotime($date));
}

/**
 * Formatta data e ora in formato italiano
 * @param string $datetime DateTime in formato MySQL
 * @param string $format Formato output
 * @return string DateTime formattato
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return '';
    }
    return date($format, strtotime($datetime));
}

/**
 * Formatta numero in valuta Euro
 * @param float $number Numero da formattare
 * @param int $decimals Decimali (default: 2)
 * @return string Numero formattato
 */
function formatCurrency($number, $decimals = 2) {
    return '€ ' . number_format($number, $decimals, ',', '.');
}

/**
 * Formatta numero con separatori italiani
 * @param float $number Numero da formattare
 * @param int $decimals Decimali
 * @return string Numero formattato
 */
function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals, ',', '.');
}

/**
 * ====================================
 * FUNZIONI DI VALIDAZIONE
 * ====================================
 */

/**
 * Valida Codice Fiscale italiano
 * @param string $cf Codice fiscale
 * @return bool
 */
function isValidCodiceFiscale($cf) {
    $cf = strtoupper(trim($cf));
    
    // Verifica lunghezza
    if (strlen($cf) != 16 && strlen($cf) != 11) {
        return false;
    }
    
    // CF persona fisica (16 caratteri)
    if (strlen($cf) == 16) {
        return preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $cf);
    }
    
    // CF persona giuridica / P.IVA (11 caratteri)
    if (strlen($cf) == 11) {
        return preg_match('/^[0-9]{11}$/', $cf);
    }
    
    return false;
}

/**
 * Valida Partita IVA italiana
 * @param string $piva Partita IVA
 * @return bool
 */
function isValidPartitaIva($piva) {
    $piva = trim($piva);
    
    // Deve essere 11 cifre
    if (!preg_match('/^[0-9]{11}$/', $piva)) {
        return false;
    }
    
    // Algoritmo di controllo
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $n = $piva[$i];
        if ($i % 2 == 0) {
            $n *= 2;
            if ($n > 9) {
                $n -= 9;
            }
        }
        $sum += $n;
    }
    
    $check = (10 - ($sum % 10)) % 10;
    return $check == $piva[10];
}

/**
 * Valida email
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida CAP italiano
 * @param string $cap
 * @return bool
 */
function isValidCAP($cap) {
    return preg_match('/^[0-9]{5}$/', $cap);
}

/**
 * ====================================
 * FUNZIONI DI SICUREZZA
 * ====================================
 */

/**
 * Pulisci input per prevenire XSS
 * @param string $input
 * @return string
 */
function cleanInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Genera password casuale
 * @param int $length Lunghezza password
 * @return string
 */
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Genera token univoco
 * @param int $length
 * @return string
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * ====================================
 * FUNZIONI DI UTILITY
 * ====================================
 */

/**
 * Ottieni estensione file
 * @param string $filename
 * @return string
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Formatta dimensione file
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function formatFileSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Genera slug da stringa
 * @param string $text
 * @return string
 */
function slugify($text) {
    // Sostituisci caratteri accentati
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    // Rimuovi caratteri non alfanumerici
    $text = preg_replace('/[^a-z0-9-]/i', '-', strtolower(trim($text)));
    // Rimuovi trattini multipli
    $text = preg_replace('/-+/', '-', $text);
    // Rimuovi trattini iniziali e finali
    return trim($text, '-');
}

/**
 * Tronca testo mantenendo parole intere
 * @param string $text
 * @param int $length
 * @param string $suffix
 * @return string
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $truncated = substr($text, 0, $length);
    $lastSpace = strrpos($truncated, ' ');
    
    if ($lastSpace !== false) {
        $truncated = substr($truncated, 0, $lastSpace);
    }
    
    return $truncated . $suffix;
}

/**
 * ====================================
 * FUNZIONI DATE E ORARI
 * ====================================
 */

/**
 * Calcola età da data di nascita
 * @param string $birthdate
 * @return int
 */
function calculateAge($birthdate) {
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birth);
    return $age->y;
}

/**
 * Verifica se data è nel passato
 * @param string $date
 * @return bool
 */
function isPastDate($date) {
    return strtotime($date) < time();
}

/**
 * Verifica se data è nel futuro
 * @param string $date
 * @return bool
 */
function isFutureDate($date) {
    return strtotime($date) > time();
}

/**
 * Calcola giorni lavorativi tra due date
 * @param string $startDate
 * @param string $endDate
 * @return int
 */
function getWorkingDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end);
    
    $workingDays = 0;
    foreach ($period as $day) {
        // Esclude sabato (6) e domenica (0)
        if ($day->format('w') != 0 && $day->format('w') != 6) {
            $workingDays++;
        }
    }
    
    return $workingDays;
}

/**
 * ====================================
 * FUNZIONI ARRAY E OGGETTI
 * ====================================
 */

/**
 * Ordina array multidimensionale per chiave
 * @param array $array
 * @param string $key
 * @param int $direction SORT_ASC o SORT_DESC
 * @return array
 */
function sortArrayByKey($array, $key, $direction = SORT_ASC) {
    $column = array_column($array, $key);
    array_multisort($column, $direction, $array);
    return $array;
}

/**
 * Converte oggetto in array ricorsivamente
 * @param mixed $object
 * @return array
 */
function objectToArray($object) {
    if (is_object($object)) {
        $object = get_object_vars($object);
    }
    
    if (is_array($object)) {
        return array_map(__FUNCTION__, $object);
    }
    
    return $object;
}

/**
 * ====================================
 * FUNZIONI SPECIFICHE CRM
 * ====================================
 */

/**
 * Genera codice cliente
 * @param string $prefix Prefisso (default: CL)
 * @return string
 */
function generateCodiceCliente($prefix = 'CL') {
    $year = date('Y');
    $random = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . $year . $random;
}

/**
 * Formatta stato con badge colorato
 * @param string $stato
 * @return string HTML
 */
function formatStatoBadge($stato) {
    $badges = [
        'attivo' => '<span class="badge badge-success">Attivo</span>',
        'inattivo' => '<span class="badge badge-danger">Inattivo</span>',
        'sospeso' => '<span class="badge badge-warning">Sospeso</span>',
        'completato' => '<span class="badge badge-info">Completato</span>',
        'in_corso' => '<span class="badge badge-primary">In Corso</span>',
        'da_iniziare' => '<span class="badge badge-secondary">Da Iniziare</span>'
    ];
    
    return $badges[strtolower($stato)] ?? '<span class="badge badge-secondary">' . ucfirst($stato) . '</span>';
}

/**
 * Calcola percentuale completamento
 * @param int $completati
 * @param int $totale
 * @return float
 */
function calculatePercentage($completati, $totale) {
    if ($totale == 0) {
        return 0;
    }
    return round(($completati / $totale) * 100, 2);
}

/**
 * ====================================
 * FUNZIONI DI DEBUG (solo development)
 * ====================================
 */

/**
 * Debug variabile (solo se APP_DEBUG attivo)
 * @param mixed $var
 * @param string $label
 * @param bool $die
 */
function debug($var, $label = '', $die = false) {
    if (defined('APP_DEBUG') && APP_DEBUG === 'true') {
        echo '<pre style="background:#f5f5f5; padding:10px; margin:10px; border:1px solid #ddd;">';
        if ($label) {
            echo '<strong>' . htmlspecialchars($label) . ':</strong><br>';
        }
        print_r($var);
        echo '</pre>';
        
        if ($die) {
            die();
        }
    }
}

/**
 * Log su file (solo se APP_DEBUG attivo)
 * @param mixed $data
 * @param string $filename
 */
function logToFile($data, $filename = 'debug.log') {
    if (defined('APP_DEBUG') && APP_DEBUG === 'true') {
        $logDir = dirname(dirname(__DIR__)) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/' . $filename;
        $timestamp = date('Y-m-d H:i:s');
        $logData = "[$timestamp] " . print_r($data, true) . "\n";
        
        file_put_contents($logFile, $logData, FILE_APPEND);
    }
}