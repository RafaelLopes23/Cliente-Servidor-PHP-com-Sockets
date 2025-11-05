# Inicia o cliente interativo apontando para o host/porta informados
param(
  [string]$Host = '127.0.0.1',
  [int]$Port = 5000
)

Write-Host "Conectando ao servidor em $Host:$Port ..."
php "$PSScriptRoot\kv-client.php" --host=$Host --port=$Port
