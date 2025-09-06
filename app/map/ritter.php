<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

// Modalità edit
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';

// Carica posti del Porto W. Ritter
$stmt = $pdo->query("
    SELECT s.*, m.name as marina_name, a.proprietario,
           sc.north, sc.south, sc.east, sc.west, sc.rotation,
           sc.center_lat, sc.center_lng, sc.width, sc.height
    FROM slots s
    INNER JOIN marinas m ON m.id = s.marina_id
    LEFT JOIN assignments a ON a.slot_id = s.id AND a.data_fine IS NULL
    LEFT JOIN slot_coordinates sc ON sc.slot_id = s.id
    WHERE s.deleted_at IS NULL 
    AND s.marina_id = 2
    ORDER BY CAST(s.numero_esterno AS UNSIGNED)
");
$slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

$slotsWithCoords = [];
$slotsWithoutCoords = [];

foreach ($slots as $slot) {
    if ($slot['north'] !== null) {
        $slotsWithCoords[] = $slot;
    } else {
        $slotsWithoutCoords[] = $slot;
    }
}

$title = 'Mappa Porto W. Ritter';
$active = 'map';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<style>
#map {
    height: 700px;
    width: 100%;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.slot-info {
    min-width: 250px;
}

.legend {
    background: white;
    padding: 10px;
    margin: 10px;
    border: 2px solid #333;
    border-radius: 5px;
    font-family: Arial, sans-serif;
}

.legend h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    font-weight: bold;
}

.legend-item {
    margin: 5px 0;
    font-size: 12px;
    display: flex;
    align-items: center;
}

.legend-color {
    display: inline-block;
    width: 20px;
    height: 14px;
    margin-right: 8px;
    border: 1px solid #333;
    border-radius: 2px;
}

.drawing-controls {
    background: #fff3cd;
    border: 2px solid #ffc107;
    padding: 15px;
    margin: 10px;
    border-radius: 5px;
    max-width: 350px;
}

.slot-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 5px;
    margin: 10px 0;
}

.rotation-control {
    margin: 10px 0;
    padding: 10px;
    background: #f0f0f0;
    border-radius: 5px;
}

.rotation-slider {
    width: 100%;
}
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h4 mb-3">
                Mappa Interattiva - Porto W. Ritter
                <?php if ($editMode): ?>
                    <span class="badge bg-warning">MODALITÀ DISEGNO</span>
                <?php endif; ?>
            </h1>
            
            <div class="row mb-3">
                <div class="col-md-8">
                    <?php if (!$editMode): ?>
                        <div class="btn-group" role="group">
                            <button class="btn btn-outline-success btn-sm" onclick="filterByStatus('all')">Tutti</button>
                            <button class="btn btn-outline-success btn-sm" onclick="filterByStatus('Libero')">Liberi</button>
                            <button class="btn btn-outline-danger btn-sm" onclick="filterByStatus('Occupato')">Occupati</button>
                        </div>
                        <a href="?edit=1" class="btn btn-warning btn-sm ms-3">🖊️ Modalità Disegno</a>
                    <?php else: ?>
                        <a href="/app/map/ritter.php" class="btn btn-primary btn-sm">← Esci da modalità disegno</a>
                    <?php endif; ?>
                    <a href="/app/map/index.php" class="btn btn-outline-primary btn-sm ms-2">Mappa Porto Bola</a>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-info">Totale: <?php echo count($slots); ?></span>
                    <span class="badge bg-success">Posizionati: <?php echo count($slotsWithCoords); ?></span>
                    <span class="badge bg-warning">Da posizionare: <?php echo count($slotsWithoutCoords); ?></span>
                </div>
            </div>
            
            <div id="map"></div>
        </div>
    </div>
</div>

<script>
// Dati dal PHP
var slotsWithCoords = <?php echo json_encode($slotsWithCoords); ?>;
var slotsWithoutCoords = <?php echo json_encode($slotsWithoutCoords); ?>;
var editMode = <?php echo $editMode ? 'true' : 'false'; ?>;

