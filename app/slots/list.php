<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$title = 'Elenco posti';
$active = 'slots_list';

// Filtri
$marinaCode = $_GET['marina'] ?? '';
$stato = $_GET['stato'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$num = trim($_GET['numero'] ?? '');
$num_int = trim($_GET['numero_interno'] ?? '');
$q = trim($_GET['q'] ?? '');
$show_deleted = ($_GET['cestino'] ?? '') === '1';

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === '1') {
    export_slots_csv($marinaCode, $stato, $tipo, $num, $num_int, $q, $show_deleted);
    exit;
}

// Query principale - MODIFICATA per prendere l'ULTIMA assegnazione
$sql = "SELECT 
        s.id,
        s.numero_esterno,
        s.numero_interno,
        s.tipo,
        s.stato,
        s.note,
        s.deleted_at,
        m.code as marina_code,
        m.name as marina_name,
        latest_a.proprietario,
        latest_a.targa,
        latest_a.email,
        latest_a.telefono,
        latest_a.data_inizio,
        latest_a.data_fine
    FROM slots s
    INNER JOIN marinas m ON m.id = s.marina_id
    LEFT JOIN (
        SELECT a1.* 
        FROM assignments a1
        INNER JOIN (
            SELECT slot_id, MAX(id) as max_id
            FROM assignments
            GROUP BY slot_id
        ) a2 ON a1.slot_id = a2.slot_id AND a1.id = a2.max_id
    ) latest_a ON latest_a.slot_id = s.id
    WHERE 1=1 ";

$params = array();

// Applica filtri
if (!$show_deleted) {
    $sql .= " AND s.deleted_at IS NULL ";
}

if ($marinaCode !== '') {
    $sql .= " AND m.code = :marina_code ";
    $params[':marina_code'] = $marinaCode;
}

if ($stato !== '') {
    $sql .= " AND s.stato = :stato ";
    $params[':stato'] = $stato;
}

if ($tipo !== '') {
    $sql .= " AND s.tipo = :tipo ";
    $params[':tipo'] = $tipo;
}

if ($num !== '') {
    $sql .= " AND s.numero_esterno = :numero ";
    $params[':numero'] = (int)$num;
}

if ($num_int !== '') {
    $sql .= " AND s.numero_interno LIKE :num_int ";
    $params[':num_int'] = '%' . $num_int . '%';
}

