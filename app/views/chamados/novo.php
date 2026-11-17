<?php use App\Core\Auth; ?>
<div class="cabecalho"><h1>Novo chamado</h1></div>
<?php if ($erros): ?><div class="alerta alerta-erro" role="alert">Corrija os campos destacados para abrir o chamado.</div><?php endif; ?>

<form class="formulario" method="post" action="?r=chamados/novo" enctype="multipart/form-data" novalidate>
  <?= csrf_campo() ?>
  <?php if (Auth::ehEquipe()): ?>
    <label for="id_cliente">Cliente *</label>
    <select id="id_cliente" name="id_cliente" required class="<?= isset($erros['id_cliente']) ? 'invalido' : '' ?>">
      <option value="">Selecione…</option>
      <?php foreach ($clientes as $cl): ?><option value="<?= $cl['id_cliente'] ?>"<?= sel($d['id_cliente'] == $cl['id_cliente']) ?>><?= e($cl['nome']) ?></option><?php endforeach; ?>
    </select>
    <?= erro_campo($erros, 'id_cliente') ?>
  <?php endif; ?>

  <label for="titulo">Título *</label>
  <input id="titulo" name="titulo" maxlength="120" required placeholder="Ex.: Impressora do financeiro não imprime"
         value="<?= e($d['titulo']) ?>" class="<?= isset($erros['titulo']) ? 'invalido' : '' ?>">
  <?= erro_campo($erros, 'titulo') ?>

  <div class="duas-colunas">
    <div>
      <label for="id_categoria">Categoria *</label>
      <select id="id_categoria" name="id_categoria" required class="<?= isset($erros['id_categoria']) ? 'invalido' : '' ?>">
        <option value="">Selecione…</option>
        <?php foreach ($categorias as $c): ?><option value="<?= $c['id_categoria'] ?>"<?= sel($d['id_categoria'] == $c['id_categoria']) ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
      </select>
      <?= erro_campo($erros, 'id_categoria') ?>
    </div>
    <div>
      <label for="id_prioridade">Prioridade *</label>
      <select id="id_prioridade" name="id_prioridade" required>
        <?php foreach ($prioridades as $p): ?>
          <option value="<?= $p['id_prioridade'] ?>" data-desc="<?= e($p['descricao']) ?>"<?= sel($d['id_prioridade'] == $p['id_prioridade']) ?>><?= e($p['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="dica" id="desc-prioridade"></p>
      <?= erro_campo($erros, 'id_prioridade') ?>
    </div>
  </div>

  <label for="descricao">Descrição *</label>
  <textarea id="descricao" name="descricao" rows="6" required placeholder="O que aconteceu, desde quando, quem é afetado e o que já foi tentado."
            class="<?= isset($erros['descricao']) ? 'invalido' : '' ?>"><?= e($d['descricao']) ?></textarea>
  <?= erro_campo($erros, 'descricao') ?>

  <label for="anexo">Anexo (opcional, até <?= tamanho_br(limite_upload_bytes()) ?> — imagem, PDF, TXT ou ZIP)</label>
  <input id="anexo" name="anexo" type="file" accept=".png,.jpg,.jpeg,.gif,.pdf,.txt,.zip" data-max="<?= limite_upload_bytes() ?>">
  <?= erro_campo($erros, 'anexo') ?>

  <div class="acoes">
    <button type="submit" class="btn btn-primario">Abrir chamado</button>
    <a class="btn btn-link" href="?r=chamados/index">Cancelar</a>
  </div>
</form>

<aside class="bloco sla-info">
  <h2>Prazos de atendimento (horário útil: seg–sex, 8h às 17h)</h2>
  <table class="tabela compacta">
    <thead><tr><th>Prioridade</th><th>Quando usar</th><th>1ª resposta</th><th>Solução</th></tr></thead>
    <tbody>
    <?php foreach ($prioridades as $p): ?>
      <tr><td><?= badge_prioridade($p['nome']) ?></td><td><?= e($p['descricao']) ?></td>
          <td><?= $p['min_resposta'] / 60 ?> h</td><td><?= $p['min_solucao'] / 60 ?> h</td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</aside>
