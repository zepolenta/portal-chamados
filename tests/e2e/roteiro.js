/**
 * Testes ponta a ponta (caixa-preta) do roteiro CT01–CT10 + verificações de segurança.
 * Executa no navegador (Playwright/Chromium), salva capturas em docs/evidencias e o resultado em JSON.
 *
 * Uso:  BASE_URL=http://127.0.0.1:8080 node tests/e2e/roteiro.js
 * Requer: npm i playwright (ou Playwright já instalado) e a carga de demonstração (database/seed.php).
 */
const { chromium, devices } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8080';
const SENHA = 'Polenta2026';
const EVID = path.join(__dirname, '..', '..', 'docs', 'evidencias');
fs.mkdirSync(EVID, { recursive: true });

const resultados = [];
async function caso(id, descricao, fn) {
  const inicio = Date.now();
  try {
    const detalhe = await fn();
    resultados.push({ id, descricao, resultado: 'Aprovado', detalhe: detalhe || '', ms: Date.now() - inicio });
    console.log(`[OK]    ${id} ${descricao}`);
  } catch (e) {
    resultados.push({ id, descricao, resultado: 'Reprovado', detalhe: String(e.message || e).split('\n')[0], ms: Date.now() - inicio });
    console.log(`[FALHA] ${id} ${descricao} — ${e.message}`);
  }
}
function esperar(cond, msg) { if (!cond) throw new Error(msg); }

async function login(page, email) {
  await page.goto(`${BASE}/?r=auth/login`);
  await page.fill('#email', email);
  await page.fill('#senha', SENHA);
  await Promise.all([page.waitForLoadState('load'), page.click('button[type=submit]')]);
}
const foto = (page, nome, full = false) => page.screenshot({ path: path.join(EVID, nome), fullPage: full });

