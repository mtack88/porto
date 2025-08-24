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
        // Genera 18 posti (3 colonne x 6 righe)
        // Ordine: colonna 1 (1-6), colonna 2 (7-12), colonna 3 (13-18)
        for ($col = 0; $col < 3; $col++) {
            for ($row = 1; $row <= 6; $row++) {
                $posto_numero = ($col * 6) + $row;
                
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