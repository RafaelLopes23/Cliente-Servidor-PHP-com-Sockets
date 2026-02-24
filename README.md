# Aplicação Cliente-Servidor em PHP

Este projeto implementa uma aplicação simples de armazenamento Chave-Valor (KV) usando um protocolo de texto próprio sobre TCP.

- Linguagem: PHP 8+
- Transporte: TCP (porta padrão 5000)
- Protocolo: `KV/1.0`

## Estrutura

- `server.php` — servidor TCP concorrente (usa `stream_select`) com armazenamento em memória
- `kv-client.php` — cliente interativo em CLI
- `client-demo.php` — cliente automático para gerar tráfego previsível

## Requisitos

- PHP CLI instalado e no PATH
- Permissão de firewall para aceitar conexões na porta escolhida (5000 por padrão)
  
## Como executar (Windows PowerShell)

1) Verifique o PHP:

```powershell
php -v
```

2) Inicie o servidor (porta 5000):

```powershell
php .\server.php --host=0.0.0.0 --port=5000
```

Se o Windows perguntar sobre o firewall, permita acesso na rede privada. Caso precise criar a regra manualmente:

```powershell
# Opcional: cria regra de firewall para a porta 5000 TCP
New-NetFirewallRule -DisplayName "ProjetoRedesKV-5000" -Direction Inbound -Protocol TCP -LocalPort 5000 -Action Allow
```

3) Descubra o IP do servidor (na mesma rede dos clientes):

```powershell
ipconfig
```

Anote o IPv4 da interface conectada à rede (ex.: `192.168.1.10`).

4) Em cada cliente (pode ser o mesmo PC ou outro dispositivo), rode:

```powershell
php .\kv-client.php --host=192.168.1.10 --port=5000
```

Dicas:
- Use `SET minhaChave` e depois digite o valor quando solicitado.
- Use `GET minhaChave`, `DEL minhaChave`, `KEYS`, `QUIT`.

## Dicas de rede

- Para testar resolução de nomes (se houver DNS):

```powershell
nslookup 192.168.1.10
```

- Os clientes devem estar na mesma rede local do servidor (ou roteamento permitido) e conseguir alcançar a porta TCP configurada.

## Observações

- O armazenamento é volátil (em memória) e só persiste enquanto o servidor estiver rodando.
- O limite de valor para `SET` é 1 MiB.
- Para encerrar o servidor, use Ctrl+C no terminal.
