<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

header('Content-Type: application/json');

$response = [
    'success' => false
];

try {
    if (empty($_POST['property_no'])) {
        throw new Exception('Property number is required');
    }

    $property_no = trim($_POST['property_no']);

    $generator = new BarcodeGeneratorPNG();
    $barcodeBinary = $generator->getBarcode(
        $property_no,
        $generator::TYPE_CODE_128
    );

    $barcodeBase64 = 'data:image/png;base64,' . base64_encode($barcodeBinary);

    // OPTIONAL: save to DB when item_id exists
    if (!empty($_POST['item_id'])) {
        $item_id = (int) $_POST['item_id'];

        $mysqli = new mysqli('localhost', 'root', '', 'inventory_db');
        if ($mysqli->connect_error) {
            throw new Exception('DB connection failed');
        }

        $stmt = $mysqli->prepare("
            UPDATE inventory 
            SET barcode_data = ?, barcode_image = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ssi', $property_no, $barcodeBase64, $item_id);
        $stmt->execute();
        $stmt->close();
        $mysqli->close();
    }

    $response['success'] = true;
    $response['barcode_image'] = $barcodeBase64;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
