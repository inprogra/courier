<?php

require_once('src/Courier.php');
error_reporting(-1);

use Custom\Data\Courier as Courier;

$courier = new Courier('test', 1);

$body = file_get_contents('php://input');
$request_data = (array) json_decode($body, true);


if (!array_key_exists('params', $request_data)) {
    print('Parameters missing');
    exit();
}

$package = $courier->newPackage($request_data['order'], $request_data['params']);
$tracking_number = $package->Shipment->TrackingNumber;

$courier->packagePDF($package->Shipment->TrackingNumber);
