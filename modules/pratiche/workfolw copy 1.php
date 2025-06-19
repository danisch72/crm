<?php
/**
 * modules/pratiche/workflow.php - Gestione Stati e Workflow Pratica
 * 
 * ✅ INTERFACCIA PER CAMBIO STATI E WORKFLOW
 * 
 * Features:
 * - Cambio stato pratica con validazioni
 * - Transizioni autorizzate per ruolo
 * - Log modifiche e audit trail
 * - Notifiche cambio stato
 * - Approvazioni richieste
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Variabili dal router:
// $sessionInfo, $db, $currentUser, $pratica (già caricata dal router)

// Definizione workflow - transizioni permesse per stato
$workflowTransitions = [
    'da_iniziare' => ['in_corso', 'sospesa'],
    'in_corso' => ['completata', 'sospesa'],
    'completata' => ['in_corso', 'fatturata'], // può tornare in corso se serve
    'sospesa' => ['in_corso', 'da_iniziare'],
    'fatturata' => [] // stato finale
];

// Stati che richiedono approvazione admin
$statiApprovazione = ['fatturata'];

// Verifica transizioni disponibili per stato corrente
$transizioniDisponibili = $workflowTransitions[$pratica['stato']] ?? [];

// Carica storico modifiche stato
$storicoStati = $db->select("
    SELECT 
        pl.*,
        CONCAT(o.nome, ' ', o.cognome) as operatore_nome
    FROM pratiche_activity_log pl
    LEFT JOIN operatori o ON pl.operatore_id = o.id
    WHERE pl.pratica_id = ?
    AND pl.action = 'cambio_stato'
    ORDER BY pl.created_at DESC
    LIMIT 20
", [$pratica['id']]);

// Carica task pratica per verifiche
$tasks = $db->select("
    SELECT 
        stato,
        COUNT(*) as count,
        SUM(CASE WHEN stato = 'completato' THEN 1 ELSE 0 END) as completati
    FROM task
    WHERE pratica_id = ?
    GROUP BY stato
", [$pratica['id']]);

$taskStats = [
    'totali' => array_sum(array_column($tasks, 'count')),
    'completati' => array_sum(array_column($tasks, 'completati'))
];

$completamentoPercentuale = $taskStats['totali'] > 0 
    ? round(($taskStats['completati'] / $taskStats['totali']) * 100) 
    : 0;

// Gestione cambio stato
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuovo_stato'])) {
    $nuovoStato = $_POST['nuovo_stato'];
    $note = $_POST['note'] ?? '';
    
    // Validazioni
    $errors = [];
    
    // Verifica transizione permessa
    if (!in_array($nuovoStato, $transizioniDisponibili)) {
        $errors[] = 'Transizione di stato non permessa';
    }
    
    // Verifica permessi admin per stati che lo richiedono
    if (in_array($nuovoStato, $statiApprovazione) && !$currentUser['is_admin']) {
        $errors[] = 'Solo gli amministratori possono impostare questo stato';
    }
    
    // Verifica completamento task per stato "completata"
    if ($nuovoStato === 'completata' && $completamentoPercentuale < 100) {
        $errors[] = 'Tutti i task devono essere completati prima di chiudere la pratica';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Salva stato precedente
            $statoPrecedente = $pratica['stato'];
            
            // Aggiorna stato pratica
            $db->update('pratiche', [
                'stato' => $nuovoStato,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$pratica['id']]);
            
            // Log cambio stato
            $db->insert('pratiche_activity_log', [
                'pratica_id' => $pratica['id'],
                'operatore_id' => $currentUser['id'],
                'action' => 'cambio_stato',
                'entity_type' => 'pratica',
                'entity_id' => $pratica['id'],
                'old_value' => $statoPrecedente,
                'new_value' => $nuovoStato,
                'metadata' => json_encode([
                    'note' => $note,
                    'completamento_percentuale' => $completamentoPercentuale
                ])
            ]);
            
            // Se pratica completata, aggiorna data completamento
            if ($nuovoStato === 'completata') {
                $db->update('pratiche', 
                    ['data_completamento' => date('Y-m-d H:i:s')],
                    'id = ?', 
                    [$pratica['id']]
                );
            }
            
            // Se pratica fatturata, aggiorna flag
            if ($nuovoStato === 'fatturata') {
                $db->update('pratiche', [
                    'is_fatturata' => 1,
                    'data_fatturazione' => date('Y-m-d H:i:s')
                ], 'id = ?', [$pratica['id']]);
            }
            
            $db->commit();
            
            $_SESSION['success_message'] = '✅ Stato pratica aggiornato con successo';
            header('Location: /crm/?action=pratiche&view=view&id=' . $pratica['id']);
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Errore durante l\'aggiornamento dello stato';
            error_log("Errore cambio stato: " . $e->getMessage());
        }
    }
}

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php';
?>

<!-- Container principale -->
<div class="px-3 py-2">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="/crm/?action=pratiche">Pratiche</a></li>
                    <li class="breadcrumb-item"><a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>"><?= htmlspecialchars($pratica['titolo']) ?></a></li>
                    <li class="breadcrumb-item active">Workflow</li>
                </ol>
            </nav>
            <h4 class="mb-0">
                <i class="bi bi-diagram-3 text-primary"></i> Gestione Workflow
            </h4>
        </div>
        
        <button class="btn btn-sm btn-outline-secondary" onclick="window.history.back()">
            <i class="bi bi-arrow-left"></i> Indietro
        </button>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Errore!</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Stato attuale e transizioni (sinistra) -->
        <div class="col-md-8">
            <!-- Card stato attuale -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Stato Attuale</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h3 class="mb-0">
                                <?php
                                $statoConfig = PRATICHE_STATI[$pratica['stato']] ?? null;
                                if ($statoConfig) {
                                    echo '<span style="color: ' . $statoConfig['color'] . '">' . 
                                         $statoConfig['icon'] . ' ' . $statoConfig['label'] . 
                                         '</span>';
                                }
                                ?>
                            </h3>
                            <p class="text-muted mb-0 mt-2">
                                Pratica creata il <?= date('d/m/Y', strtotime($pratica['created_at'])) ?>
                            </p>
                        </div>
                        
                        <div class="text-end">
                            <div class="progress" style="width: 200px; height: 20px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?= $completamentoPercentuale ?>%">
                                    <?= $completamentoPercentuale ?>%
                                </div>
                            </div>
                            <small class="text-muted">
                                <?= $taskStats['completati'] ?>/<?= $taskStats['totali'] ?> task completati
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card cambio stato -->
            <?php if (count($transizioniDisponibili) > 0): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Cambia Stato</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nuovo stato</label>
                                <div class="row g-2">
                                    <?php foreach ($transizioniDisponibili as $stato): ?>
                                        <?php $statoConfig = PRATICHE_STATI[$stato] ?? null; ?>
                                        <?php if ($statoConfig): ?>
                                            <div class="col-md-6">
                                                <div class="form-check card p-3">
                                                    <input class="form-check-input" type="radio" 
                                                           name="nuovo_stato" value="<?= $stato ?>" 
                                                           id="stato_<?= $stato ?>"
                                                           <?= in_array($stato, $statiApprovazione) && !$currentUser['is_admin'] ? 'disabled' : '' ?>>
                                                    <label class="form-check-label d-flex align-items-center" 
                                                           for="stato_<?= $stato ?>">
                                                        <span style="color: <?= $statoConfig['color'] ?>; font-size: 1.5rem; margin-right: 10px;">
                                                            <?= $statoConfig['icon'] ?>
                                                        </span>
                                                        <div>
                                                            <strong><?= $statoConfig['label'] ?></strong>
                                                            <?php if (in_array($stato, $statiApprovazione)): ?>
                                                                <br>
                                                                <small class="text-warning">
                                                                    <i class="bi bi-shield-lock"></i> Richiede approvazione admin
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Note (opzionale)</label>
                                <textarea name="note" class="form-control" rows="3" 
                                          placeholder="Aggiungi una nota sul cambio stato..."></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Importante:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php if (in_array('completata', $transizioniDisponibili)): ?>
                                        <li>Per impostare lo stato "Completata", tutti i task devono essere completati</li>
                                    <?php endif; ?>
                                    <?php if (in_array('fatturata', $transizioniDisponibili)): ?>
                                        <li>Lo stato "Fatturata" è irreversibile e richiede permessi di amministratore</li>
                                    <?php endif; ?>
                                    <li>Il cambio di stato verrà registrato nel log delle attività</li>
                                </ul>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Conferma Cambio Stato
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Non ci sono transizioni disponibili dallo stato attuale.
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Storico e info (destra) -->
        <div class="col-md-4">
            <!-- Info workflow -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-diagram-2"></i> Schema Workflow</h6>
                </div>
                <div class="card-body small">
                    <div class="workflow-diagram">
                        <div class="workflow-step">
                            <span class="badge bg-secondary">Da Iniziare</span>
                            <i class="bi bi-arrow-down"></i>
                        </div>
                        <div class="workflow-step">
                            <span class="badge bg-warning">In Corso</span>
                            <i class="bi bi-arrow-down"></i>
                        </div>
                        <div class="workflow-step">
                            <span class="badge bg-success">Completata</span>
                            <i class="bi bi-arrow-down"></i>
                        </div>
                        <div class="workflow-step">
                            <span class="badge bg-primary">Fatturata</span>
                        </div>
                        
                        <div class="mt-3 text-muted">
                            <small>
                                <i class="bi bi-arrow-left-right"></i> 
                                È possibile sospendere e riprendere in qualsiasi momento
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Storico modifiche -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-clock-history"></i> Storico Stati</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (count($storicoStati) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($storicoStati as $log): ?>
                                <div class="list-group-item small">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>
                                                <?= PRATICHE_STATI[$log['old_value']]['label'] ?? $log['old_value'] ?>
                                                →
                                                <?= PRATICHE_STATI[$log['new_value']]['label'] ?? $log['new_value'] ?>
                                            </strong>
                                            <br>
                                            <span class="text-muted">
                                                <?= htmlspecialchars($log['operatore_nome']) ?>
                                            </span>
                                            <?php 
                                            $metadata = json_decode($log['metadata'], true);
                                            if (!empty($metadata['note'])): 
                                            ?>
                                                <br>
                                                <em>"<?= htmlspecialchars($metadata['note']) ?>"</em>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted text-end">
                                            <?= date('d/m/Y', strtotime($log['created_at'])) ?>
                                            <br>
                                            <?= date('H:i', strtotime($log['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-center text-muted">
                            <i class="bi bi-inbox"></i>
                            <p class="mb-0">Nessuna modifica registrata</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS specifico -->
<style>
.workflow-diagram {
    text-align: center;
}

.workflow-step {
    margin: 10px 0;
}

.workflow-step .badge {
    padding: 8px 16px;
    font-size: 0.9rem;
}

.workflow-step i {
    display: block;
    margin: 5px 0;
    color: #6c757d;
}

.form-check.card:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

.form-check.card input[type="radio"]:checked + label {
    font-weight: bold;
}
</style>

<?php
// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/footer.php';
?>