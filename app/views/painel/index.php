<?php use App\Core\Auth; ?>
<div class="cabecalho">
  <h1>Olá, <?= e(explode(' ', Auth::user()['nome'])[0]) ?></h1>
  <a class="btn btn-primario" href="?r=chamados/novo">+ Novo chamado</a>
</div>

<section class="cards" aria-label="Indicadores">
  <a class="card" href="?r=chamados/index&status=ATIVOS"><span class="num"><?= $ind['ATIVOS'] ?></span><span>Chamados ativos</span></a>
  <div class="card ok"><span class="num"><?= $ind['NO_PRAZO'] ?></span><span>No prazo</span></div>
  <div class="card risco"><span class="num"><?= $ind['EM_RISCO'] ?></span><span>Em risco</span></div>
  <div class="card violado"><span class="num"><?= $ind['VIOLADO'] ?></span><span>SLA violado</span></div>
  <div class="card"><span class="num"><?= $ind['RESOLVIDOS_MES'] ?></span><span>Resolvidos no mês</span></div>
  <div class="card"><span class="num"><?= $ind['NOTA_MEDIA'] !== null ? number_format($ind['NOTA_MEDIA'], 1, ',', '') : '—' ?></span><span>Nota média</span></div>
</section>

<section class="bloco">
  <h2><?= Auth::ehEquipe() ? 'Fila por prioridade e vencimento' : 'Seus chamados em andamento' ?></h2>
  <?php if (!$ativos): ?>
    <p class="vazio">Nenhum chamado em andamento. 🎉</p>
  <?php else: ?>
  <div class="tabela-wrap"><table class="tabela">
    <thead><tr><th>Protocolo</th><th>Título</th><?php if (Auth::ehEquipe()): ?><th>Cliente</th><?php endif; ?><th>Prioridade</th><th>Status</th><th>Prazo de solução</th><th>SLA</th></tr></thead>
    <tbody>
    <?php foreach ($ativos as $c): ?>
      <tr>
        <td data-rot="Protocolo"><a href="?r=chamados/ver&id=<?= $c['id_chamado'] ?>"><?= e($c['protocolo']) ?></a></td>
        <td data-rot="Título"><?= e($c['titulo']) ?></td>
        <?php if (Auth::ehEquipe()): ?><td data-rot="Cliente"><?= e($c['cliente']) ?></td><?php endif; ?>
        <td data-rot="Prioridade"><?= badge_prioridade($c['prioridade']) ?></td>
        <td data-rot="Status"><?= badge_status($c['status']) ?></td>
        <td data-rot="Prazo"><?= data_br($c['prazo_solucao']) ?></td>
        <td data-rot="SLA"><?= badge_sla($c['situacao_sla']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if ($recentes): ?>
<section class="bloco">
  <h2>Encerrados recentemente</h2>
  <ul class="lista-simples">
    <?php foreach ($recentes as $c): ?>
      <li><a href="?r=chamados/ver&id=<?= $c['id_chamado'] ?>"><?= e($c['protocolo']) ?></a> — <?= e($c['titulo']) ?> <?= badge_status($c['status']) ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
