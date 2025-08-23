<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$title = 'Storico assegnazioni';
$active = 'history';

$marinaCode = $_GET['marina'] ?? '';
$stato = $_GET['stato'] ?? '';
$dal = parse_date_to_ymd($_GET['dal'] ?? '') ?: '';
$al = parse_date_to_ymd($_GET['al'] ?? '') ?: '';
$slot_id = (int)($_GET['slot_id'] ?? 0);

// Export CSV?
if (isset($_GET['export']) && $_GET['export'] === '1') {
  export_history_csv($marinaCode, $stato, $dal, $al, $slot_id);
  exit;
}

$params = [];
$sql = "SELECT a.*, s.numero_esterno, m.name as marina_name, m.code as marina_code
        FROM assignments a
        JOIN slots s ON s.id = a.slot_id
        JOIN marinas m ON m.id = s.marina_id
        WHERE 1=1 ";
if ($marinaCode) { $sql .= " AND m.code = :code "; $params[':code'] = $marinaCode; }
if ($stato) { $sql .= " AND a.stato = :st "; $params[':st'] = $stato; }
if ($slot_id) { $sql .= " AND a.slot_id = :sid "; $params[':sid'] = $slot_id; }
if ($dal) { $sql .= " AND a.data_inizio >= :dal "; $params[':dal'] = $dal; }
if ($al) { $sql .= " AND (a.data_fine IS NULL OR a.data_fine <= :al) "; $params[':al'] = $al; }
$sql .= " ORDER BY a.data_inizio DESC, a.id DESC LIMIT 1000";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$marinas = get_all_marinas();

include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Storico assegnazioni</h1>
    <a class="btn btn-outline-primary btn-sm" href="?<?= http_build_query(array_merge($_GET,['export'=>1])) ?>">Esporta CSV storico</a>
  </div>
  <form method="get" class="card p-3 mb-3">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Pontile</label>
        <select name="marina" class="form-select">
          <option value="">Tutti</option>
          <?php foreach ($marinas as $m): ?>
            <option value="<?= e($m['code']) ?>" <?= $marinaCode===$m['code']?'selected':'' ?>><?= e($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Stato</label>
        <select name="stato" class="form-select">
          <option value="">Tutti</option>
          <?php foreach (['Libero','Occupato','Riservato','Manutenzione'] as $s): ?>
            <option value="<?= $s ?>" <?= $stato===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Dal</label>
        <input type="text" name="dal" class="form-control" placeholder="gg/mm/aaaa" value="<?= e($_GET['dal'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Al</label>
        <input type="text" name="al" class="form-control" placeholder="gg/mm/aaaa" value="<?= e($_GET['al'] ?? '') ?>">
      </div>
    </div>
    <div class="mt-2">
      <button class="btn btn-primary">Filtra</button>
      <a class="btn btn-outline-secondary" href="/app/history/list.php">Reimposta</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead>
        <tr>
          <th>Pontile</th>
          <th>Posto</th>
          <th>Stato</th>
          <th>Proprietario</th>
          <th>Targa</th>
          <th>Dal</th>
          <th>Al</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['marina_name']) ?></td>
            <td><?= e($r['numero_esterno']) ?></td>
            <td><?= status_badge($r['stato']) ?></td>
            <td><?= e($r['proprietario']) ?></td>
            <td><?= e($r['targa']) ?></td>
            <td><?= e(format_date_from_ymd($r['data_inizio'])) ?></td>
            <td><?= e(format_date_from_ymd($r['data_fine'])) ?: '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>