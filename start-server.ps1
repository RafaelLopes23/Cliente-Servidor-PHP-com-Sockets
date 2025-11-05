# Inicializa o servidor KV na porta 5000 (ou especifique -Port 1234)
param(
  [int]$Port = 5000,
  [string]$Host = '0.0.0.0'
)

Write-Host "Iniciando servidor em $Host:$Port ..."
php "$PSScriptRoot\server.php" --host=$Host --port=$Port
