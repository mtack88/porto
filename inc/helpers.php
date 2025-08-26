<?php
declare(strict_types=1);

function e(?string $str): string { return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url): void { header("Location: $url"); exit; }

// Flash
function set_flash(string $key, string $value): void { $_SESSION['flash'][$key] = $value; }
function get_flash(string $key): ?string {
  if (!empty($_SESSION['flash'][$key])) { $v = $_SESSION['flash'][$key]; unset($_SESSION['flash'][$key]); return $v; }
  return null;
}

// Date helpers
function parse_date_to_ymd(string $s): ?string {
  $s = trim($s);
  if ($s === '') return null;
  $parts = explode('/', $s);
  if (count($parts) !== 3) return null;
  [$d,$m,$y] = array_map('intval', $parts);
  if (!$d || !$m || !$y) return null;
  return sprintf('%04d-%02d-%02d', $y, $m, $d);
}
function format_date_from_ymd(?string $ymd): ?string {
  if (!$ymd) return null;
  $parts = explode('-', $ymd);
  if (count($parts) !== 3) return null;
  [$y,$m,$d] = array_map('intval', $parts);
  if (!$y) return null;
  return sprintf('%02d/%02d/%04d', $d, $m, $y);
}

function status_badge(string $stato): string {
  $cls = match($stato){
    'Libero'=>'badge-libero',
    'Occupato'=>'badge-occupato',
    'Riservato'=>'badge-riservato',
    'Manutenzione'=>'badge-manutenzione',
    default=>'bg-secondary'
  };
  return '<span class="badge badge-stato '.$cls.'">'.e($stato).'</span>';
}