if ($q !== '') {
    $sql .= " AND (latest_a.proprietario LIKE :q OR latest_a.targa LIKE :q2 OR latest_a.email LIKE :q3) ";
    $params[':q'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}

$sql .= " ORDER BY m.code ASC, CAST(s.numero_esterno AS UNSIGNED) ASC ";

// Esegui query
$rows = array();
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Errore database: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Liste per filtri
$marinas = get_all_marinas();

// Conta totali
$total_slots = 0;
try {
    $stmt_total = $pdo->query("SELECT COUNT(*) FROM slots WHERE deleted_at IS NULL");
    $total_slots = (int)$stmt_total->fetchColumn();
} catch (PDOException $e) {
    // Ignora
}

include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Elenco Posti</h1>
            <small class="text-muted">
                Visualizzati: <?php echo count($rows); ?> posti
                <?php if ($total_slots > 0): ?>
                    su <?php echo $total_slots; ?> totali
                <?php endif; ?>
                <?php if (!empty($_GET) && count($_GET) > 0): ?>
                    (con filtri applicati)
                <?php endif; ?>
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="/app/slots/create.php" class="btn btn-success btn-sm">
                + Aggiungi posto
            </a>
            <?php if (count($rows) > 0): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 1])); ?>" 
               class="btn btn-outline-primary btn-sm">
                Export CSV
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtri -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="/app/slots/list.php">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label small">Pontile</label>
                        <select name="marina" class="form-select form-select-sm">
                            <option value="">-- Tutti --</option>
                            <?php foreach ($marinas as $m): ?>
                            <option value="<?php echo $m['code']; ?>" 
                                    <?php if ($marinaCode === $m['code']) echo 'selected'; ?>>
                                <?php echo $m['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Stato</label>
                        <select name="stato" class="form-select form-select-sm">
                            <option value="">-- Tutti --</option>
                            <option value="Libero" <?php if ($stato === 'Libero') echo 'selected'; ?>>Libero</option>
                            <option value="Occupato" <?php if ($stato === 'Occupato') echo 'selected'; ?>>Occupato</option>
                            <option value="Riservato" <?php if ($stato === 'Riservato') echo 'selected'; ?>>Riservato</option>
                            <option value="Manutenzione" <?php if ($stato === 'Manutenzione') echo 'selected'; ?>>Manutenzione</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Tipo</label>
                        <select name="tipo" class="form-select form-select-sm">
                            <option value="">-- Tutti --</option>
                            <option value="carrello" <?php if ($tipo === 'carrello') echo 'selected'; ?>>Carrello</option>
                            <option value="fune" <?php if ($tipo === 'fune') echo 'selected'; ?>>Fune</option>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label small">Numero</label>
                        <input type="text" name="numero" class="form-control form-control-sm" 
                               value="<?php echo $num; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Ricerca</label>
                        <input type="text" name="q" class="form-control form-control-sm" 
                               value="<?php echo $q; ?>" 
                               placeholder="Nome, targa...">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm me-2">
                            Filtra
                        </button>
                        <a href="/app/slots/list.php" class="btn btn-outline-secondary btn-sm">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella risultati -->
    <?php if (count($rows) === 0): ?>
        
        <div class="alert alert-info">
            <h5>Nessun posto trovato</h5>
            <p>Prova a modificare i filtri o <a href="/app/slots/list.php">mostra tutti i posti</a></p>
        </div>
        
    <?php else: ?>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Pontile</th>
                        <th>N°</th>
                        <th>Interno</th>
                        <th>Tipo</th>
                        <th>Stato</th>
                        <th>Proprietario</th>
                        <th>Targa</th>
                        <th>Contatti</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): 
                        // Converti tutto in stringhe per evitare errori
                        $id = (int)($row['id'] ?? 0);
                        $numero = (string)($row['numero_esterno'] ?? '');
                        $numero_interno = (string)($row['numero_interno'] ?? '');
                        $marina_name = (string)($row['marina_name'] ?? '');
                        $tipo_posto = (string)($row['tipo'] ?? '');
                        $stato_posto = (string)($row['stato'] ?? 'Libero');
                        
                        // Dati assegnazione - solo se lo stato NON è Libero
                        $proprietario = '';
                        $targa = '';
                        $email = '';
                        $telefono = '';
                        
                        if ($stato_posto !== 'Libero') {
                            $proprietario = (string)($row['proprietario'] ?? '');
                            $targa = (string)($row['targa'] ?? '');
                            $email = (string)($row['email'] ?? '');
                            $telefono = (string)($row['telefono'] ?? '');
                        }
                        
                        $deleted = !empty($row['deleted_at']);
                    ?>
                    <tr class="<?php echo $deleted ? 'table-warning' : ''; ?>">
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($marina_name); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($numero); ?></strong>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($numero_interno ?: '-'); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($tipo_posto ?: '-'); ?>
                        </td>
                        <td>
                            <?php echo status_badge($stato_posto); ?>
                        </td>
                        <td>
                            <?php if ($proprietario): ?>
                                <strong><?php echo htmlspecialchars($proprietario); ?></strong>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($targa ?: '-'); ?>
                        </td>
                        <td>
                            <?php if ($email || $telefono): ?>
                                <?php if ($email): ?>
                                    <small><?php echo htmlspecialchars($email); ?></small>
                                <?php endif; ?>
                                <?php if ($email && $telefono): ?><br><?php endif; ?>
                                <?php if ($telefono): ?>
                                    <small><?php echo htmlspecialchars($telefono); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="/app/slots/view.php?id=<?php echo $id; ?>">
                                Vedi
                            </a>
                            <?php if ($stato_posto === 'Libero' && !$deleted): ?>
                            <a class="btn btn-sm btn-success" href="/app/assignments/create.php?slot_id=<?php echo $id; ?>">
                                Assegna
                            </a>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-secondary" href="/app/slots/edit.php?id=<?php echo $id; ?>">
                                Modifica
                            </a>
                            <?php if (!$deleted): ?>
                            <a class="btn btn-sm btn-outline-danger" 
                               href="/app/slots/edit.php?id=<?php echo $id; ?>&delete=1" 
                               onclick="return confirm('Eliminare questo posto?')">
                                Elimina
                            </a>
                            <?php else: ?>
                            <a class="btn btn-sm btn-outline-success" 
                               href="/app/slots/edit.php?id=<?php echo $id; ?>&restore=1"
                               onclick="return confirm('Ripristinare questo posto?')">
                                Ripristina
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>