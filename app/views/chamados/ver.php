<?php use App\Core\Auth; use App\Models\Chamado; $equipe = Auth::ehEquipe(); $encerrado = in_array($c['status'], ['FECHADO', 'CANCELADO'], true); ?>
<div class="cabecalho">
  <div>
    <p class="muted"><a href="?r=chamados/index">← Voltar</a></p>
    <h1><?= e($c['protocolo']) ?> · <?= e($c['titulo']) ?></h1>
    <p><?= badge_status($c['status']) ?> <?= badge_prioridade($c['prioridade']) ?> <?= badge_sla($c['situacao_sla']) ?></p>
  </div>
</div>

<div class="grade-chamado">
  <section class="bloco">
    <h2>Descrição</h2>
    <p class="texto-livre"><?= nl2br(e($c['descricao'])) ?></p>

    <h2>Conversa</h2>
    <?php if (!$interacoes): ?><p class="vazio">Ainda não há mensagens neste chamado.</p><?php endif; ?>
    <ol class="linha-tempo">
      <?php foreach ($interacoes as $i): ?>
        <li class="msg <?= $i['perfil'] === 'CLIENTE' ? 'msg-cliente' : 'msg-equipe' ?> <?= $i['interna'] ? 'msg-interna' : '' ?>">
          <div class="msg-topo"><b><?= e($i['nome']) ?></b>
            <?php if ($i['interna']): ?><span class="badge interna">Nota interna</span><?php endif; ?>
            <time><?= data_br($i['criado_em']) ?></time></div>
          <div class="texto-livre"><?= nl2br(e($i['mensagem'])) ?></div>
          <?php foreach ($i['anexos'] as $a): ?>
            <a class="anexo" href="?r=chamados/anexo&id=<?= $a['id_anexo'] ?>">📎 <?= e($a['nome_arquivo']) ?> (<?= tamanho_br((int) $a['tamanho_bytes']) ?>)</a>
          <?php endforeach; ?>
        </li>
      <?php endforeach; ?>
    </ol>

    <?php if (!$encerrado): ?>
    <form class="formulario" method="post" action="?r=chamados/responder" enctype="multipart/form-data">
      <?= csrf_campo() ?>
      <input type="hidden" name="id" value="<?= $c['id_chamado'] ?>">
      <label for="mensagem">Nova mensagem</label>
      <textarea id="mensagem" name="mensagem" rows="4" placeholder="Escreva sua resposta…"></textarea>
      <label for="anexo">Anexo (opcional, até <?= tamanho_br(limite_upload_bytes()) ?>)</label>
      <input id="anexo" name="anexo" type="file" accept=".png,.jpg,.jpeg,.gif,.pdf,.txt,.zip" data-max="<?= limite_upload_bytes() ?>">
      <?php if ($equipe): ?>
        <label class="inline"><input type="checkbox" name="interna" value="1"> Nota interna (o cliente não vê)</label>
      <?php endif; ?>
      <div class="acoes"><button class="btn btn-primario" type="submit">Enviar</button></div>
    </form>
    <?php endif; ?>

    <?php if (!$equipe && $c['status'] === 'RESOLVIDO'): ?>
    <form class="formulario avaliacao" method="post" action="?r=chamados/avaliar">
      <?= csrf_campo() ?>
      <input type="hidden" name="id" value="<?= $c['id_chamado'] ?>">
      <h2>Como foi o atendimento?</h2>
      <div class="estrelas" role="radiogroup" aria-label="Nota de 1 a 5">
        <?php for ($n = 5; $n >= 1; $n--): ?>
          <input type="radio" id="nota<?= $n ?>" name="nota" value="<?= $n ?>"><label for="nota<?= $n ?>" title="<?= $n ?>">★</label>
        <?php endfor; ?>
      </div>
      <label for="comentario">Comentário (opcional)</label>
      <textarea id="comentario" name="comentario" rows="2" maxlength="500"></textarea>
      <div class="acoes"><button class="btn btn-primario" type="submit">Avaliar e encerrar</button></div>
    </form>
    <?php endif; ?>
  </section>

  <aside>
    <section class="bloco">
      <h2>Detalhes</h2>
      <dl class="detalhes">
        <dt>Cliente</dt><dd><?= e($c['cliente']) ?> (<?= $c['tipo_pessoa'] ?>, <?= $c['modalidade'] === 'MENSALISTA' ? 'mensalista' : 'avulso' ?>)</dd>
        <dt>Solicitante</dt><dd><?= e($c['solicitante']) ?></dd>
        <dt>Categoria</dt><dd><?= e($c['categoria']) ?></dd>
        <dt>Técnico</dt><dd><?= e($c['tecnico'] ?? 'Não atribuído') ?></dd>
        <dt>Aberto em</dt><dd><?= data_br($c['aberto_em']) ?></dd>
        <dt>Prazo 1ª resposta</dt><dd><?= data_br($c['prazo_resposta']) ?><?= $c['primeira_resposta_em'] ? ' · respondido ' . data_br($c['primeira_resposta_em']) : '' ?></dd>
        <dt>Prazo de solução</dt><dd><?= data_br($c['prazo_solucao']) ?></dd>
        <?php if ($c['resolvido_em']): ?><dt>Resolvido em</dt><dd><?= data_br($c['resolvido_em']) ?></dd><?php endif; ?>
        <?php if ($c['nota_avaliacao']): ?><dt>Avaliação</dt><dd><?= str_repeat('★', (int) $c['nota_avaliacao']) ?> <?= e($c['comentario_avaliacao']) ?></dd><?php endif; ?>
      </dl>
    </section>

    <?php if (($equipe && !$encerrado) || $transicoes): ?>
    <section class="bloco">
      <h2>Ações</h2>
      <?php if ($equipe && !$encerrado && (int) $c['id_tecnico'] !== Auth::id() && $c['status'] !== 'RESOLVIDO'): ?>
        <form method="post" action="?r=chamados/assumir"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $c['id_chamado'] ?>">
          <button class="btn btn-primario largo" type="submit">Assumir chamado</button></form>
      <?php endif; ?>
      <?php foreach ($transicoes as $novo): ?>
        <form method="post" action="?r=chamados/status"<?= $novo === 'CANCELADO' ? ' data-confirmar="Cancelar este chamado?"' : '' ?>>
          <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $c['id_chamado'] ?>"><input type="hidden" name="status" value="<?= $novo ?>">
          <button class="btn largo <?= $novo === 'CANCELADO' ? 'btn-perigo' : '' ?>" type="submit">
            <?= e(['EM_ATENDIMENTO' => $c['status'] === 'RESOLVIDO' ? 'Reabrir chamado' : 'Em atendimento', 'AGUARDANDO_CLIENTE' => 'Aguardar cliente',
                   'RESOLVIDO' => 'Marcar como resolvido', 'FECHADO' => 'Fechar chamado', 'CANCELADO' => 'Cancelar chamado'][$novo]) ?>
          </button></form>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="bloco">
      <h2>Histórico</h2>
      <ul class="historico">
        <?php foreach ($historico as $h): ?>
          <li><time><?= data_br($h['alterado_em']) ?></time> <?= e($h['nome']) ?>:
            <?= $h['status_anterior'] ? e(Chamado::STATUS[$h['status_anterior']]) . ' → ' : '' ?><b><?= e(Chamado::STATUS[$h['status_novo']]) ?></b></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </aside>
</div>
