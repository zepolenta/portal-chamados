<section class="bloco centro">
  <h1><?= e($titulo ?? 'Página não encontrada') ?></h1>
  <p><?= e($mensagem ?? 'O endereço acessado não existe.') ?></p>
  <p><a class="btn" href="?r=painel/index">Ir para o painel</a></p>
</section>