// DB access helpers
function get_all_marinas(): array {
  global $pdo; return $pdo->query("SELECT * FROM marinas ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
}
function get_marina_by_code(string $code): ?array {
  global $pdo; $s=$pdo->prepare("SELECT * FROM marinas WHERE code=:c LIMIT 1"); $s->execute([':c'=>$code]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r?:null;
}
function get_marina_by_id(int $id): ?array {
  global $pdo; $s=$pdo->prepare("SELECT * FROM marinas WHERE id=:c LIMIT 1"); $s->execute([':c'=>$id]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r?:null;
}
function get_slot_by_id(int $id): ?array {
  global $pdo; $s=$pdo->prepare("SELECT * FROM slots WHERE id=:id LIMIT 1"); $s->execute([':id'=>$id]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r?:null;
}
function get_current_assignment(int $slot_id): ?array {
    global $pdo;
    $s = $pdo->prepare("
        SELECT * FROM assignments 
        WHERE slot_id = :id 
        AND (data_fine IS NULL OR data_fine = '0000-00-00' OR data_fine = '')
        ORDER BY id DESC 
        LIMIT 1
    ");
    $s->execute([':id' => $slot_id]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}
function get_assignments_history(int $slot_id): array {
  global $pdo; $s=$pdo->prepare("SELECT * FROM assignments WHERE slot_id=:id ORDER BY data_inizio DESC, id DESC"); $s->execute([':id'=>$slot_id]); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function get_slots_with_current_assignment(int $marina_id): array {
  global $pdo;
  $sql = "SELECT s.*, a.proprietario, a.targa FROM slots s
          LEFT JOIN assignments a ON a.slot_id = s.id AND a.data_fine IS NULL
          WHERE s.marina_id=:mid AND s.deleted_at IS NULL
          ORDER BY s.numero_esterno";
  $s=$pdo->prepare($sql); $s->execute([':mid'=>$marina_id]); return $s->fetchAll(PDO::FETCH_ASSOC);
}

// CSV export
function export_slots_csv(string $marinaCode, string $stato, string $tipo, string $num, string $num_int, string $q, bool $show_deleted): void {
  global $pdo;
  $params = [];
  $sql = "SELECT m.code as pontile, s.numero_esterno as numero, s.numero_interno, s.tipo, s.stato, a.proprietario, a.targa, a.email, a.telefono
          FROM slots s
          JOIN marinas m ON m.id = s.marina_id
          LEFT JOIN assignments a ON a.slot_id = s.id AND a.data_fine IS NULL
          WHERE 1=1 ";
  if (!$show_deleted) $sql .= " AND s.deleted_at IS NULL ";
  if ($marinaCode) { $sql .= " AND m.code = :code "; $params[':code']=$marinaCode; }
  if ($stato) { $sql .= " AND s.stato = :st "; $params[':st']=$stato; }
  if ($tipo) { $sql .= " AND s.tipo = :tp "; $params[':tp']=$tipo; }
  if ($num) { $sql .= " AND s.numero_esterno = :num "; $params[':num']=(int)$num; }
  if ($num_int) { $sql .= " AND s.numero_interno LIKE :nint "; $params[':nint'] = '%'.$num_int.'%'; }
  if ($q) { $sql .= " AND (a.proprietario LIKE :q OR a.targa LIKE :q) "; $params[':q']='%'.$q.'%'; }
  $sql .= " ORDER BY m.code, s.numero_esterno";

  $stmt = $pdo->prepare($sql); $stmt->execute($params);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="situazione_posti.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['pontile','numero','numero_interno','tipo','stato','proprietario','targa','email','telefono'], ';');
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($out, $r, ';'); }
  fclose($out);
}

function export_history_csv(string $marinaCode, string $stato, string $dal, string $al, int $slot_id): void {
  global $pdo;
  $params=[];
  $sql="SELECT m.code as pontile, s.numero_esterno as numero, a.stato, a.proprietario, a.targa, a.email, a.telefono, a.data_inizio, a.data_fine
        FROM assignments a
        JOIN slots s ON s.id=a.slot_id
        JOIN marinas m ON m.id=s.marina_id
        WHERE 1=1 ";
  if ($marinaCode) { $sql.=" AND m.code=:c "; $params[':c']=$marinaCode; }
  if ($stato) { $sql.=" AND a.stato=:s "; $params[':s']=$stato; }
  if ($slot_id) { $sql.=" AND a.slot_id=:sid "; $params[':sid']=$slot_id; }
  if ($dal) { $sql.=" AND a.data_inizio>=:dal "; $params[':dal']=$dal; }
  if ($al) { $sql.=" AND (a.data_fine IS NULL OR a.data_fine<=:al) "; $params[':al']=$al; }
  $sql.=" ORDER BY a.data_inizio DESC";
  $stmt=$pdo->prepare($sql); $stmt->execute($params);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="storico_assegnazioni.csv"');
  $out=fopen('php://output','w');
  fputcsv($out, ['pontile','numero','stato','proprietario','targa','email','telefono','data_inizio','data_fine'], ';');
  while($r=$stmt->fetch(PDO::FETCH_ASSOC)){
    $r['data_inizio']=format_date_from_ymd($r['data_inizio']);
    $r['data_fine']=format_date_from_ymd($r['data_fine']);
    fputcsv($out, $r, ';');
  }
  fclose($out);
}

// Event log
function log_event(string $entity, int $entity_id, string $action, array $details): void {
  global $pdo;
  $stmt = $pdo->prepare("INSERT INTO event_log (user_id, entity, entity_id, action, details, created_at) VALUES (:uid,:ent,:eid,:act,:det,NOW())");
  $stmt->execute([
    ':uid'=>current_user_id(),
    ':ent'=>$entity,
    ':eid'=>$entity_id,
    ':act'=>$action,
    ':det'=>json_encode($details, JSON_UNESCAPED_UNICODE)
  ]);
}

/* ============ Occupanti ============ */
function get_occupants(string $q=''): array {
  global $pdo; $params=[]; $sql="SELECT * FROM occupants WHERE deleted_at IS NULL";
  if ($q!==''){ $sql.=" AND (proprietario LIKE :q OR targa LIKE :q OR email LIKE :q)"; $params[':q']='%'.$q.'%'; }
  $sql.=" ORDER BY proprietario"; $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function get_occupant_by_id(int $id): ?array {
  global $pdo; $s=$pdo->prepare("SELECT * FROM occupants WHERE id=:id LIMIT 1"); $s->execute([':id'=>$id]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r?:null;
}
function create_occupant(array $d): int {
  global $pdo; $s=$pdo->prepare("INSERT INTO occupants (proprietario,targa,email,telefono,note,created_at) VALUES (:p,:t,:e,:tel,:n,NOW())");
  $s->execute([':p'=>$d['proprietario'],':t'=>$d['targa']?:null,':e'=>$d['email']?:null,':tel'=>$d['telefono']?:null,':n'=>$d['note']?:null]);
  $id=(int)$pdo->lastInsertId(); log_event('occupant',$id,'create',$d); return $id;
}
function update_occupant(int $id, array $d): void {
  global $pdo; $s=$pdo->prepare("UPDATE occupants SET proprietario=:p,targa=:t,email=:e,telefono=:tel,note=:n,updated_at=NOW() WHERE id=:id");
  $s->execute([':p'=>$d['proprietario'],':t'=>$d['targa']?:null,':e'=>$d['email']?:null,':tel'=>$d['telefono']?:null,':n'=>$d['note']?:null,':id'=>$id]);
  log_event('occupant',$id,'update',$d);
}
function soft_delete_occupant(int $id): void {
  global $pdo; $pdo->prepare("UPDATE occupants SET deleted_at=NOW() WHERE id=:id AND deleted_at IS NULL")->execute([':id'=>$id]);
  log_event('occupant',$id,'delete',['soft'=>true]);
}
function get_occupants_for_select(): array {
  global $pdo; return $pdo->query("SELECT id, proprietario, targa, email, telefono FROM occupants WHERE deleted_at IS NULL ORDER BY proprietario LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
}

/* ============ Hotfix: prossimo numero per singolo pontile ============ */
function next_external_number(int $marina_id): int {
  global $pdo;
  $stmt = $pdo->prepare("SELECT COALESCE(MAX(numero_esterno),0)+1 FROM slots WHERE marina_id = :mid");
  $stmt->execute([':mid'=>$marina_id]);
  $next = (int)$stmt->fetchColumn();
  return max(1, $next);
}