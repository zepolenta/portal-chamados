# Portal de Chamados Polenta TI

Aplicação web para abertura, atendimento e acompanhamento de chamados de suporte de TI, com **prazos de SLA por prioridade (base ITIL)**, clientes **PF/PJ** e **mensalista/avulso**.

Projeto Integrador Transdisciplinar em Sistemas de Informação II — Cruzeiro do Sul Virtual
Autor: **Gabriel Naldis do Nascimento** (RGM 40479609)

## Funcionalidades

| Perfil | O que faz |
|---|---|
| Cliente | Abre chamados (com protocolo e prazos de SLA), conversa com o técnico, envia anexos, cancela enquanto aberto, reabre e avalia o atendimento (1 a 5) |
| Técnico | Fila ordenada por prioridade e vencimento, assume chamados, altera status, registra notas internas, painel de SLA (no prazo / em risco / violado) |
| Administrador | Tudo do técnico + cadastro de clientes (validação de CPF/CNPJ), contratos de mensalistas e usuários |

Regras principais: protocolo `PL + ano + 6 dígitos`; prazos contados em horário útil (seg–sex, 8h–17h); toda mudança de status gravada no histórico; notas internas invisíveis ao cliente; cliente só vê chamados da própria empresa.

## Tecnologias

- **Back-end:** PHP 8 puro, padrão **MVC** (front controller `public/index.php`)
- **Front-end:** HTML5, CSS3 e JavaScript (responsivo, sem frameworks)
- **Banco de dados:** MySQL 8 / MariaDB 10.6+ (PDO com consultas preparadas)
- **Segurança:** senhas com `password_hash`, token CSRF em todo formulário, escape de saída (XSS), controle de acesso por perfil, sessão com cookie HttpOnly/SameSite

## Estrutura (MVC)

```
app/
  Core/          App (roteador), Controller, Database, Auth, View
  controllers/   Auth, Painel, Chamado, Cliente, Usuario
  models/        Chamado, Cliente, Usuario, Interacao, Anexo, Catalogo
  services/      ServicoSla, ServicoProtocolo, ValidadorDocumento
  views/         telas (layout, login, painel, chamados, clientes, usuários)
  helpers.php    funções de apoio das views
config/config.php
database/        portal_chamados_polenta.sql (projeto físico) e seed.php (dados de demonstração)
public/          index.php, css/, js/, .htaccess, .user.ini   ← raiz pública do site
storage/uploads/ anexos (fora da pasta pública)
tests/           unit/run.php (SLA e CPF/CNPJ) e e2e/roteiro.js (CT01–CT10)
docs/            manual do usuário, evidências de teste
```

## Como rodar no computador

Requisitos: PHP 8.1+ (extensões `pdo_mysql`, `mbstring`, `fileinfo`) e MySQL 8 ou MariaDB.

```bash
# 1. Banco de dados
mysql -u root -p < database/portal_chamados_polenta.sql

# 2. Configuração
cp .env.example .env        # ajuste usuário e senha do banco

# 3. Dados de demonstração (opcional)
php database/seed.php

# 4. Servidor
php -d upload_max_filesize=12M -d post_max_size=14M -S localhost:8080 -t public
```

Acesse http://localhost:8080. Usuários de demonstração (senha **Polenta2026**):

| E-mail | Perfil |
|---|---|
| admin@polenta.tv | Administrador |
| tecnico@polenta.tv | Técnico |
| cliente@contabexemplo.com.br | Cliente (PJ mensalista) |
| cliente@studioficticio.com.br | Cliente (PJ avulso) |
| maria.exemplo@email.com | Cliente (PF avulso) |

> Os clientes e pessoas da demonstração são fictícios. Troque as senhas antes de usar em produção.

## Publicar na internet (hospedagem PHP + MySQL)

1. Contrate/crie uma hospedagem com **PHP 8.1+ e MySQL** (há opções gratuitas).
2. No painel da hospedagem, crie um banco MySQL e importe `database/portal_chamados_polenta.sql` pelo phpMyAdmin.
3. Envie os arquivos do projeto por FTP/gerenciador de arquivos. **A pasta `public/` deve ser a raiz do site** (em muitas hospedagens, copie o conteúdo de `public/` para `htdocs`/`public_html` e as demais pastas um nível acima).
4. Crie o arquivo `.env` (a partir de `.env.example`) com os dados do banco da hospedagem.
5. Rode `php database/seed.php` (ou importe usuários pelo painel) para ter o primeiro administrador.
6. Opcional: no Cloudflare, crie o registro DNS `chamados.polenta.tv` apontando para a hospedagem e ative o proxy (HTTPS).

Os arquivos `public/.htaccess` e `public/.user.ini` elevam o limite de upload para anexos de até 10 MB. Se a hospedagem não permitir, o sistema detecta o limite real e informa o usuário.

## Testes

```bash
php tests/unit/run.php                                   # 19 testes de SLA e CPF/CNPJ
BASE_URL=http://localhost:8080 node tests/e2e/roteiro.js # CT01–CT10 + segurança (requer Playwright e o seed)
```

As capturas de tela e o resultado (`resultado_testes.json`) ficam em `docs/evidencias/`.

## Licença

MIT — veja `LICENSE`.