// Centro del porto
var PORTO_CENTER = {lat: 45.953220, lng: 8.955200};

// Perimetro del porto
var RITTER_PERIMETER = [
    {lat: 45.953163, lng: 8.954926},
    {lat: 45.953216, lng: 8.955001},
    {lat: 45.953250, lng: 8.955075},
    {lat: 45.953286, lng: 8.955163},
    {lat: 45.953309, lng: 8.955269},
    {lat: 45.953319, lng: 8.955368},
    {lat: 45.953314, lng: 8.955493},
    {lat: 45.953268, lng: 8.955499},
    {lat: 45.953269, lng: 8.955381},
    {lat: 45.953257, lng: 8.955284},
    {lat: 45.953233, lng: 8.955182},
    {lat: 45.953197, lng: 8.955079},
    {lat: 45.953127, lng: 8.954981}
];

var map;
var drawingManager;
var slotShapes = [];
var infoWindow;
var selectedSlot = null;
var currentRectangle = null;
var currentPolygon = null;
var currentRotation = 0;

function initMap() {
    map = new google.maps.Map(document.getElementById('map'), {
        center: PORTO_CENTER,
        zoom: 19,
        mapTypeId: 'satellite',
        tilt: 0,
        rotateControl: true,
        fullscreenControl: true
    });
    
    infoWindow = new google.maps.InfoWindow();
    
    drawPortoPerimeter();
    loadExistingSlots();
    
    if (editMode) {
        initDrawingMode();
        addDrawingControls();
    }
    
    addLegend();
}

function drawPortoPerimeter() {
    new google.maps.Polygon({
        paths: RITTER_PERIMETER,
        strokeColor: '#FFFFFF',
        strokeOpacity: 1,
        strokeWeight: 2,
        fillColor: '#4A90E2',
        fillOpacity: 0.1,
        map: map
    });
}

function loadExistingSlots() {
    slotsWithCoords.forEach(function(slot) {
        if (slot.rotation && slot.center_lat && slot.center_lng) {
            var center = {
                lat: parseFloat(slot.center_lat),
                lng: parseFloat(slot.center_lng)
            };
            var width = parseFloat(slot.width) || 0.00003;
            var height = parseFloat(slot.height) || 0.00002;
            var rotation = parseFloat(slot.rotation) || 0;
            
            var vertices = calculateRotatedRectangle(center, width, height, rotation);
            var polygon = createSlotPolygon(vertices, slot, true);
            slotShapes.push(polygon);
        } else {
            var bounds = {
                north: parseFloat(slot.north),
                south: parseFloat(slot.south),
                east: parseFloat(slot.east),
                west: parseFloat(slot.west)
            };
            
            var rect = createSlotRectangle(slot, bounds, true);
            slotShapes.push(rect);
        }
    });
}

function createSlotRectangle(slot, bounds, saved) {
    var color = getSlotColor(slot.stato);
    
    var rect = new google.maps.Rectangle({
        bounds: bounds,
        strokeColor: saved ? '#FFFFFF' : '#FFD700',
        strokeOpacity: 1,
        strokeWeight: saved ? 1 : 2,
        fillColor: color,
        fillOpacity: 0.7,
        map: map,
        editable: editMode && !saved,
        draggable: editMode && !saved
    });
    
    var center = {
        lat: (bounds.north + bounds.south) / 2,
        lng: (bounds.east + bounds.west) / 2
    };
    
    new google.maps.Marker({
        position: center,
        map: map,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 0
        },
        label: {
            text: slot.numero_esterno.toString(),
            color: '#FFFFFF',
            fontSize: '10px',
            fontWeight: 'bold'
        }
    });
    
    rect.addListener('click', function() {
        if (!editMode) {
            showSlotInfo(slot, center);
        }
    });
    
    rect.slotData = slot;
    return rect;
}

