<?php
// Start session if needed
session_start();

// Add header to skip ngrok warning
header("ngrok-skip-browser-warning: true");

// Database connection
$mysqli = new mysqli('localhost', 'root', '', 'inventory_db');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Get barcode from URL
$barcode = isset($_GET['code']) ? trim($_GET['code']) : '';

// Include ordinal function
function ordinal($number) {
    $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) return $number.'th';
    return $number.$ends[$number % 10];
}

// If no barcode, show scanner page with camera option
if (empty($barcode)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
        <title>Barcode Scanner - Inventory System</title>
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <!-- QuaggaJS for barcode scanning -->
        <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
        <style>
            body {
                background: #f4f6f9;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 0;
                min-height: 100vh;
            }
            .scanner-container {
                width: 100%;
                max-width: 480px;
                margin: 0 auto;
                padding: 15px;
            }
            .header-card {
                background: linear-gradient(135deg, #1e1f55 0%, #2a2b75 100%);
                color: white;
                border-radius: 20px;
                padding: 25px 20px;
                margin-bottom: 20px;
                text-align: center;
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            }
            .header-card h1 {
                font-size: 24px;
                font-weight: 600;
                margin-bottom: 5px;
            }
            .mode-selector {
                display: flex;
                gap: 10px;
                margin-bottom: 20px;
            }
            .mode-btn {
                flex: 1;
                background: white;
                border: 2px solid #dee2e6;
                border-radius: 12px;
                padding: 12px;
                font-weight: 600;
                color: #495057;
                transition: all 0.3s;
                cursor: pointer;
                text-align: center;
            }
            .mode-btn.active {
                background: #1e1f55;
                border-color: #1e1f55;
                color: white;
            }
            .mode-btn i {
                margin-right: 8px;
            }
            .scanner-panel {
                background: white;
                border-radius: 20px;
                padding: 20px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.08);
                margin-bottom: 20px;
            }
            #camera-panel {
                display: block;
            }
            #manual-panel {
                display: none;
            }
            #scanner-viewport {
                width: 100%;
                height: 300px;
                background: #000;
                border-radius: 12px;
                overflow: hidden;
                position: relative;
                margin-bottom: 15px;
            }
            #scanner-viewport video,
            #scanner-viewport canvas {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            #scanner-viewport canvas.drawingBuffer {
                position: absolute;
                top: 0;
                left: 0;
            }
            .scanner-controls {
                display: flex;
                gap: 10px;
                margin-bottom: 15px;
            }
            .btn-control {
                flex: 1;
                padding: 12px;
                border: none;
                border-radius: 10px;
                font-weight: 600;
                color: white;
                transition: all 0.3s;
            }
            .btn-start {
                background: #28a745;
            }
            .btn-stop {
                background: #dc3545;
            }
            .btn-switch {
                background: #6c757d;
            }
            #scanner-status {
                text-align: center;
                font-size: 14px;
                padding: 10px;
                border-radius: 8px;
                background: #f8f9fa;
            }
            .manual-input {
                background: #f8f9fa;
                border-radius: 15px;
                padding: 20px;
            }
            .form-control-lg {
                border-radius: 12px;
                border: 2px solid #e0e0e0;
                padding: 15px;
                font-size: 16px;
            }
            .btn-scan {
                background: #1e1f55;
                color: white;
                border: none;
                border-radius: 12px;
                padding: 15px;
                font-size: 18px;
                font-weight: 600;
                width: 100%;
                margin-top: 15px;
            }
            .instruction-box {
                background: #e8f4fd;
                border-radius: 15px;
                padding: 20px;
                margin-top: 20px;
                border-left: 4px solid #0d6efd;
            }
            .recent-scans {
                margin-top: 20px;
            }
            .recent-item {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 12px 15px;
                margin-bottom: 8px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: 1px solid #e9ecef;
                cursor: pointer;
            }
            .recent-code {
                font-family: monospace;
                font-weight: 600;
                color: #1e1f55;
            }
            .footer-note {
                text-align: center;
                color: #6c757d;
                font-size: 13px;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="scanner-container">
            <!-- Header -->
            <div class="header-card">
                <i class="fas fa-camera-retro fa-3x mb-3"></i>
                <h1>Barcode Scanner</h1>
                <p>Scan barcodes using camera or manual entry</p>
            </div>

            <!-- Mode Selector -->
            <div class="mode-selector">
                <div class="mode-btn active" id="mode-camera" onclick="switchMode('camera')">
                    <i class="fas fa-camera"></i> Camera
                </div>
                <div class="mode-btn" id="mode-manual" onclick="switchMode('manual')">
                    <i class="fas fa-keyboard"></i> Manual
                </div>
            </div>

            <!-- Camera Panel -->
            <div id="camera-panel" class="scanner-panel">
                <div id="scanner-viewport"></div>
                
                <div class="scanner-controls">
                    <button class="btn-control btn-start" onclick="startScanner()">
                        <i class="fas fa-play"></i> Start
                    </button>
                    <button class="btn-control btn-stop" onclick="stopScanner()">
                        <i class="fas fa-stop"></i> Stop
                    </button>
                    <button class="btn-control btn-switch" onclick="switchCamera()">
                        <i class="fas fa-sync-alt"></i> Switch
                    </button>
                </div>

                <div id="scanner-status">
                    <i class="fas fa-info-circle"></i> Click Start to begin scanning
                </div>

                <div class="instruction-box">
                    <h5><i class="fas fa-lightbulb me-2"></i>Tips:</h5>
                    <ul class="mb-0 small">
                        <li>Hold camera steady over barcode</li>
                        <li>Ensure good lighting</li>
                        <li>Center the barcode in view</li>
                    </ul>
                </div>
            </div>

            <!-- Manual Panel -->
            <div id="manual-panel" class="scanner-panel">
                <div class="manual-input">
                    <label class="form-label fw-bold mb-3">
                        <i class="fas fa-keyboard me-2"></i>Enter Barcode Number
                    </label>
                    <input type="text" 
                           class="form-control form-control-lg" 
                           id="manualBarcode" 
                           placeholder="e.g., INV-001"
                           autocomplete="off"
                           onkeypress="if(event.key === 'Enter') lookupManual()">
                    <button class="btn-scan" onclick="lookupManual()">
                        <i class="fas fa-search"></i> Look Up Item
                    </button>
                </div>
            </div>

            <!-- Recent Scans -->
            <div class="recent-scans" id="recentScans" style="display: none;">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-history me-2"></i>Recent Scans
                </h6>
                <div id="recentList"></div>
                <button class="btn btn-link btn-sm text-muted mt-2" onclick="clearRecent()">
                    Clear History
                </button>
            </div>

            <!-- Footer -->
            <div class="footer-note">
                <i class="fas fa-shield-alt me-1"></i>
                Point Google Camera at barcode for automatic scanning
            </div>
        </div>

        <script>
        // Scanner variables
        let scannerActive = false;
        let currentCamera = 'environment'; // back camera by default
        let lastScannedCode = '';

        // Recent scans functions
        function loadRecent() {
            const recent = JSON.parse(localStorage.getItem('recentScans') || '[]');
            const recentDiv = document.getElementById('recentScans');
            const recentList = document.getElementById('recentList');
            
            if (recent.length > 0) {
                recentDiv.style.display = 'block';
                recentList.innerHTML = recent.slice(0, 5).map(item => `
                    <div class="recent-item" onclick="useRecent('${item.code}')">
                        <span class="recent-code">${item.code}</span>
                        <span class="badge bg-secondary">${item.time}</span>
                    </div>
                `).join('');
            } else {
                recentDiv.style.display = 'none';
            }
        }

        function addToRecent(code) {
            const recent = JSON.parse(localStorage.getItem('recentScans') || '[]');
            const now = new Date();
            const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            recent.unshift({
                code: code,
                time: timeStr
            });
            
            if (recent.length > 10) recent.pop();
            localStorage.setItem('recentScans', JSON.stringify(recent));
            loadRecent();
        }

        function useRecent(code) {
            window.location.href = 'scan.php?code=' + encodeURIComponent(code);
        }

        function clearRecent() {
            localStorage.removeItem('recentScans');
            loadRecent();
        }

        // Mode switching
        function switchMode(mode) {
            if (mode === 'camera') {
                document.getElementById('camera-panel').style.display = 'block';
                document.getElementById('manual-panel').style.display = 'none';
                document.getElementById('mode-camera').classList.add('active');
                document.getElementById('mode-manual').classList.remove('active');
            } else {
                document.getElementById('camera-panel').style.display = 'none';
                document.getElementById('manual-panel').style.display = 'block';
                document.getElementById('mode-camera').classList.remove('active');
                document.getElementById('mode-manual').classList.add('active');
                stopScanner();
            }
        }

        // Manual lookup
        function lookupManual() {
            const barcode = document.getElementById('manualBarcode').value.trim();
            if (barcode) {
                window.location.href = 'scan.php?code=' + encodeURIComponent(barcode);
            } else {
                alert('Please enter a barcode number');
            }
        }

        // Camera scanner functions
        function startScanner() {
            if (scannerActive) return;
            
            const statusDiv = document.getElementById('scanner-status');
            statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Initializing camera...';
            
            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: document.querySelector('#scanner-viewport'),
                    constraints: {
                        facingMode: currentCamera,
                        width: 640,
                        height: 480
                    },
                },
                decoder: {
                    readers: [
                        "code_128_reader",
                        "ean_reader",
                        "ean_8_reader",
                        "code_39_reader",
                        "codabar_reader",
                        "upc_reader",
                        "i2of5_reader"
                    ]
                },
                locate: true,
                locator: {
                    halfSample: true,
                    patchSize: "medium"
                }
            }, function(err) {
                if (err) {
                    statusDiv.innerHTML = '<span class="text-danger">❌ Camera error: ' + err.message + '</span>';
                    return;
                }
                
                Quagga.start();
                scannerActive = true;
                statusDiv.innerHTML = '<span class="text-success">✅ Scanner ready - Point at barcode</span>';
            });

            Quagga.onDetected(function(data) {
                const code = data.codeResult.code;
                
                if (code !== lastScannedCode) {
                    lastScannedCode = code;
                    
                    // Beep sound
                    try {
                        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                        const oscillator = audioCtx.createOscillator();
                        const gainNode = audioCtx.createGain();
                        oscillator.connect(gainNode);
                        gainNode.connect(audioCtx.destination);
                        oscillator.frequency.setValueAtTime(800, audioCtx.currentTime);
                        gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
                        oscillator.start();
                        oscillator.stop(audioCtx.currentTime + 0.1);
                    } catch (e) {}
                    
                    statusDiv.innerHTML = `<span class="text-success">✅ Scanned: ${code}</span>`;
                    
                    // Add to recent and redirect
                    addToRecent(code);
                    
                    // Pause briefly then redirect
                    setTimeout(() => {
                        window.location.href = 'scan.php?code=' + encodeURIComponent(code);
                    }, 500);
                }
            });
        }

        function stopScanner() {
            if (scannerActive) {
                Quagga.stop();
                scannerActive = false;
                document.getElementById('scanner-status').innerHTML = '<span class="text-warning">⏸️ Scanner stopped</span>';
            }
        }

        function switchCamera() {
            stopScanner();
            currentCamera = currentCamera === 'environment' ? 'user' : 'environment';
            setTimeout(() => {
                startScanner();
            }, 500);
        }

        // Initialize
        window.onload = function() {
            loadRecent();
            
            // Check if URL has barcode
            const urlParams = new URLSearchParams(window.location.search);
            const code = urlParams.get('code');
            if (code) {
                // If barcode is in URL, we'll show results (handled by PHP)
                console.log('Viewing barcode:', code);
            }
        }

        // Clean up on page unload
        window.onbeforeunload = function() {
            if (scannerActive) {
                Quagga.stop();
            }
        };
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================================
// WE HAVE A BARCODE - SHOW FULL ITEM DETAILS
// ============================================================================

