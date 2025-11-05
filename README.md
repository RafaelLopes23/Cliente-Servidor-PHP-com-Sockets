# Projeto 1 (Redes) — Aplicação Cliente-Servidor em PHP

Este projeto implementa uma aplicação simples de armazenamento Chave-Valor (KV) usando um protocolo de texto próprio sobre TCP.

- Linguagem: PHP 8+
- Transporte: TCP (porta padrão 5000)
- Protocolo: `KV/1.0` (ver `PROTOCOL.md`)

## Estrutura

- `server.php` — servidor TCP concorrente (usa `stream_select`) com armazenamento em memória
- `kv-client.php` — cliente interativo em CLI
- `client-demo.php` — cliente automático para gerar tráfego previsível
- `PROTOCOL.md` — especificação do protocolo
- `IEEEconferencetemplate.tex` — base do relatório (adaptada)

## Requisitos

- PHP CLI instalado e no PATH
- Permissão de firewall para aceitar conexões na porta escolhida (5000 por padrão)
- Wireshark para análise

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

5) Para gerar tráfego de teste previsível (útil para capturas):

```powershell
php .\client-demo.php --host=192.168.1.10 --port=5000
```

## Análise com Wireshark

1) Abra o Wireshark e selecione a interface em uso (Wi‑Fi/Ethernet).
2) Inicie a captura e aplique o filtro: `tcp.port == 5000`.
3) Realize operações no cliente (SET/GET/KEYS/DEL).
4) Pare a captura. Use "Follow TCP Stream" para ver requisições e respostas em texto.
5) Responda ao Quadro 1 do enunciado:
   - Versão/tipo da aplicação: veja a saudação `OK WELCOME KV/1.0` (na primeira resposta do servidor).
   - Endereços IP: coluna `Source`/`Destination` dos pacotes TCP.
   - Protocolo de transporte: TCP (camada de transporte no Wireshark indica TCP).
   - Portas de origem/destino: na aba de detalhes do pacote TCP (ex.: Src Port, Dst Port). Servidor: porta 5000 (destino no SYN recebido pelo servidor). Cliente: porta efêmera (origem no mesmo pacote).
   - Carga útil: veja no campo `Data` dos pacotes (ex.: `GET foo\r\n`, `VALUE 3\r\n` seguido de `bar\r\n`).
   - Encapsulamento: inspeção das camadas Ethernet/IP/TCP/Dados no painel do Wireshark.

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
