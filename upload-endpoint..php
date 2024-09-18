<?php
// composer autoload
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Configuração do cliente S3
$s3 = new S3Client([
  'version' => 'latest',
  'region'  => 'us-east-1', // Substitua pela sua região
  // As credenciais podem ser configuradas via variáveis de ambiente ou roles do IAM
]);

// Funções para gerar URLs pré-assinadas
function gerarPreSignedURLUpload($s3, $bucket, $chave, $expiracao = '+20 minutes')
{
  try {
    $cmd = $s3->getCommand('PutObject', [
      'Bucket' => $bucket,
      'Key'    => $chave,
      'ACL'    => 'private',
    ]);

    $request = $s3->createPresignedRequest($cmd, $expiracao);
    $preSignedUrl = (string) $request->getUri();

    return $preSignedUrl;
  } catch (AwsException $e) {
    error_log($e->getMessage());
    return null;
  }
}

function gerarPreSignedURLDownload($s3, $bucket, $chave, $expiracao = '+20 minutes')
{
  try {
    $cmd = $s3->getCommand('GetObject', [
      'Bucket' => $bucket,
      'Key'    => $chave,
    ]);

    $request = $s3->createPresignedRequest($cmd, $expiracao);
    $preSignedUrl = (string) $request->getUri();

    return $preSignedUrl;
  } catch (AwsException $e) {
    error_log($e->getMessage());
    return null;
  }
}

// Função para validar arquivos
function validarArquivo($nomeArquivo, $tamanho, $tipo)
{
  $tiposPermitidos = ['image/jpeg', 'image/png', 'application/pdf'];
  $tamanhoMaximo = 5 * 1024 * 1024; // 5MB

  if (!in_array($tipo, $tiposPermitidos)) {
    return false;
  }

  if ($tamanho > $tamanhoMaximo) {
    return false;
  }

  return true;
}

// Roteamento básico (pode ser substituído por um framework)
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestUri === '/upload-url' && $requestMethod === 'POST') {
  $data = json_decode(file_get_contents('php://input'), true);
  $clienteId = $data['clienteId'];
  $nomeArquivo = basename($data['nomeArquivo']);
  $tipoArquivo = $data['tipoArquivo'];
  $tamanhoArquivo = $data['tamanhoArquivo'];

  // Validação do arquivo
  if (!validarArquivo($nomeArquivo, $tamanhoArquivo, $tipoArquivo)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Arquivo inválido.']);
    exit;
  }

  $bucket = 'seu-bucket';
  $chave = "clientes/{$clienteId}/uploads/{$nomeArquivo}";

  $urlUpload = gerarPreSignedURLUpload($s3, $bucket, $chave);

  if ($urlUpload) {
    // Aqui você pode salvar a referência no banco de dados
    echo json_encode(['url' => $urlUpload]);
  } else {
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível gerar a URL de upload.']);
  }
} elseif ($requestUri === '/download-url' && $requestMethod === 'POST') {
  $data = json_decode(file_get_contents('php://input'), true);
  $clienteId = $data['clienteId'];
  $nomeArquivo = basename($data['nomeArquivo']);

  $bucket = 'seu-bucket';
  $chave = "clientes/{$clienteId}/uploads/{$nomeArquivo}";

  $urlDownload = gerarPreSignedURLDownload($s3, $bucket, $chave);

  if ($urlDownload) {
    echo json_encode(['url' => $urlDownload]);
  } else {
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível gerar a URL de download.']);
  }
} else {
  http_response_code(404);
  echo json_encode(['erro' => 'Endpoint não encontrado.']);
}
