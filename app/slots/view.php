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
                    <?php if ($current && $current['proprietario']): ?>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Stato:</th>
                                <td><?php echo status_badge($current['stato']); ?></td>
                            </tr>
                            <tr>
                                <th>Assegnato a:</th>
                                <td><strong><?php echo htmlspecialchars($current['proprietario']); ?></strong></td>
                            </tr>
                            <?php if ($current['targa']): ?>
                            <tr>
                                <th>Targa:</th>
                                <td><?php echo htmlspecialchars($current['targa']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($current['email']): ?>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($current['email']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($current['telefono']): ?>
                            <tr>
                                <th>Telefono:</th>
                                <td><?php echo htmlspecialchars($current['telefono']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Dal:</th>
                                <td><?php echo format_date_from_ymd($current['data_inizio']); ?></td>
                            </tr>
                            <?php if ($current['data_fine']): ?>
                            <tr>
                                <th>Al:</th>
                                <td><?php echo format_date_from_ymd($current['data_fine']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-success mb-3">
                            <strong>✓ Questo posto è LIBERO</strong><br>
                            Puoi assegnarlo a qualcuno usando il pulsante sottostante.
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
                                Cambia assegnazione
                            </a>
                            <?php if ($current): ?>
                            <form method="POST" action="/app/assignments/quick_free.php" 
                                  onsubmit="return confirm('Liberare questo posto?')">
                                <input type="hidden" name="slot_id" value="<?php echo $id; ?>">
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    Libera posto
                                </button>
                            </form>
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
            <strong>Storico Assegnazioni</strong>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?php echo format_date_from_ymd($h['data_inizio']); ?></td>
                                <td><?php echo format_date_from_ymd($h['data_fine']) ?: 'Attuale'; ?></td>
                                <td><?php echo status_badge($h['stato']); ?></td>
                                <td><?php echo htmlspecialchars($h['proprietario'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($h['targa'] ?: '-'); ?></td>
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
    
    <div class="mt-3">
        <a href="/app/slots/list.php" class="btn btn-outline-secondary">
            ← Torna alla lista
        </a>
    </div>
</div>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>