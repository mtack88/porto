<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$title = 'Rastrelliera — griglia 3×6';
$active = 'rack';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';

// Ottieni dati marina
$marina = get_marina_by_code('RAST');
if (!$marina) {
    die('<div class="container py-4"><div class="alert alert-danger">Marina RAST non trovata</div></div>');
}

// Ottieni tutti gli slot
$all_slots = get_slots_with_current_assignment((int)$marina['id']);

// Crea mappa numero -> dati slot
$slots_map = array();
foreach ($all_slots as $slot) {
    $numero = (int)$slot['numero_esterno'];
    $slots_map[$numero] = $slot;
}
?>

<div class="container py-4">
    <h1 class="h4 mb-3">Rastrelliera — Vista griglia 3 colonne × 6 righe</h1>
    
    <!-- Griglia usando CSS Grid -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; max-width: 900px; margin: 0 auto;">
        <?php
        // IMPORTANTE: Genera la griglia RIGA per RIGA ma con numerazione per COLONNA
        // Quindi la prima riga avrà: 1, 7, 13
        // La seconda riga avrà: 2, 8, 14
        // E così via...
        
        for ($row = 0; $row < 6; $row++) {
            for ($col = 0; $col < 3; $col++) {
                // Formula per numerazione per colonna:
                // Colonna 0: 1-6
                // Colonna 1: 7-12
                // Colonna 2: 13-18
                $posto_numero = ($col * 6) + ($row + 1);
                
                // Verifica se il posto esiste nel database
                $posto_dati = isset($slots_map[$posto_numero]) ? $slots_map[$posto_numero] : null;
                
                // Determina stato e colore
                $stato = $posto_dati ? $posto_dati['stato'] : 'Manutenzione';
                $proprietario = $posto_dati ? ($posto_dati['proprietario'] ?? '') : '';
                $slot_id = $posto_dati ? $posto_dati['id'] : 0;
                
                // Colori per stato
                $colori = array(
                    'Libero' => '#d4edda',
                    'Occupato' => '#f8d7da',
                    'Riservato' => '#fff3cd',
                    'Manutenzione' => '#e2e3e5'
                );
                $colore_sfondo = isset($colori[$stato]) ? $colori[$stato] : '#e2e3e5';
                
                // Bordi per stato
                $bordi = array(
                    'Libero' => '#28a745',
                    'Occupato' => '#dc3545',
                    'Riservato' => '#ffc107',
                    'Manutenzione' => '#6c757d'
                );
                $colore_bordo = isset($bordi[$stato]) ? $bordi[$stato] : '#6c757d';
                ?>
                
                <div style="
                    background: <?php echo $colore_sfondo; ?>;
                    border: 2px solid <?php echo $colore_bordo; ?>;
                    border-radius: 8px;
                    padding: 15px;
                    text-align: center;
                    min-height: 130px;
                    cursor: pointer;
                    transition: all 0.2s;
                    position: relative;
                    "
                    onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 5px 15px rgba(0,0,0,0.2)';"
                    onmouseout="this.style.transform=''; this.style.boxShadow='';"
                    <?php if ($slot_id > 0): ?>
                    onclick="window.location.href='/app/slots/view.php?id=<?php echo $slot_id; ?>'"
                    <?php endif; ?>
                    >
                    
                    <div style="font-size: 1.3rem; font-weight: bold; color: #333; margin-bottom: 8px;">
                        R<?php echo $posto_numero; ?>
                    </div>
                    
                    <?php if ($stato == 'Libero'): ?>
                        <span style="background: #28a745; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;">
                            LIBERO
                        </span>
                    <?php elseif ($stato == 'Occupato'): ?>
                        <span style="background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;">
                            OCCUPATO
                        </span>
                    <?php elseif ($stato == 'Riservato'): ?>
                        <span style="background: #ffc107; color: #333; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;">
                            RISERVATO
                        </span>
                    <?php else: ?>
                        <span style="background: #6c757d; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;">
                            N/D
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($proprietario): ?>
                        <div style="margin-top: 8px; font-size: 0.85rem; color: #495057; word-break: break-word;">
                            <?php echo htmlspecialchars($proprietario); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php
            }
        }
        ?>
    </div>
    
    <!-- Schema visivo per chiarezza -->
    <div class="mt-4">
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Schema numerazione posti (per colonna)</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <strong>Colonna 1</strong><br>
                        R1 → R6
                    </div>
                    <div class="col-4">
                        <strong>Colonna 2</strong><br>
                        R7 → R12
                    </div>
                    <div class="col-4">
                        <strong>Colonna 3</strong><br>
                        R13 → R18
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Legenda e azioni -->
    <div class="mt-4">
        <div class="alert alert-info">
            <h6 class="alert-heading">Legenda stati:</h6>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <span class="badge" style="background: #28a745; color: white;">Libero</span>
                <span class="badge" style="background: #dc3545; color: white;">Occupato</span>
                <span class="badge" style="background: #ffc107; color: #333;">Riservato</span>
                <span class="badge" style="background: #6c757d; color: white;">Non disponibile</span>
            </div>
        </div>
        
        <div class="d-flex gap-2">
            <a href="/app/slots/list.php?marina=RAST" class="btn btn-primary">
                Visualizza tabella dettagliata
            </a>
            <a href="/app/dashboard.php" class="btn btn-outline-secondary">
                Torna alla dashboard
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>