// Query to get FULL item details with all related information
$query = "
    SELECT inv.*, 
           s.name as section_name, 
           d.name as department_name, 
           b.name AS building_name,
           b.floor AS building_floor,
           e1.firstname AS approved_first,
           e1.lastname AS approved_last,
           e2.firstname AS verified_first,
           e2.lastname AS verified_last,
           e3.firstname AS allocate_first,
           e3.lastname AS allocate_last,
           eq.name AS equip_name, 
           eq.category AS equip_category
    FROM inventory inv
    LEFT JOIN sections s ON inv.section_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN buildings b ON d.building_id = b.id
    LEFT JOIN employees e1 ON inv.approved_by = e1.id
    LEFT JOIN employees e2 ON inv.verified_by = e2.id
    LEFT JOIN employees e3 ON inv.allocate_to = e3.id
    LEFT JOIN equipment eq ON inv.equipment_id = eq.id
    WHERE inv.property_no = ?
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $barcode);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

// Get certified correct names if any
$cert_names = [];
if ($item && !empty($item['certified_correct'])) {
    $cert_ids = json_decode($item['certified_correct'], true);
    if (!empty($cert_ids)) {
        $placeholders = implode(',', array_fill(0, count($cert_ids), '?'));
        $types = str_repeat('i', count($cert_ids));
        $cert_stmt = $mysqli->prepare("SELECT firstname, lastname FROM employees WHERE id IN ($placeholders)");
        if ($cert_stmt) {
            $cert_stmt->bind_param($types, ...$cert_ids);
            $cert_stmt->execute();
            $cert_res = $cert_stmt->get_result();
            while ($row = $cert_res->fetch_assoc()) {
                $cert_names[] = $row['firstname'] . ' ' . $row['lastname'];
            }
            $cert_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Item Details - <?= htmlspecialchars($barcode) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .detail-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            padding: 15px;
        }
        .result-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            margin-bottom: 20px;
        }
        .header-found {
            background: linear-gradient(135deg, #1e1f55 0%, #2a2b75 100%);
            color: white;
            padding: 25px 20px;
            text-align: center;
        }
        .header-notfound {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 25px 20px;
            text-align: center;
        }
        .header-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .header-title {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 5px;
            opacity: 0.9;
        }
        .barcode-number {
            font-size: 26px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            background: rgba(255,255,255,0.15);
            padding: 10px 20px;
            border-radius: 50px;
            display: inline-block;
            margin-top: 10px;
            letter-spacing: 1px;
        }
        .content {
            padding: 25px;
        }
        .info-section {
            margin-bottom: 25px;
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
        }
        .info-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e1f55;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #dee2e6;
        }
        .info-grid {
            display: flex;
            flex-wrap: wrap;
            margin: 0;
        }
        .info-row {
            width: 100%;
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            width: 40%;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        .info-value {
            width: 60%;
            color: #212529;
            font-size: 14px;
            word-break: break-word;
        }
        .condition-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
        }
        .condition-serviceable { background: #d4edda; color: #155724; }
        .condition-nonserviceable { background: #f8d7da; color: #721c24; }
        .condition-condemn { background: #fff3cd; color: #856404; }
        .condition-repair { background: #d1ecf1; color: #0c5460; }
        .condition-disposal { background: #e2e3e5; color: #383d41; }
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        .btn-primary-custom {
            flex: 1;
            background: #1e1f55;
            color: white;
            border: none;
            border-radius: 15px;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }
        .btn-primary-custom:hover {
            background: #2a2b75;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,31,85,0.3);
            color: white;
        }
        .btn-success-custom {
            flex: 1;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 15px;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }
        .btn-success-custom:hover {
            background: #218838;
            transform: translateY(-2px);
            color: white;
        }
        .value-highlight {
            color: #28a745;
            font-weight: 700;
        }
        .footer-note {
            text-align: center;
            color: #6c757d;
            font-size: 13px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="detail-container">
        <?php if ($item): ?>
            <!-- ITEM FOUND - SHOW ALL DETAILS -->
            <div class="result-card">
                <div class="header-found">
                    <div class="header-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="header-title">Item Found in Database</div>
                    <div class="barcode-number"><?= htmlspecialchars($item['property_no']) ?></div>
                </div>
                
                <div class="content">
                    <!-- BASIC INFORMATION -->
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="fas fa-info-circle me-2"></i>Basic Information
                        </div>
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">Property No:</div>
                                <div class="info-value"><strong><?= htmlspecialchars($item['property_no']) ?></strong></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Article:</div>
                                <div class="info-value"><?= htmlspecialchars($item['equip_name'] ?: $item['article_name'] ?: 'N/A') ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Type of PPE:</div>
                                <div class="info-value"><?= htmlspecialchars($item['equip_category'] ?: $item['category'] ?: 'N/A') ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Type of Equipment:</div>
                                <div class="info-value"><?= htmlspecialchars($item['type_equipment'] ?: 'N/A') ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Description:</div>
                                <div class="info-value"><?= htmlspecialchars($item['description'] ?: 'No description') ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- LOCATION -->
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="fas fa-map-marker-alt me-2"></i>Location
                        </div>
                        <div class="info-grid">
                            <?php if ($item['building_name']): ?>
                                <div class="info-row">
                                    <div class="info-label">Building:</div>
                                    <div class="info-value"><?= htmlspecialchars($item['building_name']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Floor:</div>
                                    <div class="info-value"><?= ordinal($item['building_floor']) ?> Floor</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Department:</div>
                                    <div class="info-value"><?= htmlspecialchars($item['department_name'] ?: 'N/A') ?></div>
                                </div>
                                <?php if ($item['section_name']): ?>
                                <div class="info-row">
                                    <div class="info-label">Section:</div>
                                    <div class="info-value"><?= htmlspecialchars($item['section_name']) ?></div>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="info-row">
                                    <div class="info-label">Location:</div>
                                    <div class="info-value text-muted">Not assigned</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ACCOUNTABILITY -->
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="fas fa-user-check me-2"></i>Accountability
                        </div>
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">Accountable Person:</div>
                                <div class="info-value">
                                    <?php if ($item['allocate_first']): ?>
                                        <strong><?= htmlspecialchars($item['allocate_first'] . ' ' . $item['allocate_last']) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($cert_names)): ?>
                            <div class="info-row">
                                <div class="info-label">Certified Correct:</div>
                                <div class="info-value"><?= htmlspecialchars(implode(', ', $cert_names)) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($item['approved_first']): ?>
                            <div class="info-row">
                                <div class="info-label">Approved By:</div>
                                <div class="info-value"><?= htmlspecialchars($item['approved_first'] . ' ' . $item['approved_last']) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($item['verified_first']): ?>
                            <div class="info-row">
                                <div class="info-label">Verified By:</div>
                                <div class="info-value"><?= htmlspecialchars($item['verified_first'] . ' ' . $item['verified_last']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- QUANTITY & VALUE -->
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="fas fa-calculator me-2"></i>Quantity & Value
                        </div>
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">Physical Count:</div>
                                <div class="info-value"><strong><?= $item['qty_physical_count'] ?: 0 ?></strong></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Property Card:</div>
                                <div class="info-value"><?= $item['qty_property_card'] ?: 0 ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Unit Value:</div>
                                <div class="info-value value-highlight">₱<?= number_format($item['unit_value'] ?: 0, 2) ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Unit of Measure:</div>
                                <div class="info-value"><?= htmlspecialchars($item['uom'] ?: 'N/A') ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- CONDITION -->
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="fas fa-clipboard-check me-2"></i>Condition
                        </div>
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">Status:</div>
                                <div class="info-value">
                                    <?php
                                    $condition = $item['condition_text'] ?? 'Unknown';
                                    $conditionClass = 'condition-serviceable';
                                    if ($condition == 'Non-Serviceable') $conditionClass = 'condition-nonserviceable';
                                    elseif ($condition == 'For Condemn') $conditionClass = 'condition-condemn';
                                    elseif ($condition == 'Under Repair') $conditionClass = 'condition-repair';
                                    elseif ($condition == 'For Disposal') $conditionClass = 'condition-disposal';
                                    ?>
                                    <span class="condition-badge <?= $conditionClass ?>">
                                        <?= htmlspecialchars($condition) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ADDITIONAL DETAILS -->
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="fas fa-ellipsis-h me-2"></i>Additional Details
                        </div>
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">Fund Cluster:</div>
                                <div class="info-value"><?= htmlspecialchars($item['fund_cluster'] ?: 'N/A') ?></div>
                            </div>
                            <?php if ($item['remarks']): ?>
                            <div class="info-row">
                                <div class="info-label">Remarks:</div>
                                <div class="info-value"><?= htmlspecialchars($item['remarks']) ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <div class="info-label">Date Added:</div>
                                <div class="info-value"><?= $item['date_added'] ? date('M d, Y', strtotime($item['date_added'])) : 'N/A' ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Last Updated:</div>
                                <div class="info-value"><?= $item['date_updated'] ? date('M d, Y', strtotime($item['date_updated'])) : 'N/A' ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- BARCODE PREVIEW (if available) -->
                    <?php if (!empty($item['barcode_image'])): ?>
                    <div class="info-section text-center">
                        <div class="info-section-title">
                            <i class="fas fa-barcode me-2"></i>Barcode
                        </div>
                        <img src="<?= $item['barcode_image'] ?>" style="max-width: 100%; height: 60px;" alt="Barcode">
                    </div>
                    <?php endif; ?>

                    <!-- ACTION BUTTONS -->
                    <div class="action-buttons">
                        <a href="scan.php" class="btn-success-custom">
                            <i class="fas fa-camera me-2"></i>Scan Again
                        </a>
                        <a href="inventory.php<?= isset($item['type_equipment']) && $item['type_equipment'] == 'Semi-expendable Equipment' ? '?type=semi-expendable' : (isset($item['type_equipment']) && $item['type_equipment'] == 'Property Plant Equipment (50K Above)' ? '?type=ppe' : '') ?>" class="btn-primary-custom">
                            <i class="fas fa-boxes me-2"></i>Inventory
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- ITEM NOT FOUND -->
            <div class="result-card">
                <div class="header-notfound">
                    <div class="header-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="header-title">Item Not Found</div>
                    <div class="barcode-number"><?= htmlspecialchars($barcode) ?></div>
                </div>
                
                <div class="content text-center">
                    <div style="padding: 30px 0;">
                        <i class="fas fa-barcode fa-4x text-muted mb-3"></i>
                        <h5 class="mb-3">No item found with this barcode</h5>
                        <p class="text-muted mb-4">
                            The barcode number <strong><?= htmlspecialchars($barcode) ?></strong> doesn't exist in the database.
                        </p>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div class="action-buttons">
                        <a href="scan.php" class="btn-success-custom">
                            <i class="fas fa-camera me-2"></i>Scan Again
                        </a>
                        <a href="inventory.php" class="btn-primary-custom">
                            <i class="fas fa-boxes me-2"></i>View Inventory
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer-note">
            <i class="fas fa-barcode me-1"></i>
            Inventory Management System - v1.0
        </div>
    </div>
</body>
</html>
<?php
$mysqli->close();
?>