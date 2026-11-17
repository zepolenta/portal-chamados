/**
 * Grava um vídeo de demonstração (sem narração) percorrendo as principais funções.
 * Uso: BASE_URL=http://127.0.0.1:8080 node tests/e2e/video_demo.js  (gera docs/video/*.webm)
 */
const { chromium } = require('playwright');
const path = require('path');
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8080';
const OUT = path.join(__dirname, '..', '..', 'docs', 'video');
const ESCALA = Number(process.env.ESCALA || 4.0); // tempo extra para a narração
const pausa = (ms) => new Promise(r => setTimeout(r, ms * ESCALA));

async function legenda(page, texto) {
  await page.evaluate((t) => {
    let el = document.getElementById('legenda-demo');
    if (!el) {
      el = document.createElement('div');
      el.id = 'legenda-demo';
      el.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:rgba(20,33,61,.92);color:#fff;' +
        'font:600 20px system-ui,Arial;padding:12px 22px;border-radius:12px;z-index:9999;max-width:90%;text-align:center;box-shadow:0 6px 20px rgba(0,0,0,.3)';
      document.body.appendChild(el);
    }
    el.textContent = t;
  }, texto);
}
async function ir(page, url, texto, espera = 2500) {
  await page.goto(BASE + url);
  if (texto) await legenda(page, texto);
  await pausa(espera);
}
async function clicar(page, sel, texto, espera = 2200) {
  await Promise.all([page.waitForLoadState('load'), page.click(sel)]);
  if (texto) await legenda(page, texto);
  await pausa(espera);
}
async function entrar(page, email, texto) {
  await ir(page, '/?r=auth/login', texto, 1500);
  await page.type('#email', email, { delay: 45 });
  await page.type('#senha', 'Polenta2026', { delay: 45 });
  await pausa(600);
  await clicar(page, 'button[type=submit]', null, 300);
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 720 }, locale: 'pt-BR',
    recordVideo: { dir: OUT, size: { width: 1280, height: 720 } } });
  const page = await ctx.newPage();
  page.on('dialog', d => d.accept());

  // 1. Cliente
  await entrar(page, 'cliente@contabexemplo.com.br', '1. Login do cliente (Contabilidade Exemplo — mensalista PJ)');
  await legenda(page, 'Painel do cliente: chamados ativos e situação do SLA');
  await pausa(3500);
  await ir(page, '/?r=chamados/novo', '2. Abrindo um novo chamado', 1500);
  await page.type('#titulo', 'Servidor de arquivos inacessível', { delay: 40 });
  await page.selectOption('#id_categoria', { label: 'Rede e internet' });
  await pausa(700);
  await page.selectOption('#id_prioridade', { label: 'Crítica' });
  await legenda(page, 'Cada prioridade explica quando usar e define os prazos (tabela abaixo)');
  await pausa(2500);
  await page.type('#descricao', 'Ninguém consegue abrir a pasta compartilhada do servidor desde as 9h. Afeta o escritório todo.', { delay: 18 });
  await pausa(800);
  await clicar(page, 'button[type=submit]', 'Protocolo gerado e prazos de 1ª resposta e solução calculados em horário útil', 4500);
  const idNovo = new URL(page.url()).searchParams.get('id');
  await page.type('#mensagem', 'Reiniciei o roteador e não resolveu.', { delay: 35 });
  await clicar(page, 'form[action*="responder"] button[type=submit]', '3. Conversa registrada na linha do tempo do chamado', 3000);
  await clicar(page, 'a.sair', null, 500);

  // 2. Técnico
  await entrar(page, 'tecnico@polenta.tv', '4. Login do técnico');
  await legenda(page, 'Painel do técnico: indicadores de SLA (no prazo, em risco, violado)');
  await pausa(3500);
  await ir(page, '/?r=chamados/index&status=ATIVOS', 'Fila ordenada por prioridade e vencimento do SLA', 3500);
  await ir(page, '/?r=chamados/ver&id=' + idNovo, '5. Técnico abre o chamado crítico', 2000);
  await clicar(page, 'text=Assumir chamado', 'Assumir: status muda para "Em atendimento" e vai para o histórico', 3000);
  await page.type('#mensagem', 'Serviço SMB parado no servidor. Reiniciando o serviço.', { delay: 30 });
  await page.check('input[name=interna]');
  await clicar(page, 'form[action*="responder"] button[type=submit]', '6. Nota interna: visível só para a equipe técnica', 3000);
  await page.type('#mensagem', 'Pronto! O acesso à pasta foi restabelecido. Pode testar?', { delay: 30 });
  await clicar(page, 'form[action*="responder"] button[type=submit]', 'Resposta ao cliente registra a primeira resposta do SLA', 2500);
  await clicar(page, 'text=Marcar como resolvido', '7. Chamado marcado como resolvido', 2500);
  await clicar(page, 'a.sair', null, 500);

  // 3. Cliente avalia
  await entrar(page, 'cliente@contabexemplo.com.br', '8. Cliente confere a solução');
  await ir(page, '/?r=chamados/ver&id=' + idNovo, 'A nota interna do técnico não aparece para o cliente', 3500);
  await page.click('label[for=nota5]');
  await page.type('#comentario', 'Resolvido rapidamente, obrigado!', { delay: 35 });
  await legenda(page, '9. Avaliação do atendimento (1 a 5 estrelas)');
  await pausa(2000);
  await clicar(page, 'text=Avaliar e encerrar', 'Chamado fechado com a avaliação registrada', 3500);
  await clicar(page, 'a.sair', null, 500);

  // 4. Administrador
  await entrar(page, 'admin@polenta.tv', '10. Login do administrador');
  await ir(page, '/?r=clientes/index', 'Clientes PF/PJ, mensalistas (com plano) e avulsos', 3000);
  await ir(page, '/?r=clientes/form', 'Cadastro de cliente com validação de CPF/CNPJ', 1500);
  await page.check('input[name=tipo_pessoa][value=PF]');
  await page.type('#nome', 'Cliente Demonstração', { delay: 30 });
  await page.type('#cpf_cnpj', '111.222.333-00', { delay: 40 });
  await page.type('#email', 'demo@exemplo.com', { delay: 30 });
  await clicar(page, 'button[type=submit]', 'CPF inválido é recusado com mensagem clara', 3500);
  await ir(page, '/?r=usuarios/index', 'Usuários e perfis de acesso (cliente, técnico, administrador)', 3500);
  await ir(page, '/?r=painel/index', 'Portal de Chamados Polenta TI — PHP 8 (MVC) + MySQL', 3000);

  await ctx.close();
  await browser.close();
  console.log('Vídeo gravado em', OUT);
})();