function createSlotPolygon(vertices, slot, saved) {
    var color = getSlotColor(slot.stato);
    
    var polygon = new google.maps.Polygon({
        paths: vertices,
        strokeColor: saved ? '#FFFFFF' : '#FFD700',
        strokeOpacity: 1,
        strokeWeight: saved ? 1 : 2,
        fillColor: color,
        fillOpacity: 0.7,
        map: map
    });
    
    var sumLat = 0, sumLng = 0;
    vertices.forEach(function(v) {
        sumLat += v.lat;
        sumLng += v.lng;
    });
    var center = {
        lat: sumLat / vertices.length,
        lng: sumLng / vertices.length
    };
    
    new google.maps.Marker({
        position: center,
        map: map,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 0
        },
        label: {
            text: slot.numero_esterno.toString(),
            color: '#FFFFFF',
            fontSize: '10px',
            fontWeight: 'bold'
        }
    });
    
    polygon.addListener('click', function() {
        if (!editMode) {
            showSlotInfo(slot, center);
        }
    });
    
    polygon.slotData = slot;
    return polygon;
}

function calculateRotatedRectangle(center, widthDeg, heightDeg, rotation) {
    var angleRad = rotation * Math.PI / 180;
    var cos = Math.cos(angleRad);
    var sin = Math.sin(angleRad);
    
    // Converti da gradi decimali a metri
    var widthMeters = widthDeg * 111320 * Math.cos(center.lat * Math.PI / 180);
    var heightMeters = heightDeg * 111320;
    
    // Metà dimensioni in metri
    var hw = widthMeters / 2;
    var hh = heightMeters / 2;
    
    var points = [
        {x: -hw, y: -hh}, // bottom-left
        {x: hw, y: -hh},  // bottom-right
        {x: hw, y: hh},   // top-right
        {x: -hw, y: hh}   // top-left
    ];
    
    // Ruota i punti
    var rotatedPoints = points.map(function(point) {
        return {
            x: point.x * cos - point.y * sin,
            y: point.x * sin + point.y * cos
        };
    });
    
    // Converti da metri tornando a gradi decimali
    return rotatedPoints.map(function(point) {
        return {
            lat: center.lat + (point.y / 111320),
            lng: center.lng + (point.x / (111320 * Math.cos(center.lat * Math.PI / 180)))
        };
    });
}

function initDrawingMode() {
    drawingManager = new google.maps.drawing.DrawingManager({
        drawingMode: null,
        drawingControl: false,
        rectangleOptions: {
            strokeColor: '#FFD700',
            strokeOpacity: 1,
            strokeWeight: 2,
            fillColor: '#FFD700',
            fillOpacity: 0.5,
            editable: true,
            draggable: true
        }
    });
    
    drawingManager.setMap(map);
    
    google.maps.event.addListener(drawingManager, 'rectanglecomplete', function(rectangle) {
        currentRectangle = rectangle;
        drawingManager.setDrawingMode(null);
        
        var bounds = rectangle.getBounds();
        var ne = bounds.getNorthEast();
        var sw = bounds.getSouthWest();
        var center = bounds.getCenter();
        
        currentRectangle.originalWidth = ne.lng() - sw.lng();
        currentRectangle.originalHeight = ne.lat() - sw.lat();
        currentRectangle.originalCenter = {
            lat: center.lat(),
            lng: center.lng()
        };
        
        document.getElementById('rotation-controls').style.display = 'block';
    });
}

