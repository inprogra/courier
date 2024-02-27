<?php

namespace Custom\Data;

class Courier
{
    public $test;
    public $error_level;
    public $domain;
    public $params;
    public function __construct(string $test = 'test', int $error_level = 1)
    {
        $this->error_level = $error_level;
        if ($test == 'test') {
            $this->domain = 'https://mtapi.net/?testMode=1';
        } else {
            $this->domain = 'https://mtapi.net/';
        }
    }
    public function sendData(array $data)
    {
        $ch = curl_init($this->domain);
        # Setup request to send json via POST.
        $payload = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        # Return response instead of printing.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        # Send request.
        $result_json = curl_exec($ch);
        curl_close($ch);
        if ($result_json) {
            $result = json_decode($result_json);                        
            if ($result->ErrorLevel > $this->error_level) {
                print_r('Error:' . $result->Error);
                exit();
            }
        }
        return json_decode($result_json);
    }

    private function validateData(array $data)
    {

        $result = ['ConsigneeAddress' => [], 'ConsignorAddress' => [], 'Shipment' => []];
        $defaults = ['sender_company', 'sender_fullname', 'sender_address', 'sender_city', 'sender_postalcode', 'sender_email', 'sender_phone', 'delivery_company', 'delivery_fullname', 'delivery_address', 'delivery_city', 'delivery_postalcode', 'delivery_country', 'delivery_email', 'delivery_phone'];
        $filter = array_intersect_key($data, array_flip($defaults));

        $requiredFields = array_diff_key(array_flip($defaults), $filter);
        if (!empty($requiredFields)) {
            exit('Parameters are required ' . json_encode(array_keys($requiredFields)));
        }

        $result['ConsignorAddress'] = [
            'Name' => (strlen($filter['sender_fullname']) <= 30 ? htmlspecialchars($filter['sender_fullname']) : exit('parameter sender_fullname can be 30 chars long')),
            'Company' => (strlen($filter['sender_company']) <= 30 ? htmlspecialchars($filter['sender_company']) : exit('parameter sender_company can be 30 chars long')),
            'AddressLine1' => (strlen($filter['sender_address']) <= 30 ? htmlspecialchars($filter['sender_address']) : exit('parameter sender_address can be 30 chars long')),
            'City' => (strlen($filter['sender_city']) <= 30 ? htmlspecialchars($filter['sender_city']) : exit('parameter sender_city can be 30 chars long')),
            'Zip' => (strlen($filter['sender_postalcode']) <= 20 ? htmlspecialchars($filter['sender_postalcode']) : exit('parameter sender_postalcode can be 20 chars long')),
            'Country' => (array_key_exists('sender_country', $filter) &&  strlen($filter['sender_country']) == 2 ? htmlspecialchars($filter['sender_country']) : ''),
            'Phone' => (strlen($filter['sender_phone']) <= 15 ? htmlspecialchars($filter['sender_phone']) : exit('parameter sender_phone can be 15 chars long')),
            'Email' => ($filter['sender_email'] ? filter_var($filter['sender_email'], FILTER_SANITIZE_EMAIL) : ''),
        ];
        $result['ConsigneeAddress'] = [
            'Name' => (strlen($filter['sender_fullname']) <= 30 ? htmlspecialchars($filter['sender_fullname']) : exit('parameter sender_fullname can be 30 chars long')),
            'Company' => (strlen($filter['delivery_company']) <= 30 ? htmlspecialchars($filter['delivery_company']) : exit('parameter delivery_company can be 30 chars long')),
            'AddressLine1' => (strlen($filter['delivery_address']) <= 30 ? htmlspecialchars($filter['delivery_address']) : exit('parameter delivery_address can be 30 chars long')),
            'City' => (strlen($filter['delivery_city']) <= 30 ? htmlspecialchars($filter['delivery_city']) : exit('parameter delivery_city can be 30 chars long')),
            'Zip' => (strlen($filter['delivery_postalcode']) <= 20 ? htmlspecialchars($filter['delivery_postalcode']) : exit('parameter sender_postalcode can be 20 chars long')),
            'Country' => (strlen($filter['delivery_country']) == 2 ? htmlspecialchars($filter['delivery_country']) : exit('parameter delivery_country can be 2 chars long')),
            'Phone' => (strlen($filter['delivery_phone']) <= 15 ? htmlspecialchars($filter['sender_phone']) : exit('parameter sender_phone can be 15 chars long')),
            'Email' => ($filter['delivery_email'] ? filter_var($filter['delivery_email'], FILTER_SANITIZE_EMAIL) : exit('parameter delivery_email is required')),
        ];

        return $result;
    }
    public function newPackage(array $order, array $params)
    {
        $this->params = $params;
        $validatedData = $this->validateData($order);
        $validatedData['Apikey'] = $params['api_key'];
        $validatedData['Command'] = 'OrderShipment';
        $validatedData['Shipment']['ShipperReference'] = time();
        $validatedData['Shipment']['LabelFormat'] = $params['label_format'];
        $validatedData['Shipment']['Service'] = $params['service'];
        $validatedData['Shipment']['ConsignorAddress'] = $validatedData['ConsignorAddress'];
        $validatedData['Shipment']['ConsigneeAddress'] = $validatedData['ConsigneeAddress'];
        unset($validatedData['ConsignorAddress']);
        unset($validatedData['ConsigneeAddress']);

        $package = $this->sendData($validatedData);

        return $package;
    }
    public function packagePDF(string $trackingNumber)
    {
        $request = [
            'Apikey' => $this->params['api_key'],
            'Command' => 'GetShipmentLabel',
            'Shipment' => [
                'LabelFormat' => $this->params['label_format'],
                'TrackingNumber' => $trackingNumber,
            ]
        ];
        $response = $this->sendData($request);
        
        $sticker = base64_decode($response->Shipment->LabelImage);
        header('Content-Type: application/pdf');
        echo $sticker;
        exit();
    }
}
