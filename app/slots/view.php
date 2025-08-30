<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /app/slots/list.php');
    exit;
}

// Carica dati posto
$slot = get_slot_by_id($id);
if (!$slot) {
    header('Location: /app/slots/list.php');
    exit;
}

// Carica marina
$marina = get_marina_by_id((int)$slot['marina_id']);

// Carica assegnazione corrente
$current = get_current_assignment($id);

// DEBUG: Verifica cosa contiene $current
// echo "<pre>DEBUG current: " . print_r($current, true) . "</pre>";

// Se il posto è occupato ma non c'è assegnazione, cerca l'ultima assegnazione
if ($slot['stato'] !== 'Libero' && !$current) {
    // Prova a recuperare l'ultima assegnazione anche se ha data_fine
    $stmt = $pdo->prepare("
        SELECT * FROM assignments 
        WHERE slot_id = :id 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $last_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se l'ultima assegnazione non è "Libero", usala come current
    if ($last_assignment && $last_assignment['stato'] !== 'Libero') {
        $current = $last_assignment;
    }
}

// Carica storico
$history = get_assignments_history($id);

$title = 'Posto ' . $slot['numero_esterno'] . ' - ' . $marina['name'];
$active = 'slots_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <h1 class="h4 mb-4">
        Posto <?php echo $slot['numero_esterno']; ?> - <?php echo $marina['name']; ?>
    </h1>
    
    <!-- DEBUG INFO - rimuovi dopo aver risolto -->
    <?php if (isset($_GET['debug'])): ?>
    <div class="alert alert-warning">
        <strong>DEBUG INFO:</strong><br>
        Stato slot: <?php echo $slot['stato']; ?><br>
        Current assignment: <?php echo $current ? 'SI (ID: '.$current['id'].')' : 'NO'; ?><br>
        <?php if ($current): ?>
            - Proprietario: <?php echo $current['proprietario'] ?? 'NULL'; ?><br>
            - Data fine: <?php echo $current['data_fine'] ?? 'NULL'; ?><br>
            - Stato assignment: <?php echo $current['stato'] ?? 'NULL'; ?><br>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- INFO POSTO -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <strong>Informazioni Posto</strong>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Pontile:</th>
                            <td><?php echo $marina['name']; ?></td>
                        </tr>
                        <tr>
                            <th>Numero:</th>
                            <td><strong><?php echo $slot['numero_esterno']; ?></strong></td>
                        </tr>
                        <tr>
                            <th>Stato attuale:</th>
                            <td><?php echo status_badge($slot['stato']); ?></td>
                        </tr>
                        <?php if ($slot['tipo']): ?>
                        <tr>
                            <th>Tipo:</th>
                            <td><?php echo $slot['tipo']; ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($slot['numero_interno']): ?>
                        <tr>
                            <th>Num. interno:</th>
                            <td><?php echo $slot['numero_interno']; ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($slot['note']): ?>
                        <tr>
                            <th>Note:</th>
                            <td><?php echo nl2br(htmlspecialchars($slot['note'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <div class="mt-3">
                        <a href="/app/slots/edit.php?id=<?php echo $id; ?>" 
                           class="btn btn-sm btn-outline-secondary">
                            Modifica info posto
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ASSEGNAZIONE CORRENTE -->
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <strong>Assegnazione Attuale</strong>
                </div>
                <div class="card-body">
                    <?php if ($slot['stato'] === 'Libero'): ?>
                        
                        <div class="alert alert-success mb-3">
                            <strong>✓ Questo posto è LIBERO</strong><br>
                            Puoi assegnarlo a qualcuno usando il pulsante sottostante.
                        </div>
                        
                    <?php elseif ($slot['stato'] === 'Manutenzione'): ?>
                        
                        <div class="alert alert-warning mb-3">
                            <strong>⚠️ Posto in MANUTENZIONE</strong><br>
                            Non disponibile per assegnazioni.
                        </div>
                        
                    <?php elseif ($current): ?>
                        <!-- C'è un'assegnazione -->
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Stato:</th>
                                <td><?php echo status_badge($slot['stato']); ?></td>
                            </tr>
                            <?php if (!empty($current['proprietario'])): ?>
                            <tr>
                                <th>Assegnato a:</th>
                                <td><strong><?php echo htmlspecialchars($current['proprietario']); ?></strong></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($current['targa'])): ?>
                            <tr>
                                <th>Targa:</th>
                                <td><?php echo htmlspecialchars($current['targa']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($current['email'])): ?>
                            <tr>
                                <th>Email:</th>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($current['email']); ?>">
                                        <?php echo htmlspecialchars($current['email']); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($current['telefono'])): ?>
                            <tr>
                                <th>Telefono:</th>
                                <td>
                                    <a href="tel:<?php echo htmlspecialchars($current['telefono']); ?>">
                                        <?php echo htmlspecialchars($current['telefono']); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($current['data_inizio'])): ?>
                            <tr>
                                <th>Dal:</th>
                                <td><?php echo format_date_from_ymd($current['data_inizio']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($current['data_fine'])): ?>
                            <tr>
                                <th>Al:</th>
                                <td><?php echo format_date_from_ymd($current['data_fine']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        
                    <?php else: ?>
                        <!-- Non c'è assegnazione ma il posto non è libero -->
                        <div class="alert alert-warning">
                            <strong>⚠️ Stato inconsistente</strong><br>
                            Il posto risulta <?php echo status_badge($slot['stato']); ?> ma non ha un'assegnazione attiva.<br>
                            <small>Usa il pulsante sotto per correggere lo stato.</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <?php if ($slot['stato'] === 'Libero'): ?>
                            <a href="/app/assignments/create.php?slot_id=<?php echo $id; ?>" 
                            class="btn btn-success">
                                <strong>➕ ASSEGNA QUESTO POSTO</strong>
                            </a>
                        <?php else: ?>
                            <a href="/app/assignments/create.php?slot_id=<?php echo $id; ?>" 
                            class="btn btn-warning">
                                <?php echo $current ? 'Cambia assegnazione' : 'Correggi stato'; ?>
                            </a>
                            <?php if ($current): ?>
                            <!-- Pulsante che apre il modal per liberare -->
                            <button type="button" class="btn btn-outline-danger" 
                                    data-bs-toggle="modal" data-bs-target="#liberaPostoModal">
                                Libera posto
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- STORICO -->
    <div class="card">
        <div class="card-header">
            <strong>Storico Assegnazioni (ultime 20)</strong>
        </div>
        <div class="card-body">
            <?php if (count($history) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Dal</th>
                                <th>Al</th>
                                <th>Stato</th>
                                <th>Proprietario</th>
                                <th>Targa</th>
                                <th>Email</th>
                                <th>Telefono</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 0;
                            foreach ($history as $h): 
                                if (++$count > 20) break;
                            ?>
                            <tr <?php echo !$h['data_fine'] ? 'class="table-info"' : ''; ?>>
                                <td><?php echo format_date_from_ymd($h['data_inizio']); ?></td>
                                <td>
                                    <?php echo format_date_from_ymd($h['data_fine']) ?: '<strong>ATTUALE</strong>'; ?>
                                </td>
                                <td><?php echo status_badge($h['stato']); ?></td>
                                <td><?php echo htmlspecialchars($h['proprietario'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($h['targa'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($h['email'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($h['telefono'] ?: '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">Nessuno storico disponibile</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ALLEGATI -->
    <div class="card mt-3">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <strong>📎 Allegati</strong>
                <a href="/app/attachments/upload_form.php?entity_type=slot&entity_id=<?php echo $id; ?>" 
                   class="btn btn-sm btn-success">
                    + Aggiungi allegato
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php 
            // Query SENZA JOIN per evitare problemi
            $stmt = $pdo->prepare("
                SELECT * FROM attachments 
                WHERE entity_type = ? AND entity_id = ?
                ORDER BY uploaded_at DESC
            ");
            $stmt->execute(['slot', $id]);
            $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug
            echo "<!-- Trovati " . count($attachments) . " allegati -->";
            ?>
            
            <?php if (count($attachments) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th width="50">Tipo</th>
                                <th>Nome file</th>
                                <th>Descrizione</th>
                                <th>Dimensione</th>
                                <th>Data</th>
                                <th width="100">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attachments as $att): ?>
                            <tr>
                                <td class="text-center">
                                    <?php 
                                    $icons = [
                                        'pdf' => '📄',
                                        'jpg' => '🖼️',
                                        'jpeg' => '🖼️',
                                        'png' => '🖼️',
                                        'doc' => '📝',
                                        'docx' => '📝',
                                        'xls' => '📊',
                                        'xlsx' => '📊'
                                    ];
                                    echo $icons[$att['file_type']] ?? '📎';
                                    ?>
                                </td>
                                <td>
                                    <a href="/app/attachments/download.php?id=<?php echo $att['id']; ?>" 
                                       target="_blank">
                                        <?php echo htmlspecialchars($att['original_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($att['description'] ?: '-'); ?></small>
                                </td>
                                <td>
                                    <small>
                                        <?php 
                                        $bytes = (int)$att['file_size'];
                                        if ($bytes < 1024) {
                                            echo $bytes . ' B';
                                        } elseif ($bytes < 1048576) {
                                            echo round($bytes / 1024, 1) . ' KB';
                                        } else {
                                            echo round($bytes / 1048576, 1) . ' MB';
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <small>
                                        <?php 
                                        $date = substr($att['uploaded_at'], 0, 10);
                                        echo format_date_from_ymd($date);
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <a href="/app/attachments/delete.php?id=<?php echo $att['id']; ?>&return=slot&return_id=<?php echo $id; ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Eliminare questo allegato?')">
                                        Elimina
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Nessun allegato presente</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Upload -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="/app/attachments/upload.php" enctype="multipart/form-data">
                    <input type="hidden" name="entity_type" value="slot">
                    <input type="hidden" name="entity_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="return_url" value="/app/slots/view.php?id=<?php echo $id; ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Carica allegato</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">File *</label>
                            <input type="file" name="attachment" class="form-control" required
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                            <small class="text-muted">
                                Formati: PDF, JPG, PNG, Word, Excel. Max 10MB
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrizione (opzionale)</label>
                            <textarea name="description" class="form-control" rows="2"
                                    placeholder="es. Contratto, Foto imbarcazione, Documenti..."></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Annulla
                        </button>
                        <button type="submit" class="btn btn-success">
                            Carica file
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal per liberare il posto -->
    <?php if ($slot['stato'] !== 'Libero'): ?>
    <div class="modal fade" id="liberaPostoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="/app/assignments/libera_posto.php" onsubmit="console.log('Form submitted'); return true;">
                    <input type="hidden" name="slot_id" value="<?php echo $id; ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Libera Posto <?php echo $slot['numero_esterno']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <!-- Debug info -->
                        <div class="alert alert-info small">
                            <strong>Debug Info:</strong><br>
                            Slot ID: <?php echo $id; ?><br>
                            Stato attuale: <?php echo $slot['stato']; ?><br>
                            <?php if ($current): ?>
                            Assignment ID: <?php echo $current['id'] ?? 'N/A'; ?><br>
                            Proprietario: <?php echo $current['proprietario'] ?? 'N/A'; ?><br>
                            Data fine attuale: <?php echo $current['data_fine'] ?? '0000-00-00'; ?>
                            <?php else: ?>
                            Nessuna assegnazione corrente
                            <?php endif; ?>
                        </div>
                        
                        <p>Stai per liberare il posto <strong><?php echo $slot['numero_esterno']; ?></strong></p>
                        
                        <?php if ($current && !empty($current['proprietario'])): ?>
                        <p>Attualmente assegnato a: <strong><?php echo htmlspecialchars($current['proprietario']); ?></strong></p>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Data di liberazione *</label>
                            <input type="date" 
                                   name="data_liberazione" 
                                   class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" 
                                   required>
                            <small class="text-muted">
                                L'assegnazione terminerà in questa data
                            </small>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Annulla
                        </button>
                        <button type="submit" class="btn btn-danger">
                            Conferma liberazione
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="mt-3">
        <a href="/app/slots/list.php" class="btn btn-outline-secondary">
            ← Torna alla lista
        </a>
    </div>
</div>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>