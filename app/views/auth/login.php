<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Entrar · Portal de Chamados Polenta TI</title>
  <link rel="stylesheet" href="css/app.css">
</head>
<body class="tela-login">
  <main class="cartao-login">
    <div class="marca grande"><span class="logo">P</span> Polenta <b>Chamados</b></div>
    <p class="sub">Suporte de TI para você e sua empresa</p>
    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
    <form method="post" action="?r=auth/login" novalidate>
      <?= csrf_campo() ?>
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>" autofocus>
      <label for="senha">Senha</label>
      <input id="senha" name="senha" type="password" autocomplete="current-password" required>
      <button type="submit" class="btn btn-primario largo">Entrar</button>
    </form>
    <p class="ajuda">Esqueceu a senha? Fale com o suporte pelo WhatsApp ou e-mail da Polenta TI.</p>
  </main>
</body>
</html>
