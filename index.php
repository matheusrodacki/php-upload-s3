<?php
// Incluir o autoload do Composer
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Configurações AWS
$awsConfig = [
  'version' => 'latest',
  'region'  => 'us-east-2', // Exemplo: 'us-east-1'
  'credentials' => [
    'key'    => 'SUA_AWS_ACCESS_KEY_ID',
    'secret' => 'SUA_AWS_SECRET_ACCESS_KEY',
  ],
];

// Inicializar o cliente S3
$s3 = new S3Client($awsConfig);

// Definir o nome do bucket
$bucket = 'haruarchives';

// Função para gerar URL pré-assinada de download
function gerarPreSignedURLDownload($s3, $bucket, $s3Key, $expires = '+20 minutes')
{
  try {
    $cmd = $s3->getCommand('GetObject', [
      'Bucket' => $bucket,
      'Key'    => $s3Key,
    ]);

    $request = $s3->createPresignedRequest($cmd, $expires);
    $preSignedUrl = (string) $request->getUri();

    return $preSignedUrl;
  } catch (AwsException $e) {
    error_log($e->getMessage());
    return null;
  }
}

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Verificar se o arquivo foi enviado sem erros
  if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = basename($_FILES['file']['name']);
    $fileSize = $_FILES['file']['size'];
    $fileType = $_FILES['file']['type'];
    $fileNameCmps = pathinfo($fileName);
    $fileExtension = strtolower($fileNameCmps['extension']);

    // Sanitizar o nome do arquivo
    $newFileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileName);

    // Definir a chave do objeto no S3
    $s3Key = "cliente-upload/{$newFileName}";

    // Verificar se o arquivo já existe no S3
    try {
      $s3->headObject([
        'Bucket' => $bucket,
        'Key'    => $s3Key,
      ]);
      $fileExists = true;
    } catch (AwsException $e) {
      if ($e->getStatusCode() == 404) {
        $fileExists = false;
      } else {
        // Outro erro ocorreu
        $fileExists = true; // Para evitar sobrescrever em caso de erro
      }
    }

    if (!$fileExists) {
      // Fazer o upload do arquivo para o S3
      try {
        $result = $s3->putObject([
          'Bucket'      => $bucket,
          'Key'         => $s3Key,
          'SourceFile'  => $fileTmpPath,
          'ACL'         => 'private', // ou 'public-read' conforme necessário
          'ContentType' => $fileType,
        ]);

        $message = "Arquivo enviado com sucesso para o S3!";
      } catch (AwsException $e) {
        $message = "Houve um erro ao enviar o arquivo para o S3: " . $e->getMessage();
      }
    } else {
      $message = "Um arquivo com esse nome já existe no S3.";
    }
  } else {
    $message = "Nenhum arquivo foi enviado ou houve um erro no upload.";
  }
}

// Obter a lista de arquivos no bucket S3
try {
  $objects = $s3->listObjectsV2([
    'Bucket' => $bucket,
    'Prefix' => 'cliente-upload/',
  ]);

  if (isset($objects['Contents'])) {
    $files = [];
    foreach ($objects['Contents'] as $object) {
      // Extrair o nome do arquivo a partir da chave
      $fileName = basename($object['Key']);
      if ($fileName) { // Evita pastas vazias
        // Obter os metadados do objeto
        $head = $s3->headObject([
          'Bucket' => $bucket,
          'Key'    => $object['Key'],
        ]);

        $files[] = [
          'name' => $fileName,
          'size' => $head['ContentLength'],
          'type' => $head['ContentType'],
        ];
      }
    }
  } else {
    $files = [];
  }
} catch (AwsException $e) {
  $files = [];
  $message = "Erro ao listar arquivos no S3: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Gerenciador de Arquivos</title>
  <!-- Link do Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
  <div class="container mt-5">
    <h2 class="mb-4">Upload de Arquivos</h2>

    <!-- Exibir mensagem -->
    <?php if (isset($message)): ?>
      <div class="alert alert-info">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <!-- Formulário de Upload -->
    <form action="" method="post" enctype="multipart/form-data" class="mb-5">
      <div class="mb-3">
        <label for="file" class="form-label">Selecione o arquivo:</label>
        <input class="form-control" type="file" id="file" name="file" required>
      </div>
      <button type="submit" class="btn btn-primary">Enviar</button>
    </form>

    <h3>Arquivos Enviados</h3>
    <?php if (isset($files) && count($files) > 0): ?>
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Nome do Arquivo</th>
            <th>Tamanho</th>
            <th>Tipo</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($files as $file): ?>
            <?php
            $fileName = $file['name'];
            $fileSizeKB = $file['size'] / 1024;
            $fileType = $file['type'];
            $s3Key = "cliente-upload/{$fileName}";
            $urlDownload = gerarPreSignedURLDownload($s3, $bucket, $s3Key);
            ?>
            <tr>
              <td><?php echo htmlspecialchars($fileName); ?></td>
              <td><?php echo number_format($fileSizeKB, 2); ?> KB</td>
              <td><?php echo htmlspecialchars($fileType); ?></td>
              <td>
                <?php if ($urlDownload): ?>
                  <a href="<?php echo $urlDownload; ?>" class="btn btn-success btn-sm" download>Download</a>
                <?php else: ?>
                  <span class="text-danger">Erro no download</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>Nenhum arquivo enviado.</p>
    <?php endif; ?>
  </div>

  <!-- Link do Bootstrap JS e dependências -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>