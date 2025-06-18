<?php
/**
 * modules/operatori/view.php - Vista Operatore Ultra-Compatta
 * 
 * ✅ LAYOUT DINAMICO 100% SCHERMO
 * ✅ TUTTE LE INFO IN UNA VISTA SENZA SCROLL
 */

if (!defined('OPERATORI_ROUTER_LOADED')) {
    header('Location: /crm/?action=operatori');
    exit;
}

$pageTitle = 'Dettaglio Operatore';
$pageIcon = '👤';

// ID operatore già validato dal router
$operatoreId = $_GET['id'];

// Carica dati operatore
$operatore = $db->selectOne("
    SELECT o.*,
           (SELECT COUNT(*) FROM clienti WHERE operatore_responsabile_id = o.id) as clienti_gestiti,
           (SELECT COUNT(*) FROM clienti WHERE operatore_responsabile_id = o.id AND stato = 'attivo') as clienti_attivi,
           (SELECT COUNT(*) FROM sessioni_lavoro WHERE operatore_id = o.id) as sessioni_totali,
           (SELECT COUNT(*) FROM sessioni_lavoro WHERE operatore_id = o.id AND DATE(login_timestamp) = CURDATE()) as sessioni_oggi,
           (SELECT SUM(TIMESTAMPDIFF(MINUTE, login_timestamp, IFNULL(logout_timestamp, NOW())) / 60) 
            FROM sessioni_lavoro WHERE operatore_id = o.id AND DATE(login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as ore_ultimo_mese
    FROM operatori o
    WHERE o.id = ?
", [$operatoreId]);

if (!$operatore) {
    $_SESSION['error_message'] = 'Operatore non trovato';
    header('Location: /crm/?action=operatori');
    exit;
}

// Decode qualifiche
$qualifiche = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];

// Ultimi accessi
$ultimiAccessi = $db->select("
    SELECT login_timestamp, logout_timestamp, ip_address,
           TIMESTAMPDIFF(MINUTE, login_timestamp, IFNULL(logout_timestamp, NOW())) as durata_minuti
    FROM sessioni_lavoro 
    WHERE operatore_id = ?
    ORDER BY login_timestamp DESC
    LIMIT 10
", [$operatoreId]) ?: [];

// Clienti recenti
$clientiRecenti = $db->select("
    SELECT id, ragione_sociale, stato, created_at
    FROM clienti
    WHERE operatore_responsabile_id = ?
    ORDER BY created_at DESC
    LIMIT 5
", [$operatoreId]) ?: [];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?> - CRM Re.De</title>
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-ultra-compact.css">
    <style>
        /* Layout a 3 colonne per massima densità */
        .view-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            height: calc(100vh - 120px);
        }
        
        .info-box {
            background: white;
            border: 1px solid #dee2e6;
            padding: 8px;
            height: fit-content;
        }
        
        .info-box h3 {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            font-size: 11px;
        }
        
        .info-label {
            color: #6c757d;
            font-size: 10px;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .qualifica-tag {
            display: inline-block;
            padding: 2px 6px;
            background: #e9ecef;
            font-size: 10px;
            margin: 2px;
            border-radius: 2px;
        }
        
        .timeline-compact {
            font-size: 11px;
        }
        
        .timeline-item {
            padding: 4px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .col-full {
            grid-column: 1 / -1;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 4px;
        }
        
        .status-online {
            background: #28a745;
        }
        
        .status-offline {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
            
            <main class="main-content">
                <!-- Header compatto con azioni -->
                <div class="page-header" style="padding: 8px 12px; margin-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 48px; height: 48px; background: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 600;">
                            <?= strtoupper(substr($operatore['nome'], 0, 1) . substr($operatore['cognome'], 0, 1)) ?>
                        </div>
                        <div>
                            <h1 style="font-size: 16px; margin: 0;">
                                <?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?>
                                <?php if ($operatore['is_amministratore']): ?>
                                    <span class="badge badge-warning" style="margin-left: 8px;">ADMIN</span>
                                <?php endif; ?>
                                <span class="badge badge-<?= $operatore['is_attivo'] ? 'success' : 'danger' ?>" style="margin-left: 4px;">
                                    <?= $operatore['is_attivo'] ? 'ATTIVO' : 'INATTIVO' ?>
                                </span>
                            </h1>
                            <div style="font-size: 11px; color: #6c757d;">
                                <?= $operatore['codice_operatore'] ?> • 
                                Registrato il <?= date('d/m/Y', strtotime($operatore['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 8px;">
                        <a href="/crm/?action=operatori" class="btn btn-secondary btn-sm">← Lista</a>
                        <?php if ($sessionInfo['is_admin'] || $operatore['id'] == $sessionInfo['operatore_id']): ?>
                        <a href="/crm/?action=operatori&view=edit&id=<?= $operatoreId ?>" class="btn btn-primary btn-sm">✏ Modifica</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Grid a 3 colonne -->
                <div class="view-grid">
                    <!-- Colonna 1: Dati principali -->
                    <div>
                        <!-- Info contatto -->
                        <div class="info-box">
                            <h3>📞 Contatti</h3>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <a href="mailto:<?= $operatore['email'] ?>" style="color: #007bff; font-size: 11px;">
                                    <?= htmlspecialchars($operatore['email']) ?>
                                </a>
                            </div>
                            <?php if ($operatore['telefono']): ?>
                            <div class="info-row">
                                <span class="info-label">Telefono</span>
                                <a href="tel:<?= $operatore['telefono'] ?>" style="color: inherit;">
                                    <?= htmlspecialchars($operatore['telefono']) ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Orari -->
                        <div class="info-box" style="margin-top: 12px;">
                            <h3>⏰ Orari Lavoro</h3>
                            <?php if ($operatore['orario_mattino_inizio'] && $operatore['orario_mattino_fine']): ?>
                            <div class="info-row">
                                <span class="info-label">Mattino</span>
                                <span class="info-value">
                                    <?= substr($operatore['orario_mattino_inizio'], 0, 5) ?> - 
                                    <?= substr($operatore['orario_mattino_fine'], 0, 5) ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ($operatore['orario_pomeriggio_inizio'] && $operatore['orario_pomeriggio_fine']): ?>
                            <div class="info-row">
                                <span class="info-label">Pomeriggio</span>
                                <span class="info-value">
                                    <?= substr($operatore['orario_pomeriggio_inizio'], 0, 5) ?> - 
                                    <?= substr($operatore['orario_pomeriggio_fine'], 0, 5) ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Qualifiche -->
                        <div class="info-box" style="margin-top: 12px;">
                            <h3>🎯 Qualifiche</h3>
                            <?php if (empty($qualifiche)): ?>
                                <div style="color: #6c757d; font-size: 11px;">Nessuna qualifica</div>
                            <?php else: ?>
                                <div>
                                    <?php foreach ($qualifiche as $q): ?>
                                        <span class="qualifica-tag"><?= htmlspecialchars($q) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Colonna 2: Statistiche -->
                    <div>
                        <!-- Statistiche generali -->
                        <div class="info-box">
                            <h3>📊 Statistiche</h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                <div style="text-align: center; padding: 8px; background: #f8f9fa; border-radius: 3px;">
                                    <div style="font-size: 20px; font-weight: 600; color: #28a745;">
                                        <?= $operatore['clienti_attivi'] ?>
                                    </div>
                                    <div style="font-size: 10px; color: #6c757d;">Clienti Attivi</div>
                                </div>
                                <div style="text-align: center; padding: 8px; background: #f8f9fa; border-radius: 3px;">
                                    <div style="font-size: 20px; font-weight: 600;">
                                        <?= $operatore['clienti_gestiti'] ?>
                                    </div>
                                    <div style="font-size: 10px; color: #6c757d;">Totali</div>
                                </div>
                                <div style="text-align: center; padding: 8px; background: #f8f9fa; border-radius: 3px;">
                                    <div style="font-size: 20px; font-weight: 600;">
                                        <?= number_format($operatore['ore_ultimo_mese'] ?? 0, 0) ?>h
                                    </div>
                                    <div style="font-size: 10px; color: #6c757d;">Ore (30gg)</div>
                                </div>
                                <div style="text-align: center; padding: 8px; background: #f8f9fa; border-radius: 3px;">
                                    <div style="font-size: 20px; font-weight: 600;">
                                        <?= $operatore['sessioni_oggi'] ?>
                                    </div>
                                    <div style="font-size: 10px; color: #6c757d;">Login Oggi</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Clienti recenti -->
                        <div class="info-box" style="margin-top: 12px;">
                            <h3>🏢 Clienti Recenti</h3>
                            <?php if (empty($clientiRecenti)): ?>
                                <div style="color: #6c757d; font-size: 11px;">Nessun cliente</div>
                            <?php else: ?>
                                <div class="timeline-compact">
                                    <?php foreach ($clientiRecenti as $cliente): ?>
                                    <div class="timeline-item">
                                        <div style="display: flex; justify-content: space-between;">
                                            <a href="/crm/?action=clienti&view=view&id=<?= $cliente['id'] ?>" 
                                               style="color: inherit; text-decoration: none; font-weight: 500;">
                                                <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                                            </a>
                                            <span class="badge badge-<?= $cliente['stato'] === 'attivo' ? 'success' : 'warning' ?>" 
                                                  style="font-size: 9px;">
                                                <?= strtoupper(substr($cliente['stato'], 0, 3)) ?>
                                            </span>
                                        </div>
                                        <div style="font-size: 10px; color: #6c757d;">
                                            <?= date('d/m/Y', strtotime($cliente['created_at'])) ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Colonna 3: Attività -->
                    <div>
                        <!-- Ultimi accessi -->
                        <div class="info-box">
                            <h3>🔐 Ultimi Accessi</h3>
                            <?php if (empty($ultimiAccessi)): ?>
                                <div style="color: #6c757d; font-size: 11px;">Nessun accesso</div>
                            <?php else: ?>
                                <div class="timeline-compact" style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($ultimiAccessi as $accesso): ?>
                                    <div class="timeline-item">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <span class="status-indicator <?= $accesso['logout_timestamp'] ? 'status-offline' : 'status-online' ?>"></span>
                                                <?= date('d/m H:i', strtotime($accesso['login_timestamp'])) ?>
                                            </div>
                                            <span style="font-size: 10px; color: #6c757d;">
                                                <?= round($accesso['durata_minuti'] / 60, 1) ?>h
                                            </span>
                                        </div>
                                        <div style="font-size: 9px; color: #6c757d;">
                                            IP: <?= htmlspecialchars($accesso['ip_address']) ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Info sistema -->
                        <div class="info-box" style="margin-top: 12px;">
                            <h3>ℹ️ Info Sistema</h3>
                            <div class="info-row">
                                <span class="info-label">Ultimo accesso</span>
                                <span class="info-value">
                                    <?= $operatore['ultimo_accesso'] ? date('d/m/Y H:i', strtotime($operatore['ultimo_accesso'])) : 'Mai' ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Sessioni totali</span>
                                <span class="info-value"><?= $operatore['sessioni_totali'] ?></span>
                            </div>
                            <?php if ($operatore['updated_at']): ?>
                            <div class="info-row">
                                <span class="info-label">Ultima modifica</span>
                                <span class="info-value"><?= date('d/m/Y', strtotime($operatore['updated_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>