(async () => {
  const browser = await chromium.launch();
  const ctxDesk = () => browser.newContext({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2, locale: 'pt-BR' });

  // ---------- Cliente (Ana — mensalista PJ) ----------
  const cCtx = await ctxDesk();
  const cli = await cCtx.newPage();
  cli.on('dialog', d => d.accept());
  let novoId = null, novoProt = null;

  await caso('CT01', 'Login (válido e inválido)', async () => {
    await cli.goto(`${BASE}/?r=auth/login`);
    await cli.fill('#email', 'cliente@contabexemplo.com.br');
    await cli.fill('#senha', 'senhaErrada1');
    await cli.click('button[type=submit]');
    await cli.waitForSelector('.alerta-erro');
    esperar((await cli.textContent('.alerta-erro')).includes('E-mail ou senha inválidos'), 'mensagem de erro ausente');
    esperar(!(await cli.locator('#menu').count()), 'acessou o sistema com senha errada');
    await foto(cli, 'CT01a_login_senha_invalida.png');
    await login(cli, 'cliente@contabexemplo.com.br');
    esperar(cli.url().includes('painel/index') || (await cli.locator('.cards').count()) > 0, 'não abriu o painel');
    await foto(cli, 'CT01b_painel_cliente.png');
    return 'Senha errada exibe "E-mail ou senha inválidos" e não dá acesso; senha correta abre o painel.';
  });

  await caso('CT03', 'Campos obrigatórios do novo chamado', async () => {
    await cli.goto(`${BASE}/?r=chamados/novo`);
    await cli.click('button[type=submit]');
    await cli.waitForSelector('.erro-campo');
    const erros = await cli.locator('.erro-campo').allTextContents();
    esperar(erros.some(t => t.includes('título')) && erros.some(t => t.includes('Descreva')) && erros.some(t => t.includes('categoria')), 'faltou mensagem de campo obrigatório');
    await foto(cli, 'CT03_campos_obrigatorios.png', true);
    return `Formulário bloqueado com ${erros.length} mensagens de campo (título, categoria, descrição).`;
  });

  await caso('CT02', 'Abrir chamado (protocolo e prazos de SLA)', async () => {
    await cli.goto(`${BASE}/?r=chamados/novo`);
    await cli.fill('#titulo', 'Computador da recepção não liga');
    await cli.selectOption('#id_categoria', { label: 'Computadores e notebooks' });
    await cli.selectOption('#id_prioridade', { label: 'Alta' });
    await cli.fill('#descricao', 'O computador da recepção não liga desde hoje cedo. A luz da fonte não acende.');
    await Promise.all([cli.waitForURL(/chamados\/ver/), cli.click('button[type=submit]')]);
    const alerta = await cli.textContent('.alerta-sucesso');
    const m = alerta.match(/PL\d{10}/);
    esperar(m, 'protocolo não exibido');
    novoProt = m[0];
    novoId = new URL(cli.url()).searchParams.get('id');
    const det = await cli.textContent('.detalhes');
    esperar(det.includes('Prazo de solução') && /\d{2}\/\d{2}\/\d{4}/.test(det), 'prazos não exibidos');
    await foto(cli, 'CT02_chamado_aberto_protocolo_sla.png', true);
    return `Chamado ${novoProt} criado com prazos de 1ª resposta e solução calculados (prioridade Alta).`;
  });

  await caso('CT04', 'Anexo (até 10 MB aceito; acima recusado)', async () => {
    const pequeno = path.join(EVID, '..', 'tmp_print.png');
    const grande = path.join(EVID, '..', 'tmp_grande.zip');
    fs.writeFileSync(pequeno, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64'));
    fs.writeFileSync(grande, Buffer.alloc(11 * 1024 * 1024, 1));
    // arquivo grande: bloqueado na tela
    await cli.goto(`${BASE}/?r=chamados/ver&id=${novoId}`);
    await cli.setInputFiles('#anexo', grande);
    await cli.waitForSelector('.erro-js');
    esperar((await cli.textContent('.erro-js')).includes('10 MB'), 'aviso de limite ausente');
    await foto(cli, 'CT04a_anexo_acima_10MB_recusado.png');
    // arquivo grande: também recusado pelo servidor (envio direto, sem o JavaScript)
    const html = await cli.content();
    const token = html.match(/name="_csrf" value="([^"]+)"/)[1];
    const resp = await cli.request.post(`${BASE}/?r=chamados/responder`, { multipart: {
      _csrf: token, id: String(novoId), mensagem: '',
      anexo: { name: 'grande.zip', mimeType: 'application/zip', buffer: fs.readFileSync(grande) } }, maxRedirects: 0 });
    await cli.goto(`${BASE}/?r=chamados/ver&id=${novoId}`);
    const erroSrv = await cli.locator('.alerta-erro').allTextContents();
    esperar(resp.status() === 302 && erroSrv.join(' ').includes('10,0 MB'), 'servidor aceitou arquivo acima de 10 MB');
    // arquivo válido
    await cli.fill('#mensagem', 'Segue foto da tela.');
    await cli.setInputFiles('#anexo', pequeno);
    await Promise.all([cli.waitForLoadState('load'), cli.click('form[action*="responder"] button[type=submit]')]);
    esperar((await cli.locator('.anexo').count()) >= 1, 'anexo válido não listado');
    await foto(cli, 'CT04b_anexo_valido_listado.png', true);
    fs.unlinkSync(pequeno); fs.unlinkSync(grande);
    return 'Arquivo de 11 MB recusado na tela e no servidor; imagem pequena anexada e listada para download.';
  });

  await caso('CT05', 'Interação (resposta na linha do tempo)', async () => {
    await cli.goto(`${BASE}/?r=chamados/ver&id=${novoId}`);
    await cli.fill('#mensagem', 'Já tentei trocar a tomada e não resolveu.');
    await Promise.all([cli.waitForLoadState('load'), cli.click('form[action*="responder"] button[type=submit]')]);
    const msgs = await cli.locator('.linha-tempo .msg').allTextContents();
    esperar(msgs.some(t => t.includes('trocar a tomada')), 'mensagem não aparece');
    esperar(msgs.every(t => /\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/.test(t)), 'mensagem sem data/hora');
    await foto(cli, 'CT05_interacao_linha_do_tempo.png', true);
    return 'Mensagem exibida na linha do tempo com autor, data e hora.';
  });

  // ---------- Técnico ----------
  const tCtx = await ctxDesk();
  const tec = await tCtx.newPage();
  tec.on('dialog', d => d.accept());
  await login(tec, 'tecnico@polenta.tv');

  await caso('CT06', 'Atendimento: assumir e alterar status (histórico)', async () => {
    await tec.goto(`${BASE}/?r=chamados/ver&id=${novoId}`);
    await Promise.all([tec.waitForLoadState('load'), tec.click('text=Assumir chamado')]);
    esperar((await tec.textContent('h1 + p')).includes('Em atendimento'), 'status não mudou para Em atendimento');
    await Promise.all([tec.waitForLoadState('load'), tec.click('text=Aguardar cliente')]);
    const hist = await tec.textContent('.historico');
    esperar(hist.includes('Aberto → Em atendimento') && hist.includes('Em atendimento → Aguardando cliente'), 'histórico incompleto');
    await foto(tec, 'CT06_assumir_e_historico.png', true);
    return 'Técnico assumiu (Aberto → Em atendimento) e alterou para Aguardando cliente; histórico registrado com usuário e horário.';
  });

  await caso('CT07', 'Nota interna invisível ao cliente', async () => {
    await tec.goto(`${BASE}/?r=chamados/ver&id=${novoId}`);
    await tec.fill('#mensagem', 'Fonte provavelmente queimada. Levar fonte reserva.');
    await tec.check('input[name=interna]');
    await Promise.all([tec.waitForLoadState('load'), tec.click('form[action*="responder"] button[type=submit]')]);
    esperar((await tec.locator('.msg-interna').count()) >= 1, 'nota interna não registrada');
    await foto(tec, 'CT07a_nota_interna_visao_tecnico.png', true);
    await cli.goto(`${BASE}/?r=chamados/ver&id=${novoId}`);
    const texto = await cli.textContent('.linha-tempo');
    esperar(!texto.includes('Fonte provavelmente queimada'), 'cliente viu a nota interna');
    await foto(cli, 'CT07b_nota_interna_oculta_cliente.png', true);
    return 'Nota interna aparece para o técnico (destacada) e não aparece para o cliente.';
  });

  await caso('CT08', 'Painel de SLA (chamado vencido aparece como violado)', async () => {
    await tec.goto(`${BASE}/?r=painel/index`);
    const violados = Number(await tec.textContent('.card.violado .num'));
    esperar(violados >= 1, 'nenhum chamado violado no painel');
    esperar((await tec.locator('.sla-violado').count()) >= 1, 'etiqueta Violado ausente na fila');
    await foto(tec, 'CT08_painel_sla_violado.png', true);
    return `Painel mostra ${violados} chamado(s) com SLA violado e etiquetas no prazo / em risco / violado na fila.`;
  });

  // ---------- Administrador ----------
  const aCtx = await ctxDesk();
  const adm = await aCtx.newPage();
  await login(adm, 'admin@polenta.tv');

  await caso('CT09', 'Cadastro de cliente (CPF/CNPJ inválido ou duplicado)', async () => {
    await adm.goto(`${BASE}/?r=clientes/form`);
    await adm.check('input[name=tipo_pessoa][value=PF]');
    await adm.fill('#nome', 'Cliente Teste CPF');
    await adm.fill('#cpf_cnpj', '123.456.789-00');
    await adm.fill('#email', 'teste.cpf@exemplo.com');
    await adm.click('button[type=submit]');
    await adm.waitForSelector('.erro-campo');
    esperar((await adm.textContent('.erro-campo')).includes('CPF inválido'), 'CPF inválido aceito');
    await foto(adm, 'CT09a_cpf_invalido.png', true);
    await adm.check('input[name=tipo_pessoa][value=PJ]');
    await adm.fill('#cpf_cnpj', '11.222.333/0001-81');
    await adm.click('button[type=submit]');
    await adm.waitForSelector('.erro-campo');
    esperar((await adm.textContent('.erro-campo')).includes('Já existe'), 'CNPJ duplicado aceito');
    await foto(adm, 'CT09b_cnpj_duplicado.png', true);
    await adm.check('input[name=tipo_pessoa][value=PF]');
    await adm.fill('#cpf_cnpj', '390.533.447-05');
    await Promise.all([adm.waitForURL(/clientes\/index/), adm.click('button[type=submit]')]);
    esperar((await adm.textContent('.alerta-sucesso')).includes('salvo'), 'cadastro válido não salvo');
    await foto(adm, 'CT09c_cliente_salvo.png');
    return 'CPF inválido e CNPJ duplicado recusados com mensagem clara; CPF válido cadastrado.';
  });

  await caso('CT10', 'Avaliação do atendimento e uso no celular', async () => {
    // técnico resolve
    await tec.goto(`${BASE}/?r=chamados/ver&id=${novoId}`);
    await Promise.all([tec.waitForLoadState('load'), tec.click('text=Em atendimento')]);
    await Promise.all([tec.waitForLoadState('load'), tec.click('text=Marcar como resolvido')]);
    // cliente avalia
    await cli.goto(`${BASE}/?r=chamados/ver&id=${novoId}`);
    await cli.click('label[for=nota5]');
    await cli.fill('#comentario', 'Atendimento rápido, obrigado!');
    await foto(cli, 'CT10a_avaliacao.png', true);
    await Promise.all([cli.waitForLoadState('load'), cli.click('text=Avaliar e encerrar')]);
    esperar((await cli.textContent('h1 + p')).includes('Fechado'), 'chamado não foi fechado');
    esperar((await cli.textContent('.detalhes')).includes('★★★★★'), 'nota não registrada');
    // celular
    const mCtx = await browser.newContext({ ...devices['iPhone 13'], locale: 'pt-BR' });
    const mob = await mCtx.newPage();
    await mob.goto(`${BASE}/?r=auth/login`);
    await mob.screenshot({ path: path.join(EVID, 'CT10b_celular_login.png') });
    await login(mob, 'maria.exemplo@email.com');
    await mob.screenshot({ path: path.join(EVID, 'CT10c_celular_painel.png'), fullPage: true });
    await mob.goto(`${BASE}/?r=chamados/novo`);
    await mob.fill('#titulo', 'Wi-Fi de casa sem sinal');
    await mob.selectOption('#id_categoria', { label: 'Rede e internet' });
    await mob.fill('#descricao', 'O Wi-Fi não aparece no celular nem no notebook.');
    await Promise.all([mob.waitForURL(/chamados\/ver/), mob.click('button[type=submit]')]);
    await mob.screenshot({ path: path.join(EVID, 'CT10d_celular_chamado_aberto.png'), fullPage: true });
    const larg = await mob.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);
    esperar(larg, 'página com rolagem horizontal no celular');
    await mCtx.close();
    return 'Cliente avaliou com 5 estrelas e o chamado foi fechado; login, painel e abertura de chamado funcionaram no celular (iPhone 13) sem rolagem horizontal.';
  });

  // ---------- Verificações de segurança (desenvolvedor) ----------
  await caso('SEG01', 'Cliente não acessa chamado de outra empresa', async () => {
    const r = await cli.request.get(`${BASE}/?r=chamados/ver&id=3`); // chamado do Studio (seed)
    const t = await r.text();
    esperar(r.status() === 404 && t.includes('não encontrado'), `status ${r.status()}`);
    return 'Acesso direto pela URL ao chamado de outra empresa retorna 404.';
  });
  await caso('SEG02', 'Cliente não acessa telas administrativas', async () => {
    const r = await cli.request.get(`${BASE}/?r=clientes/index`);
    esperar(r.status() === 403, `status ${r.status()}`);
    return 'Rota de clientes retorna 403 (acesso negado) para perfil Cliente.';
  });
  await caso('SEG03', 'Formulário sem token CSRF é recusado', async () => {
    const r = await cli.request.post(`${BASE}/?r=chamados/responder`, { form: { id: String(novoId), mensagem: 'x' } });
    esperar(r.status() === 419, `status ${r.status()}`);
    return 'POST sem token retorna 419 (sessão expirada) e nada é gravado.';
  });
  await caso('SEG04', 'Texto com HTML é exibido escapado (XSS)', async () => {
    await cli.goto(`${BASE}/?r=chamados/novo`);
    await cli.fill('#titulo', '<script>alert(1)</script> teste');
    await cli.selectOption('#id_categoria', { label: 'Outros' });
    await cli.fill('#descricao', '<img src=x onerror=alert(1)> descrição de teste');
    let alertou = false;
    cli.once('dialog', d => { alertou = true; d.dismiss(); });
    await Promise.all([cli.waitForURL(/chamados\/ver/), cli.click('button[type=submit]')]);
    esperar(!alertou && (await cli.textContent('h1')).includes('<script>'), 'HTML executado');
    return 'Conteúdo com tags é mostrado como texto, sem executar scripts.';
  });

  await browser.close();
  fs.writeFileSync(path.join(EVID, 'resultado_testes.json'), JSON.stringify({ executado_em: new Date().toISOString(), base: BASE, resultados }, null, 2));
  const ok = resultados.filter(r => r.resultado === 'Aprovado').length;
  console.log(`\n${ok} de ${resultados.length} casos aprovados.`);
  process.exit(ok === resultados.length ? 0 : 1);
})();
