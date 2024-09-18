<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Configuração do cliente S3
$s3 = new S3Client([
  'version' => 'latest',
  'region'  => 'us-east-2', // Substitua pela sua região
  'credentials' => [
    'key'    => 'SUA_AWS_ACCESS_KEY_ID',
    'secret' => 'SUA_AWS_SECRET_ACCESS_KEY',
  ],
]);

// Função para gerar URL pré-assinada para upload
function gerarPreSignedURLUpload($s3, $bucket, $chave, $expiracao = '+20 minutes')
{
  try {
    $cmd = $s3->getCommand('PutObject', [
      'Bucket' => $bucket,
      'Key'    => $chave,
      'ACL'    => 'private', // ou 'public-read' conforme necessidade
    ]);

    $request = $s3->createPresignedRequest($cmd, $expiracao);
    $preSignedUrl = (string) $request->getUri();

    return $preSignedUrl;
  } catch (AwsException $e) {
    // Trate o erro conforme necessário
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
    // Trate o erro conforme necessário
    error_log($e->getMessage());
    return null;
  }
}
