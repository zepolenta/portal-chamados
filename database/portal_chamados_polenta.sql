-- =====================================================================
-- Portal de Chamados Polenta TI — Projeto Físico do Banco de Dados
-- SGBD: MySQL 8 / MariaDB 10.6+   |   Charset: utf8mb4
-- Autor: Gabriel Naldis do Nascimento (RGM 40479609) — PIT II
-- =====================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS portal_chamados
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portal_chamados;

-- ---------------------------------------------------------------------
-- CLIENTE: pessoa física ou jurídica atendida (mensalista ou avulso)
-- ---------------------------------------------------------------------
CREATE TABLE cliente (
  id_cliente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo_pessoa ENUM('PF','PJ') NOT NULL,
  nome VARCHAR(150) NOT NULL COMMENT 'Nome (PF) ou razão social (PJ)',
  cpf_cnpj VARCHAR(14) NOT NULL COMMENT '11 (CPF) ou 14 (CNPJ) dígitos',
  email VARCHAR(150) NOT NULL,
  telefone VARCHAR(20) NULL,
  modalidade ENUM('MENSALISTA','AVULSO') NOT NULL DEFAULT 'AVULSO',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_cliente_doc UNIQUE (cpf_cnpj),
  CONSTRAINT ck_cliente_doc CHECK (
    (tipo_pessoa = 'PF' AND CHAR_LENGTH(cpf_cnpj) = 11) OR
    (tipo_pessoa = 'PJ' AND CHAR_LENGTH(cpf_cnpj) = 14))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CONTRATO: plano de atendimento dos clientes mensalistas
-- ---------------------------------------------------------------------
CREATE TABLE contrato (
  id_contrato INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_cliente INT UNSIGNED NOT NULL,
  plano VARCHAR(60) NOT NULL,
  horas_mes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  inicio_vigencia DATE NOT NULL,
  fim_vigencia DATE NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_contrato_cliente FOREIGN KEY (id_cliente)
    REFERENCES cliente (id_cliente) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_contrato_vigencia CHECK (fim_vigencia IS NULL OR fim_vigencia >= inicio_vigencia)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- USUARIO: quem acessa o portal (solicitante do cliente, técnico, admin)
-- ---------------------------------------------------------------------
CREATE TABLE usuario (
  id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_cliente INT UNSIGNED NULL COMMENT 'Só para perfil CLIENTE',
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt/argon2',
  perfil ENUM('CLIENTE','TECNICO','ADMIN') NOT NULL DEFAULT 'CLIENTE',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acesso DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_usuario_email UNIQUE (email),
  CONSTRAINT fk_usuario_cliente FOREIGN KEY (id_cliente)
    REFERENCES cliente (id_cliente) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CATEGORIA: tipo de serviço (hardware, rede, e-mail, software...)
-- ---------------------------------------------------------------------
CREATE TABLE categoria (
  id_categoria SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(60) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT uq_categoria_nome UNIQUE (nome)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PRIORIDADE_SLA: metas de resposta e solução por prioridade (base ITIL)
-- Tempos em minutos úteis (seg–sex, 8h–17h)
-- ---------------------------------------------------------------------
CREATE TABLE prioridade_sla (
  id_prioridade TINYINT UNSIGNED PRIMARY KEY,
  nome VARCHAR(20) NOT NULL,
  descricao VARCHAR(200) NOT NULL,
  min_resposta SMALLINT UNSIGNED NOT NULL,
  min_solucao SMALLINT UNSIGNED NOT NULL,
  CONSTRAINT uq_prioridade_nome UNIQUE (nome),
  CONSTRAINT ck_sla_coerente CHECK (min_solucao >= min_resposta)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CHAMADO: solicitação aberta pelo cliente
-- ---------------------------------------------------------------------
CREATE TABLE chamado (
  id_chamado INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  protocolo CHAR(12) NOT NULL COMMENT 'Ex.: PL2026000123',
  id_cliente INT UNSIGNED NOT NULL,
  id_solicitante INT UNSIGNED NOT NULL,
  id_tecnico INT UNSIGNED NULL,
  id_categoria SMALLINT UNSIGNED NOT NULL,
  id_prioridade TINYINT UNSIGNED NOT NULL,
  titulo VARCHAR(120) NOT NULL,
  descricao TEXT NOT NULL,
  status ENUM('ABERTO','EM_ATENDIMENTO','AGUARDANDO_CLIENTE',
                           'RESOLVIDO','FECHADO','CANCELADO') NOT NULL DEFAULT 'ABERTO',
  aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  prazo_resposta DATETIME NOT NULL,
  prazo_solucao DATETIME NOT NULL,
  primeira_resposta_em DATETIME NULL,
  resolvido_em DATETIME NULL,
  fechado_em DATETIME NULL,
  nota_avaliacao TINYINT UNSIGNED NULL,
  comentario_avaliacao VARCHAR(500) NULL,
  CONSTRAINT uq_chamado_protocolo UNIQUE (protocolo),
  CONSTRAINT fk_chamado_cliente FOREIGN KEY (id_cliente) REFERENCES cliente (id_cliente),
  CONSTRAINT fk_chamado_solicitante FOREIGN KEY (id_solicitante) REFERENCES usuario (id_usuario),
  CONSTRAINT fk_chamado_tecnico FOREIGN KEY (id_tecnico) REFERENCES usuario (id_usuario),
  CONSTRAINT fk_chamado_categoria FOREIGN KEY (id_categoria) REFERENCES categoria (id_categoria),
  CONSTRAINT fk_chamado_prioridade  FOREIGN KEY (id_prioridade)  REFERENCES prioridade_sla (id_prioridade),
  CONSTRAINT ck_chamado_nota CHECK (nota_avaliacao IS NULL OR nota_avaliacao BETWEEN 1 AND 5),
  INDEX ix_chamado_status_prazo (status, prazo_solucao),
  INDEX ix_chamado_cliente (id_cliente, aberto_em),
  INDEX ix_chamado_tecnico (id_tecnico, status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- INTERACAO: mensagens do chamado (públicas ou notas internas do técnico)
-- ---------------------------------------------------------------------
CREATE TABLE interacao (
  id_interacao INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_chamado INT UNSIGNED NOT NULL,
  id_usuario INT UNSIGNED NOT NULL,
  mensagem TEXT NOT NULL,
  interna TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = visível só para a equipe técnica',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_interacao_chamado FOREIGN KEY (id_chamado)
    REFERENCES chamado (id_chamado) ON DELETE CASCADE,
  CONSTRAINT fk_interacao_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario),
  INDEX ix_interacao_chamado (id_chamado, criado_em)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ANEXO: arquivos enviados em uma interação (prints, logs)
-- ---------------------------------------------------------------------
CREATE TABLE anexo (
  id_anexo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_interacao INT UNSIGNED NOT NULL,
  nome_arquivo VARCHAR(200) NOT NULL,
  caminho VARCHAR(300) NOT NULL COMMENT 'Chave do objeto no armazenamento',
  tipo_mime VARCHAR(100) NOT NULL,
  tamanho_bytes INT UNSIGNED NOT NULL,
  enviado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_anexo_interacao FOREIGN KEY (id_interacao)
    REFERENCES interacao (id_interacao) ON DELETE CASCADE,
  CONSTRAINT ck_anexo_tamanho CHECK (tamanho_bytes <= 10485760)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- HISTORICO_STATUS: trilha de auditoria das mudanças de status
-- ---------------------------------------------------------------------
CREATE TABLE historico_status (
  id_historico INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_chamado INT UNSIGNED NOT NULL,
  id_usuario INT UNSIGNED NOT NULL,
  status_anterior VARCHAR(20) NULL,
  status_novo VARCHAR(20) NOT NULL,
  alterado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hist_chamado FOREIGN KEY (id_chamado)
    REFERENCES chamado (id_chamado) ON DELETE CASCADE,
  CONSTRAINT fk_hist_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario),
  INDEX ix_hist_chamado (id_chamado, alterado_em)
) ENGINE=InnoDB;

-- =====================================================================
-- Dados de domínio (carga inicial)
-- =====================================================================
INSERT INTO prioridade_sla (id_prioridade, nome, descricao, min_resposta, min_solucao) VALUES
  (1, 'Crítica', 'Parada total da operação ou de vários usuários', 60,  240),
  (2, 'Alta', 'Serviço importante degradado, sem alternativa', 120,  480),
  (3, 'Média', 'Problema com alternativa de contorno', 240, 1080),
  (4, 'Baixa', 'Dúvida, solicitação de serviço ou melhoria', 480, 2160);

INSERT INTO categoria (nome) VALUES
  ('Computadores e notebooks'), ('Rede e internet'), ('E-mail e Microsoft 365/Google Workspace'),
  ('Impressoras e periféricos'), ('Software e licenças'), ('Segurança e backup'), ('Outros');

-- =====================================================================
-- Visão de apoio: chamados em aberto com situação do SLA
-- =====================================================================
CREATE OR REPLACE VIEW vw_chamados_sla AS
SELECT c.protocolo, cl.nome AS cliente, cl.modalidade, p.nome AS prioridade, c.status,
       c.aberto_em, c.prazo_solucao,
       CASE WHEN c.status IN ('RESOLVIDO','FECHADO','CANCELADO') THEN 'ENCERRADO'
            WHEN NOW() > c.prazo_solucao THEN 'VIOLADO'
            WHEN TIMESTAMPDIFF(MINUTE, NOW(), c.prazo_solucao) <= p.min_solucao * 0.2 THEN 'EM RISCO'
            ELSE 'NO PRAZO' END AS situacao_sla
FROM chamado c
JOIN cliente cl ON cl.id_cliente = c.id_cliente
JOIN prioridade_sla p ON p.id_prioridade = c.id_prioridade;
