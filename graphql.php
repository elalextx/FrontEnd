<?php
session_start();

function graphql_request($query, $variables = [], $useAuth = true) {
    $url = 'http://localhost:8092/graphql';
    $payload = json_encode(['query' => $query, 'variables' => $variables]);

    $headers = ['Content-Type: application/json'];

    if ($useAuth && !empty($_SESSION['token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['token'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['errors' => [['message' => "cURL error: $err"]]];
    }
    curl_close($ch);

    return json_decode($resp, true);
}
?>