function addDrawingControls() {
    var controlDiv = document.createElement('div');
    var slotListHtml = '';
    
    slotsWithoutCoords.forEach(function(s) {
        slotListHtml += '<div>';
        slotListHtml += '<input type="radio" name="slot_select" value="' + s.id + '" id="slot_' + s.id + '">';
        slotListHtml += '<label for="slot_' + s.id + '">Posto ' + s.numero_esterno + '</label>';
        slotListHtml += '</div>';
    });
    
    controlDiv.innerHTML = '<div class="drawing-controls">' +
        '<h6>Modalità Disegno</h6>' +
        '<div class="slot-list">' +
        '<strong>Posti da posizionare:</strong><br>' +
        slotListHtml +
        '</div>' +
        '<button class="btn btn-success btn-sm mb-2" onclick="startDrawing()">' +
        'Disegna nuovo posto' +
        '</button>'+
        '<div id="rotation-controls" class="rotation-control" style="display:none;">' +
        '<strong>Rotazione (opzionale):</strong>' +
        '<input type="range" class="rotation-slider" id="rotation-slider" ' +
        'min="-180" max="180" value="0" oninput="updateRotation(this.value)" onchange="updateRotation(this.value)">' +
        '<div>Angolo: <span id="rotation-value">0°</span></div>' +
        '<div class="mt-2">' +
        '<button class="btn btn-primary btn-sm" onclick="saveRectangle()">Salva posizione</button> ' +
        '<button class="btn btn-danger btn-sm" onclick="cancelRectangle()">Annulla</button>' +
        '</div></div></div>';
    
    map.controls[google.maps.ControlPosition.LEFT_TOP].push(controlDiv);
}

function startDrawing() {
    var selectedRadio = document.querySelector('input[name="slot_select"]:checked');
    if (!selectedRadio) {
        alert('Seleziona prima un posto dalla lista!');
        return;
    }
    
    selectedSlot = slotsWithoutCoords.find(function(s) { 
        return s.id == selectedRadio.value; 
    });
    currentRotation = 0;
    document.getElementById('rotation-slider').value = 0;
    document.getElementById('rotation-value').textContent = '0°';
    drawingManager.setDrawingMode(google.maps.drawing.OverlayType.RECTANGLE);
}

function updateRotation(value) {
    currentRotation = parseFloat(value);
    document.getElementById('rotation-value').textContent = value + '°';
    
    if (currentRectangle) {
        // Ottieni i bounds ORIGINALI (prima della rotazione)
        var bounds = currentRectangle.getBounds();
        var ne = bounds.getNorthEast();
        var sw = bounds.getSouthWest();
        
        // Calcola dimensioni ORIGINALI
        var originalWidth = Math.abs(ne.lng() - sw.lng());
        var originalHeight = Math.abs(ne.lat() - sw.lat());
        var originalCenter = {
            lat: (ne.lat() + sw.lat()) / 2,
            lng: (ne.lng() + sw.lng()) / 2
        };
        
        // Se rotazione = 0, mostra il rettangolo originale
        if (currentRotation === 0) {
            if (currentPolygon) {
                currentPolygon.setMap(null);
                currentPolygon = null;
            }
            currentRectangle.setMap(map);
            return;
        }
        
        // Nascondi rettangolo originale e mostra poligono ruotato
        currentRectangle.setMap(null);
        
        // Calcola vertici del rettangolo ruotato
        var vertices = calculateRotatedRectangle(originalCenter, originalWidth, originalHeight, currentRotation);
        
        // Crea o aggiorna il poligono ruotato
        if (!currentPolygon) {
            currentPolygon = new google.maps.Polygon({
                paths: vertices,
                strokeColor: '#FFD700',
                strokeOpacity: 1,
                strokeWeight: 2,
                fillColor: '#FFD700',
                fillOpacity: 0.5,
                map: map,
                editable: false,
                draggable: true
            });
        } else {
            currentPolygon.setPath(vertices);
        }
        
        // Salva i dati ORIGINALI
        currentPolygon.originalCenter = originalCenter;
        currentPolygon.originalWidth = originalWidth;
        currentPolygon.originalHeight = originalHeight;
    }
}

