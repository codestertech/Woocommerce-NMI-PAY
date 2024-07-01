<?php

$security_key = $_COOKIE['ks'];

$jsonContent = json_decode(file_get_contents('php://input'));

$fields = array(
    //'security_key' => 'rnQ9sqB4K333M98zHHBFVZC3qCMa8n9S',
    'security_key' => $security_key,
    'payment_token' => $jsonContent->paymentToken,
    'amount' => '10.00',
    'email' => $jsonContent->email,
    'phone' => $jsonContent->phone,
    'city' => $jsonContent->city,
    'address1' => $jsonContent->address1,
    'country' => $jsonContent->country,
    'first_name' => $jsonContent->firstName,
    'last_name' => $jsonContent->lastName,
    'zip' => $jsonContent->postalCode,
    'cavv' => $jsonContent->cavv,
    'xid' => $jsonContent->xid,
    'eci' => $jsonContent->eci,
    'cardholder_auth' => $jsonContent->cardHolderAuth,
    'three_ds_version' => $jsonContent->threeDsVersion,
    'directory_server_id' => $jsonContent->directoryServiceId
);

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://secure.nmi.com/api/transact.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $fields
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;
