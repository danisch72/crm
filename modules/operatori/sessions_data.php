<?php
/**
 * modules/operatori/sessions_data.php - Dati Sessioni per Modal
 * 
 * Endpoint AJAX per caricamento cronologia sessioni operatore:
 * - Cronologia completa sessioni lavoro
 * - Dettagli tracking e interruzioni
 * - Calcoli produttività e anomalie
 * - Export formattato per visualizzazione
 */

// Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Percorsi assoluti per evitare problemi
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/classes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/auth/AuthSystem.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/functions/helpers.php';


// Verifica autenticazione
if (!AuthSystem::isAuthenticated()) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$sessionInfo = AuthSystem::getSessionInfo();
$db = Database::getInstance();

// Recupera parametri
$operatoreId = $_GET['id'] ?? null;
$periodo = $_GET['periodo'] ?? '30';

if (!$operatoreId) {
    echo '<div style="color: var(--danger-red);">ID operatore mancante</div>';
    exit;
}

// Verifica permessi
$operatore = $db->selectOne("SELECT * FROM operatori WHERE id = ?", [$operatoreId]);
if (!$operatore) {
    echo '<div style="color: var(--danger-red);">Operatore non trovato</div>';
    exit;
}

$canView = $sessionInfo['is_admin'] || $sessionInfo['operatore_id'] == $operatoreId;
if (!$canView) {
    echo '<div style="color: var(--danger-red);">Permessi insufficienti</div>';
    exit;
}

$dataInizio = date('Y-m-d', strtotime("-$periodo days"));

// Recupera sessioni con dettagli
$sessioni = $db->select(
    "SELECT 
        sl.*,
        CASE 
            WHEN sl.is_attiva = 1 THEN TIMESTAMPDIFF(SECOND, sl.login_timestamp, NOW()) / 3600
            ELSE sl.ore_effettive 
        END as ore_calcolate,
        COUNT(DISTINCT tt.id) as tracking_count,
        COUNT(DISTINCT i.id) as interruzioni_count,
        COALESCE(SUM(tt.ore_lavorate), 0) as ore_tracked
     FROM sessioni_lavoro sl
     LEFT JOIN tracking_task tt ON sl.id = tt.sessione_id
     LEFT JOIN interruzioni i ON sl.id = i.sessione_id
     WHERE sl.operatore_id = ? 
     AND sl.login_timestamp >= ?
     GROUP BY sl.id
     ORDER BY sl.login_timestamp DESC",
    [$operatoreId, $dataInizio]
);

// Calcoli aggregati
$totaleSessioni = count($sessioni);
$oreTotali = array_sum(array_column($sessioni, 'ore_calcolate'));
$oreExtra = array_sum(array_column($sessioni, 'ore_extra'));
$sessioniAttive = count(array_filter($sessioni, function($s) { return $s['is_attiva']; }));

// Anomalie e insights
$anomalie = [];

foreach ($sessioni as $sessione) {
    // Sessioni troppo lunghe (>12 ore)
    if ($sessione['ore_calcolate'] > 12) {
        $anomalie[] = [
            'type' => 'warning',
            'sessione_id' => $sessione['id'],
            'message' => 'Sessione molto lunga (' . number_format($sessione['ore_calcolate'], 1) . 'h)',
            'date' => $sessione['login_timestamp']
        ];
    }
    
    // Sessioni senza logout
    if ($sessione['is_attiva'] && strtotime($sessione['login_timestamp']) < strtotime('-1 day')) {
        $anomalie[] = [
            'type' => 'error',
            'sessione_id' => $sessione['id'],
            'message' => 'Sessione non chiusa da più di 24h',
            'date' => $sessione['login_timestamp']
        ];
    }
    
    // Discrepanza tracking
    if ($sessione['ore_tracked'] > 0 && abs($sessione['ore_tracked'] - $sessione['ore_calcolate']) > 2) {
        $anomalie[] = [
            'type' => 'info',
            'sessione_id' => $sessione['id'],
            'message' => 'Discrepanza tracking: ' . number_format(abs($sessione['ore_tracked'] - $sessione['ore_calcolate']), 1) . 'h',
            'date' => $sessione['login_timestamp']
        ];
    }
}
?>