function saveRectangle() {
    if ((!currentRectangle && !currentPolygon) || !selectedSlot) return;
    
    var data;
    
    if (currentPolygon && currentRotation !== 0) {
        // Usa i dati ORIGINALI salvati, non i bounds del poligono ruotato
        data = {
            slot_id: selectedSlot.id,
            north: currentPolygon.originalCenter.lat + currentPolygon.originalHeight / 2,
            south: currentPolygon.originalCenter.lat - currentPolygon.originalHeight / 2,
            east: currentPolygon.originalCenter.lng + currentPolygon.originalWidth / 2,
            west: currentPolygon.originalCenter.lng - currentPolygon.originalWidth / 2,
            rotation: currentRotation,
            center_lat: currentPolygon.originalCenter.lat,
            center_lng: currentPolygon.originalCenter.lng,
            width: currentPolygon.originalWidth,
            height: currentPolygon.originalHeight
        };
    } else {
        var bounds = currentRectangle.getBounds();
        var center = bounds.getCenter();
        
        data = {
            slot_id: selectedSlot.id,
            north: bounds.getNorthEast().lat(),
            south: bounds.getSouthWest().lat(),
            east: bounds.getNorthEast().lng(),
            west: bounds.getSouthWest().lng(),
            rotation: 0,
            center_lat: center.lat(),
            center_lng: center.lng(),
            width: Math.abs(bounds.getNorthEast().lng() - bounds.getSouthWest().lng()),
            height: Math.abs(bounds.getNorthEast().lat() - bounds.getSouthWest().lat())
        };
    }
    
    fetch('/app/map/save_coordinates.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(function(response) { return response.json(); })
    .then(function(result) {
        if (result.success) {
            alert('Posizione salvata!');
            location.reload();
        } else {
            alert('Errore: ' + result.error);
        }
    });
}

function cancelRectangle() {
    if (currentRectangle) {
        currentRectangle.setMap(null);
        currentRectangle = null;
    }
    if (currentPolygon) {
        currentPolygon.setMap(null);
        currentPolygon = null;
    }
    document.getElementById('rotation-controls').style.display = 'none';
    document.getElementById('rotation-slider').value = 0;
    document.getElementById('rotation-value').textContent = '0°';
    selectedSlot = null;
}

function getSlotColor(stato) {
    var colors = {
        'Libero': '#00ff00',
        'Occupato': '#ff0000',
        'Riservato': '#ffff00',
        'Manutenzione': '#808080'
    };
    return colors[stato] || '#0080ff';
}

function showSlotInfo(slot, position) {
    var proprietarioHtml = '';
    if (slot.proprietario) {
        proprietarioHtml = '<tr><td>Proprietario:</td><td>' + slot.proprietario + '</td></tr>';
    }
    
    var content = '<div class="slot-info p-3">' +
        '<h5>Posto n° ' + slot.numero_esterno + '</h5>' +
        '<table class="table table-sm">' +
        '<tr><td>Stato:</td><td>' + slot.stato + '</td></tr>' +
        proprietarioHtml +
        '</table>' +
        '<a href="/app/slots/view.php?id=' + slot.id + '" class="btn btn-sm btn-primary">Dettagli</a>' +
        '</div>';
    
    infoWindow.setContent(content);
    infoWindow.setPosition(position);
    infoWindow.open(map);
}

function addLegend() {
    var legend = document.createElement('div');
    var editLegend = '';
    
    if (editMode) {
        editLegend = '<div class="legend-item">' +
            '<span class="legend-color" style="background: #FFD700;"></span>' +
            '<span>Nuovo/Non salvato</span>' +
            '</div>';
    }
    
    legend.innerHTML = '<div class="legend">' +
        '<h4>Legenda</h4>' +
        '<div class="legend-item">' +
        '<span class="legend-color" style="background: #00ff00;"></span>' +
        '<span>Libero</span>' +
        '</div>' +
        '<div class="legend-item">' +
        '<span class="legend-color" style="background: #ff0000;"></span>' +
        '<span>Occupato</span>' +
        '</div>' +
        editLegend +
        '</div>';
    
    map.controls[google.maps.ControlPosition.RIGHT_TOP].push(legend);
}

function filterByStatus(status) {
    slotShapes.forEach(function(shape) {
        if (status === 'all' || shape.slotData.stato === status) {
            shape.setMap(map);
        } else {
            shape.setMap(null);
        }
    });
}
</script>

<script async defer 
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA_Gq0x3SiNY2jVTaeSKE34ZazIdRNw2A0&libraries=drawing&callback=initMap">
</script>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>