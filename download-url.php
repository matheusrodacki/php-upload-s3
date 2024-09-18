<?php
// download-url.php

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Configuração do cliente S3
$s3 = new S3Client([
  'version' => 'latest',
  'region'  => 'us-east-1', // Substitua pela sua região
]);

// Receber dados do cliente (por exemplo, via JSON)
$data = json_decode(file_get_contents('php://input'), true);
$clienteId = $data['clienteId'];
$nomeArquivo = basename($data['nomeArquivo']); // Sanitização básica

$bucket = 'seu-bucket';
$chave = "clientes/{$clienteId}/uploads/{$nomeArquivo}";

$urlDownload = gerarPreSignedURLDownload($s3, $bucket, $chave);

if ($urlDownload) {
  echo json_encode(['url' => $urlDownload]);
} else {
  http_response_code(500);
  echo json_encode(['erro' => 'Não foi possível gerar a URL de download.']);
}
