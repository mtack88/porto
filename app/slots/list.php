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

// Export CSV?
if (isset($_GET['export']) && $_GET['export'] === '1') {
    export_slots_csv($marinaCode, $stato, $tipo, $num, $num_int, $q, $show_deleted);
    exit;
}

// Query
$params = [];
$sql = "SELECT s.*, m.code as marina_code, m.name as marina_name,
          a.proprietario, a.targa
        FROM slots s
        JOIN marinas m ON m.id = s.marina_id
        LEFT JOIN assignments a ON a.slot_id = s.id AND a.data_fine IS NULL
        WHERE 1=1 ";
if (!$show_deleted) $sql .= " AND s.deleted_at IS NULL ";
if ($marinaCode) { $sql .= " AND m.code = :code "; $params[':code'] = $marinaCode; }
if ($stato) { $sql .= " AND s.stato = :stato "; $params[':stato'] = $stato; }
if ($tipo) { $sql .= " AND (s.tipo = :tipo) "; $params[':tipo'] = $tipo; }
if ($num !== '') { $sql .= " AND s.numero_esterno = :num "; $params[':num'] = (int)$num; }
if ($num_int !== '') { $sql .= " AND s.numero_interno LIKE :nint "; $params[':nint'] = '%'.$num_int.'%'; }
if ($q !== '') {
  $sql .= " AND (a.proprietario LIKE :q OR a.targa LIKE :q) ";
  $params[':q'] = '%'.$q.'%';
}
$sql .= " ORDER BY m.code, s.numero_esterno ";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For select options
$marinas = get_all_marinas();
$active = 'slots_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Posti — elenco</h1>
    <div class="d-flex gap-2">
      <?php if (in_array($marinaCode, ['BOLA','RITTER'], true)): ?>
        <a class="btn btn-success btn-sm" href="/app/slots/create.php?marina=<?= e($marinaCode) ?>">Aggiungi posto</a>
      <?php endif; ?>
      <a class="btn btn-outline-secondary btn-sm" href="/app/slots/import.php">Import CSV</a>
      <a class="btn btn-outline-primary btn-sm" href="?<?= http_build_query(array_merge($_GET,['export'=>1])) ?>">Esporta CSV</a>
    </div>
  </div>

  <form method="get" class="card p-3 mb-3">
    <div class="row g-2">
      <div class="col-md-2">
        <label class="form-label">Pontile</label>
        <select name="marina" class="form-select">
          <option value="">Tutti</option>
          <?php foreach ($marinas as $m): ?>
            <option value="<?= e($m['code']) ?>" <?= $marinaCode===$m['code']?'selected':'' ?>>
              <?= e($m['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Stato</label>
        <select name="stato" class="form-select">
          <option value="">Tutti</option>
          <?php foreach (['Libero','Occupato','Riservato','Manutenzione'] as $s): ?>
            <option value="<?= $s ?>" <?= $stato===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select">
          <option value="">Tutti</option>
          <?php foreach (['carrello','fune'] as $t): ?>
            <option value="<?= $t ?>" <?= $tipo===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Numero</label>
        <input type="number" name="numero" value="<?= e($num) ?>" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Numero interno</label>
        <input type="text" name="numero_interno" value="<?= e($num_int) ?>" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">Ricerca</label>
        <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Proprietario / Targa">
      </div>
    </div>
    <div class="form-check mt-2">
      <input class="form-check-input" type="checkbox" value="1" id="cestino" name="cestino" <?= $show_deleted?'checked':'' ?>>
      <label class="form-check-label" for="cestino">Mostra cestino (posti eliminati)</label>
    </div>
    <div class="mt-3">
      <button class="btn btn-primary">Filtra</button>
      <a class="btn btn-outline-secondary" href="/app/slots/list.php">Reimposta</a>
    </div>
  </form>

  <div class="table-responsive table-sticky">
    <table class="table table-hover align-middle" id="tbl">
      <thead>
        <tr>
          <th>Pontile</th>
          <th>Numero</th>
          <th>Numero interno</th>
          <th>Tipo</th>
          <th>Stato</th>
          <th>Proprietario</th>
          <th>Targa</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="<?= $r['deleted_at'] ? 'table-danger' : '' ?>">
            <td><?= e($r['marina_name']) ?></td>
            <td><?= e($r['numero_esterno']) ?></td>
            <td><?= e($r['numero_interno']) ?></td>
            <td><?= e($r['tipo']) ?></td>
            <td><?= status_badge($r['stato']) ?></td>
            <td><?= e($r['proprietario']) ?></td>
            <td><?= e($r['targa']) ?></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="/app/slots/view.php?id=<?= (int)$r['id'] ?>">Vedi</a>
              <a class="btn btn-sm btn-outline-secondary" href="/app/slots/edit.php?id=<?= (int)$r['id'] ?>">Modifica</a>
              <?php if (!$r['deleted_at']): ?>
                <a class="btn btn-sm btn-outline-danger" href="/app/slots/edit.php?id=<?= (int)$r['id'] ?>&delete=1" data-confirm="Confermi l'eliminazione del posto?">Elimina</a>
              <?php else: ?>
                <a class="btn btn-sm btn-outline-success" href="/app/slots/edit.php?id=<?= (int)$r['id'] ?>&restore=1" data-confirm="Ripristinare questo posto?">Ripristina</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>