<div style="max-height: 600px; overflow-y: auto;">
    <!-- Header Riepilogo -->
    <div style="background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem;">
        <h4 style="margin: 0 0 1rem 0; color: var(--gray-800);">
            📊 Riepilogo Ultimi <?= $periodo ?> Giorni
        </h4>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem;">
            <div style="text-align: center;">
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-green);">
                    <?= $totaleSessioni ?>
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                    Sessioni
                </div>
            </div>
            
            <div style="text-align: center;">
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-green);">
                    <?= number_format($oreTotali, 1) ?>h
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                    Ore Totali
                </div>
            </div>
            
            <div style="text-align: center;">
                <div style="font-size: 1.5rem; font-weight: 700; color: <?= $oreExtra > 0 ? 'var(--accent-orange)' : 'var(--secondary-green)' ?>;">
                    <?= number_format($oreExtra, 1) ?>h
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                    Ore Extra
                </div>
            </div>
            
            <div style="text-align: center;">
                <div style="font-size: 1.5rem; font-weight: 700; color: <?= $sessioniAttive > 0 ? 'var(--secondary-green)' : 'var(--gray-500)' ?>;">
                    <?= $sessioniAttive ?>
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                    Attive
                </div>
            </div>
        </div>
    </div>
    
    <!-- Anomalie -->
    <?php if (!empty($anomalie)): ?>
    <div style="background: var(--danger-red); color: white; padding: 1rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem;">
        <h4 style="color: white; margin: 0 0 0.5rem 0;">⚠️ Anomalie Rilevate</h4>
        <ul style="margin: 0; padding-left: 1.5rem;">
            <?php foreach (array_slice($anomalie, 0, 5) as $anomalia): ?>
                <li style="margin-bottom: 0.25rem;">
                    <?= htmlspecialchars($anomalia['message']) ?> 
                    <small>(<?= date('d/m H:i', strtotime($anomalia['date'])) ?>)</small>
                </li>
            <?php endforeach; ?>
            <?php if (count($anomalie) > 5): ?>
                <li><em>... e altre <?= count($anomalie) - 5 ?> anomalie</em></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Lista Sessioni -->
    <div>
        <h4 style="margin: 0 0 1rem 0; color: var(--gray-800);">
            📅 Cronologia Sessioni
        </h4>
        
        <?php if (empty($sessioni)): ?>
            <div style="text-align: center; padding: 3rem; color: var(--gray-500);">
                📝 Nessuna sessione trovata per il periodo selezionato
            </div>
        <?php else: ?>
            <?php foreach ($sessioni as $sessione): ?>
                <div style="border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: 1rem; margin-bottom: 1rem; background: white;">
                    <!-- Header Sessione -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                        <div>
                            <div style="font-weight: 600; color: var(--gray-800); margin-bottom: 0.25rem;">
                                📅 <?= formatDateTimeIT($sessione['login_timestamp']) ?>
                                <?php if ($sessione['is_attiva']): ?>
                                    <span style="color: var(--secondary-green); font-size: var(--font-size-sm);">🔴 IN CORSO</span>
                                <?php endif; ?>
                            </div>
                            <div style="color: var(--gray-600); font-size: var(--font-size-sm);">
                                <?php if ($sessione['modalita_lavoro']): ?>
                                    <?= $sessione['modalita_lavoro'] === 'ufficio' ? '🏢 Ufficio' : '🏠 Smart Working' ?> • 
                                <?php endif; ?>
                                Sessione #<?= $sessione['id'] ?>
                            </div>
                        </div>
                        
                        <div style="text-align: right;">
                            <div style="font-weight: 600; color: var(--primary-green);">
                                <?= number_format($sessione['ore_calcolate'], 2) ?> ore
                            </div>
                            <?php if ($sessione['ore_extra'] > 0): ?>
                                <div style="color: var(--accent-orange); font-size: var(--font-size-sm);">
                                    +<?= number_format($sessione['ore_extra'], 2) ?>h extra
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Dettagli -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 0.75rem; font-size: var(--font-size-sm);">
                        <div>
                            <strong>Inizio:</strong><br>
                            <?= date('H:i:s', strtotime($sessione['login_timestamp'])) ?>
                        </div>
                        
                        <div>
                            <strong>Fine:</strong><br>
                            <?php if ($sessione['logout_timestamp']): ?>
                                <?= date('H:i:s', strtotime($sessione['logout_timestamp'])) ?>
                            <?php else: ?>
                                <span style="color: var(--secondary-green);">In corso...</span>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <strong>Tracking:</strong><br>
                            <?= $sessione['tracking_count'] ?> task (<?= number_format($sessione['ore_tracked'], 1) ?>h)
                        </div>
                        
                        <div>
                            <strong>Interruzioni:</strong><br>
                            <?= $sessione['interruzioni_count'] ?> eventi
                        </div>
                    </div>
                    
                    <!-- Note -->
                    <?php if ($sessione['note']): ?>
                    <div style="background: var(--gray-50); padding: 0.75rem; border-radius: var(--radius-md); margin-top: 0.75rem;">
                        <strong style="color: var(--gray-700);">📝 Note:</strong><br>
                        <span style="color: var(--gray-600); font-size: var(--font-size-sm);">
                            <?= htmlspecialchars($sessione['note']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Indicatori Anomalie -->
                    <?php 
                    $sessioneAномалии = array_filter($anomalie, function($a) use ($sessione) {
                        return $a['sessione_id'] == $sessione['id'];
                    });
                    ?>
                    <?php if (!empty($sessioneAномалии)): ?>
                    <div style="background: var(--warning-yellow); padding: 0.5rem; border-radius: var(--radius-md); margin-top: 0.75rem;">
                        <?php foreach ($sessioneAномалии as $anomalia): ?>
                            <div style="color: var(--gray-800); font-size: var(--font-size-sm);">
                                ⚠️ <?= htmlspecialchars($anomalia['message']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Progress Bar Produttività -->
                    <?php if ($sessione['ore_tracked'] > 0 && $sessione['ore_calcolate'] > 0): ?>
                    <?php 
                    $produttivita = min(100, ($sessione['ore_tracked'] / $sessione['ore_calcolate']) * 100);
                    $coloreProduttivita = $produttivita >= 80 ? 'var(--secondary-green)' : 
                                         ($produttivita >= 60 ? 'var(--accent-orange)' : 'var(--danger-red)');
                    ?>
                    <div style="margin-top: 0.75rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem; font-size: var(--font-size-sm);">
                            <span style="color: var(--gray-600);">Produttività Tracking</span>
                            <span style="color: var(--gray-800); font-weight: 500;"><?= round($produttivita) ?>%</span>
                        </div>
                        <div style="width: 100%; height: 6px; background: var(--gray-200); border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; background: <?= $coloreProduttivita ?>; width: <?= $produttivita ?>%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Footer Info -->
    <div style="background: var(--gray-50); padding: 1rem; border-radius: var(--radius-lg); margin-top: 1.5rem; text-align: center; color: var(--gray-600); font-size: var(--font-size-sm);">
        📊 Dati aggiornati in tempo reale • 
        Periodo: <?= date('d/m/Y', strtotime($dataInizio)) ?> - <?= date('d/m/Y') ?> • 
        Generato: <?= date('d/m/Y H:i') ?>
    </div>
</div>

<style>
/* Stili specifici per il modal */
@media (max-width: 768px) {
    div[style*="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr))"] {
        grid-template-columns: 1fr !important;
    }
    
    div[style*="grid-template-columns: repeat(auto-fit, minmax(120px, 1fr))"] {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

/* Animazioni smooth per progress bar */
div[style*="transition: width 0.3s ease"] {
    transition: width 0.3s ease !important;
}
</style>

<script>
// Animazioni per progress bar
document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('div[style*="transition: width 0.3s ease"]');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 300);
    });
});

// Auto-scroll verso le sessioni attive
const activeSession = document.querySelector('[style*="🔴 IN CORSO"]');
if (activeSession) {
    setTimeout(() => {
        activeSession.closest('div[style*="border: 1px solid"]').scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }, 500);
}
</script>