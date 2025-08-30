<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$title = 'Lista d\'attesa';
$active = 'waiting_list';

// Filtri
$tipologia = $_GET['tipologia'] ?? '';
$luogo = $_GET['luogo'] ?? '';
$attivo = $_GET['attivo'] ?? '1';
$q = trim($_GET['q'] ?? '');

// Query principale
$params = [];
$sql = "SELECT * FROM waiting_list WHERE 1=1 ";

if ($tipologia) {
    $sql .= " AND tipologia = :tipologia ";
    $params[':tipologia'] = $tipologia;
}

if ($luogo) {
    $sql .= " AND luogo = :luogo ";
    $params[':luogo'] = $luogo;
}

if ($attivo !== '') {
    $sql .= " AND attivo = :attivo ";
    $params[':attivo'] = (int)$attivo;
}

if ($q) {
    $sql .= " AND (cognome LIKE :q OR nome LIKE :q2 OR email LIKE :q3 OR telefono LIKE :q4) ";
    $params[':q'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
    $params[':q4'] = '%' . $q . '%';
}

// Ordinamento: prima Melide, poi per data
$sql .= " ORDER BY 
    tipologia ASC,
    CASE WHEN luogo = 'Melide' THEN 0 ELSE 1 END,
    luogo ASC,
    data_iscrizione ASC,
    id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Raggruppa per tipologia
$barche = array_filter($rows, fn($r) => $r['tipologia'] === 'Barca');
$canoe = array_filter($rows, fn($r) => $r['tipologia'] === 'Canoa');

// Conta totali
$stmt_total = $pdo->query("SELECT COUNT(*) FROM waiting_list WHERE attivo = 1");
$total_attivi = $stmt_total->fetchColumn();

include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Lista d'attesa</h1>
            <small class="text-muted">
                <?php echo count($rows); ?> iscritti totali 
                (<?php echo $total_attivi; ?> attivi)
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="/app/waiting/create.php" class="btn btn-success btn-sm">
                + Aggiungi iscrizione
            </a>
            <a href="/app/waiting/verify.php" class="btn btn-warning btn-sm">
                Verifica annuale
            </a>
        </div>
    </div>

    <!-- Filtri -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label small">Tipologia</label>
                        <select name="tipologia" class="form-select form-select-sm">
                            <option value="">Tutte</option>
                            <option value="Barca" <?php echo $tipologia === 'Barca' ? 'selected' : ''; ?>>Barca</option>
                            <option value="Canoa" <?php echo $tipologia === 'Canoa' ? 'selected' : ''; ?>>Canoa</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Luogo</label>
                        <input type="text" name="luogo" class="form-control form-control-sm" 
                               value="<?php echo htmlspecialchars($luogo); ?>" 
                               placeholder="es. Melide">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Stato</label>
                        <select name="attivo" class="form-select form-select-sm">
                            <option value="1" <?php echo $attivo === '1' ? 'selected' : ''; ?>>Attivi</option>
                            <option value="0" <?php echo $attivo === '0' ? 'selected' : ''; ?>>Non attivi</option>
                            <option value="">Tutti</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small">Ricerca</label>
                        <input type="text" name="q" class="form-control form-control-sm" 
                               value="<?php echo htmlspecialchars($q); ?>" 
                               placeholder="Nome, cognome, email...">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm me-2">Filtra</button>
                        <a href="/app/waiting/list.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista BARCHE -->
    <?php if (!$tipologia || $tipologia === 'Barca'): ?>
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <strong>🚤 Lista d'attesa BARCHE (<?php echo count($barche); ?>)</strong>
        </div>
        <div class="card-body">
            <?php if (count($barche) > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th width="30">#</th>
                            <th>Cognome Nome</th>
                            <th>Luogo</th>
                            <th>Via</th>
                            <th>Contatti</th>
                            <th>Motore/Dim.</th>
                            <th>Iscrizione</th>
                            <th>Verifica</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $pos = 1;
                        foreach ($barche as $row): 
                            $giorni_attesa = (int)((time() - strtotime($row['data_iscrizione'])) / 86400);
                        ?>
                        <tr class="<?php echo !$row['attivo'] ? 'table-secondary' : ''; ?>">
                            <td>
                                <strong><?php echo $pos++; ?></strong>
                                <?php if ($row['luogo'] === 'Melide'): ?>
                                    <span class="badge bg-success">M</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['cognome'] . ' ' . $row['nome']); ?></strong>
                                <?php if (!$row['attivo']): ?>
                                    <span class="badge bg-secondary">Non attivo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['luogo']); ?></td>
                            <td><small><?php echo htmlspecialchars($row['via']); ?></small></td>
                            <td>
                                <small>
                                    📧 <?php echo htmlspecialchars($row['email']); ?><br>
                                    📞 <?php echo htmlspecialchars($row['telefono']); ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($row['motore_kw']): ?>
                                    <small>Motore: <?php echo htmlspecialchars($row['motore_kw']); ?> KW</small><br>
                                <?php endif; ?>
                                <?php if ($row['dimensioni']): ?>
                                    <small>Dim: <?php echo htmlspecialchars($row['dimensioni']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php echo format_date_from_ymd($row['data_iscrizione']); ?><br>
                                    <span class="text-muted">(<?php echo $giorni_attesa; ?> gg)</span>
                                </small>
                            </td>
                            <td>
                                <?php if ($row['ultima_verifica']): ?>
                                    <small class="text-success">
                                        ✓ <?php echo format_date_from_ymd($row['ultima_verifica']); ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-warning">Mai verificato</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <a href="/app/waiting/edit.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">Modifica</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted mb-0">Nessuna barca in lista d'attesa</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Lista CANOE -->
    <?php if (!$tipologia || $tipologia === 'Canoa'): ?>
    <div class="card">
        <div class="card-header bg-info text-white">
            <strong>🛶 Lista d'attesa CANOE (<?php echo count($canoe); ?>)</strong>
        </div>
        <div class="card-body">
            <?php if (count($canoe) > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th width="30">#</th>
                            <th>Cognome Nome</th>
                            <th>Luogo</th>
                            <th>Via</th>
                            <th>Contatti</th>
                            <th>Info</th>
                            <th>Iscrizione</th>
                            <th>Verifica</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $pos = 1;
                        foreach ($canoe as $row): 
                            $giorni_attesa = (int)((time() - strtotime($row['data_iscrizione'])) / 86400);
                        ?>
                        <tr class="<?php echo !$row['attivo'] ? 'table-secondary' : ''; ?>">
                            <td>
                                <strong><?php echo $pos++; ?></strong>
                                <?php if ($row['luogo'] === 'Melide'): ?>
                                    <span class="badge bg-success">M</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['cognome'] . ' ' . $row['nome']); ?></strong>
                                <?php if (!$row['attivo']): ?>
                                    <span class="badge bg-secondary">Non attivo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['luogo']); ?></td>
                            <td><small><?php echo htmlspecialchars($row['via']); ?></small></td>
                            <td>
                                <small>
                                    📧 <?php echo htmlspecialchars($row['email']); ?><br>
                                    📞 <?php echo htmlspecialchars($row['telefono']); ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($row['dimensioni']): ?>
                                    <small><?php echo htmlspecialchars($row['dimensioni']); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php echo format_date_from_ymd($row['data_iscrizione']); ?><br>
                                    <span class="text-muted">(<?php echo $giorni_attesa; ?> gg)</span>
                                </small>
                            </td>
                            <td>
                                <?php if ($row['ultima_verifica']): ?>
                                    <small class="text-success">
                                        ✓ <?php echo format_date_from_ymd($row['ultima_verifica']); ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-warning">Mai verificato</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <a href="/app/waiting/edit.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">Modifica</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted mb-0">Nessuna canoa in lista d'attesa</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>